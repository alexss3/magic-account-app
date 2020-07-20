<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\Entity\MagicAccount;
use App\Entity\Config;
use App\Entity\Deposit;
use App\Entity\Withdraw;
use App\Entity\Payment;
use App\Entity\User;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MagicAccountController extends AbstractController {

    /**
     * @Route("/account", name="account_home", methods={"GET"})
     */

    public function home() {
        /* US-MA-9:
         * As a user I want to see an overview of my Magic account balances, separated into
         * promotion money, multiplied amount and deposited amount, such that I know what I have
         * available for spending or payout.
         */

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $userId = $user->getId();
        $username = $user->getUsername();

        // Get the balance and promo money
        $entityManager = $this->getDoctrine()->getManager();

        // Use MagicAccountRepository to retrieve the MagicAccount
        // based on the userId column
        // ** Ideally this should be in a service as it will
        // be used for all account related routes
        $account = $this->getDoctrine()
            ->getRepository(MagicAccount::class)
            ->findAccountByUserId($userId);

        // Load the multiplier setting
        // Default is 3x
        $multiplier = $this->getDoctrine()
            ->getRepository(Config::class)
            ->findConfigSettingByName('multiplier')
            ->getConfigValue();

        $deposit = $account->getDepositBalance();
        $balance = $deposit * $multiplier;
        $promo = $account->getPromoValue();

        return $this->render('account/home.html.twig', [
            'username' => $username,
            'deposit' => $deposit,
            'balance' => $balance,
            'promo' => $promo
        ]);
    }

    /**
     * @Route("/account/deposit", name="account_deposit", methods={"POST"})
     */

    public function deposit() {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Get values from POST data
        $amountToDeposit = $request->request->get('amount');

        // Ideally the client-side form will not allow
        // trying to deposit 0 or negative values

        if ($amountToDeposit > 0) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $userId = $user->getId();

            // Use MagicAccountRepository to retrieve the MagicAccount
            // based on the userId column
            // ** Ideally this should be in a service as it will
            // be used for all account related routes
            $account = $this->getDoctrine()
                ->getRepository(MagicAccount::class)
                ->findAccountByUserId($userId);

            // Load the config setting for max balance
            // (after multiplier) and make sure we don't 
            // deposit more than is allowed
            // Default is 500kr
            $maxAllowed = $this->getDoctrine()
                ->getRepository(Config::class)
                ->findConfigSettingByName('max_balance')
                ->getConfigValue();

            // Load the config setting for max daily
            // deposit amount
            // Default is 100kr
            $maxDailyDeposit = $this->getDoctrine()
                ->getRepository(Config::class)
                ->findConfigSettingByName('max_daily')
                ->getConfigValue();

            // Load the multiplier setting
            // Default is 3x
            $multiplier = $this->getDoctrine()
                ->getRepository(Config::class)
                ->findConfigSettingByName('multiplier')
                ->getConfigValue();

            // Get all deposits from today
            $depositsFromToday = $this->getDoctrine()
                ->getRepository(Deposit::class)
                ->findDepositsByAccountNumberFromToday($account->getAccountNumber());

            if (!empty($depositsFromToday)) {
                // Sum up the deposits
                $todaysDepositTotal = array_reduce($depositsFromToday, function($carry, $deposit) {
                    $carry += $deposit->getAmount();
                    return $carry;
                }, 0);
            } else {
                $todaysDepositTotal = 0;
            }

            /* US-MA-7:
             * As user with access to magic account, I can only deposit exactly 100kr per day
             */

            if ($amountToDeposit + $todaysDepositTotal > $maxDailyDeposit) {
                $canDeposit = $maxDailyDeposit - ($amountToDeposit + $todaysDepositTotal);
                return $this->json(['errors' => 'You cannot deposit more than ' . $maxDailyDeposit . 'kr per day. You may only deposit ' . $canDeposit . 'kr more today']);
            }

            $availableFunds = $account->getDepositBalance() * $multiplier;
            $delta = $maxAllowed - $availableFunds;

            $amountToDepositAfterMultiplier = $amountToDeposit * $multiplier;

            // If the amount to deposit (multiplied by the multiplier) + the current balance exceeds
            // the maximum allowed, return an error
            if ($amountToDepositAfterMultiplier > $delta) {
                $maxAmountBeforeMultiplier = floor($delta / 3);
                return $this->json(['errors' => ['You can only deposit a maximum of ' . $maxAmountBeforeMultiplier . 'kr']]);
            }

            // Make the deposit - without the multiplier
            $entityManager = $this->getDoctrine()->getManager();
            $deposit = new Deposit();
            $deposit->setAccountNumber($account->getAccountNumber());
            $deposit->setAmount($amountToDeposit);
            $deposit->setDatetime(date('Y-m-d H:i:s', time()));

            $errors = $validator->validate($deposit);
            if (count($errors) > 0) {
                return new Response((string) $errors, 400);
            }

            $entityManager->persist($deposit);
            $entityManager->flush();

            // Update the account deposit balance ONLY
            // Available Balance is always determined at runtime
            $newDepositBalance = $account->getDepositBalance() + $amountToDeposit;
            $account->setDepositBalance($newDepositBalance);
            $entityManager->flush();

            return $this->json(['balance' => $newBalance, 'errors' => []]);

        } else {
            return $this->json(['errors' => ['You must deposit more than 0kr']]);
        }
    }

    /**
     * @Route("/account/payment", name="account_payment", methods={"POST"})
     */

    public function payment() {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /* US-MA-8:
         * As a user with access to a magic account I want to use my magic account as a payment
         * method before 00.00, so that the bar influences me to come early. 
        */
        $currentTime = date('H:i:s', time());

        if ($currentTime > '23:59:59' || $currentTime < '12:00:00') {
            return $this->json(['errors' => 'Magic funds cannot be spent before noon or after midnight.']);
        }

        // Get values from POST data
        $paymentAmount = $request->request->get('amount');
        $vendor_id = $request->request->get('vendor_id');

        // Load up the account and get the balance and promo values
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $userId = $user->getId();

        $entityManager = $this->getDoctrine()->getManager();

        // Use MagicAccountRepository to retrieve the MagicAccount
        // based on the userId column
        // ** Ideally this should be in a service as it will
        // be used for all account related routes
        $account = $this->getDoctrine()
            ->getRepository(MagicAccount::class)
            ->findAccountByUserId($userId);

        // Load the multiplier setting
        // Default is 3x
        $multiplier = $this->getDoctrine()
            ->getRepository(Config::class)
            ->findConfigSettingByName('multiplier')
            ->getConfigValue();

        // If not enough funds available, return an error
        // telling the user to add funds
        $balance = $account->getDepositBalance() * $multiplier;
        $promo = $account->getPromoValue();

        if (($balance + $promo) < $paymentAmount) {
            return $this->json(['errors' => ['Not enough funds available. Please add funds.']]);
        }

        // If promo money exists, use that first
        // then remaining amount to be paid is deducted
        // from the account balance
        $leftToPay = $paymentAmount;

        if ($promo > 0) {
            if ($leftToPay >= $promo) {
                // use up promo money, then use balance
                $leftToPay -= $promo;
                $account->setPromoValue(0);
            } elseif ($leftToPay < $promo) {
                // use promo money only
                $account->setPromoValue($promo - $leftToPay);
                $leftToPay = 0;
            }
        }
        
        // Make payment with balance
        if ($leftToPay > 0) {
            $account->setDepositBalance($account->getDepositBalance() - ($leftToPay / $multiplier));
        }

        // Insert a payment record for the amount to be paid
        // and the vendorId
        // ** $payment.status will be set by payment processing microservice
        $payment = new Payment();
        $payment->setAmount($paymentAmount);
        $payment->setAccountNumber($account->getAccountNumber());
        $payment->setVendorId($vendor_id);
        $payment->setDatetime(date('Y-m-d H:i:s', time()));

        $errors = $validator->validate($payment);
        if (count($errors) > 0) {
            return new Response((string) $errors, 400);
        }

        // Persist the payment record and update the user's account
        $entityManager->persist($payment);
        $entityManager->flush();

    }

    /**
     * @Route("/account/withdraw", name="account_withdraw", methods={"POST"})
     */

    public function withdraw() {
        /* US-MA-5:
        * As a user I should be able to withdraw my remaining amount on my Deposited-Amount
        * account, such that I will never lose any money if I stop using the app. Payout should only
        * include money that I deposited myself.
        */
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Get values from POST data
        $withdrawAmount = $request->request->get('amount');

        // Load up the account and get the balance and promo values
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $userId = $user->getId();

        $entityManager = $this->getDoctrine()->getManager();

        // Use MagicAccountRepository to retrieve the MagicAccount
        // based on the userId column
        // ** Ideally this should be in a service as it will
        // be used for all account related routes
        $account = $this->getDoctrine()
            ->getRepository(MagicAccount::class)
            ->findAccountByUserId($userId);

        // Can only withdraw funds deposited,
        // not from the balance or promo columns
        $balance = $account->getDepositBalance();

        if ($withdrawAmount > $balance) {
            return $this->json(['errors' => 'You only have ' . $balance . ' available to withdraw.']);
        }

        $newBalance = $balance - $withdrawAmount;
        $account->setDepositBalance($newBalance);
        $entityManager->flush();

        return $this->json(['balance' => $newBalance, 'errors' => []]);
    }
}