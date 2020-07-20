<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Promotion;
use App\Entity\Vendor;
use App\Entity\Config;
use App\Entity\MagicAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AdminController extends AbstractController {

    /**
     * @Route("/admin", name="admin_home", methods={"GET"})
     */

    public function home() {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // The following lookups are not scalable,
        // obviously implement some pagination to return
        // n number of records at a time.

        // Get a listing of promos
        $entityManager = $this->getDoctrine()->getManager();
        $promos = $entityManager
            ->getRepository(Promotion::class)
            ->findAll();

        // Get a listing of vendors
        $entityManager = $this->getDoctrine()->getManager();
        $vendors = $entityManager
            ->getRepository(Vendor::class)
            ->findAll();

        return $this->render('admin/dashboard.html.twig', [
            'promos' => $promos,
            'vendors' => $vendors
        ]);
    }

    /**
     * @Route("/admin/promo/add", name="admin_add_promo", methods={"POST"})
     */

    public function add_promo(Request $request, ValidatorInterface $validator) {
        /* US-MA-4:
         * As an admin I want to add promotion money to a magic account such that I can motivate
         * users to get started
         */
        
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get values from POST data
        $amount = $request->request->get('amount');
        $expiration = $request->request->get('expiration');
        
        // Store the promo in the database
        $entityManager = $this->getDoctrine()->getManager();
        $promo = new Promotion();
        $promo->setAmount($amount);
        $promo->setExpiration($expiration);

        $errors = $validator->validate($promo);
        if (count($errors) > 0) {
            return new Response((string) $errors, 400);
        }

        $entityManager->persist($promo);
        $entityManager->flush();

        // Update accounts with the promo money
        // In this scenario it overwrites it, not
        // sure if can be stacked
        $accounts = $entityManager
            ->getRepository(MagicAccount::class)
            ->findAll();

        $accounts->setPromoValue($amount);
        $entityManager->flush();

        return $this->json(['promo' => $promo->getId()]);
    }

    /**
     * @Route("/admin/vendor/add", name="admin_add_vendor", methods={"POST"})
     */

    public function add_vendor(Request $request, ValidatorInterface $validator) {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get values from POST data
        $name = $request->request->get('name');
        
        // Store the vendor in the database
        $entityManager = $this->getDoctrine()->getManager();
        $vendor = new Vendor();
        $vendor->setName($name);

        $errors = $validator->validate($vendor);
        if (count($errors) > 0) {
            return new Response((string) $errors, 400);
        }

        $entityManager->persist($vendor);
        $entityManager->flush();

        return $this->json(['vendor' => $vendor->getId()]);
    }

    /**
     * @Route("/admin/multiplier/update", name="admin_update_multiplier", methods={"PUT"})
     */

    public function update_multiplier(Request $request, ValidatorInterface $validator) {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get values from POST data
        $multiplier = $request->request->get('multiplier');
        $configId = $request->request->get('config_id');

        // Can't have anything less than 1
        // for the multiplier
        if ($multiplier < 1) {
            $multiplier = 1;
        }
        
        // Store the multiplier in the database
        $entityManager = $this->getDoctrine()->getManager();
        $configRow = $entityManager
            ->getRepository(Config::class)
            ->find($configId);

        if (!$configRow) {
            throw $this->createNotFoundException(
                'No config setting found for id ' . $configId
            );
        }

        // Update the multiplier value
        $configRow->setValue($multiplier);
        $entityManager->flush();

        // Return the new value to show in the UI
        return $this->json(['configValue' => $multiplier]);
    }

    /**
     * @Route("/admin/max-balance/update", name="admin_update_max_balance", methods={"PUT"})
     */

    public function update_max_balance(Request $request, ValidatorInterface $validator) {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get values from POST data
        $maxBalance = $request->request->get('max_balance');
        $configId = $request->request->get('config_id');
        
        // Store the value in the database
        $entityManager = $this->getDoctrine()->getManager();
        $configRow = $entityManager
            ->getRepository(Config::class)
            ->find($configId);

        if (!$configRow) {
            throw $this->createNotFoundException(
                'No config setting found for id ' . $configId
            );
        }

        // Update the config value
        $configRow->setValue($maxBalance);
        $entityManager->flush();

        // Return the new value to show in the UI
        return $this->json(['configValue' => $maxBalance]);
    }

    /**
     * @Route("/admin/max-daily/update", name="admin_update_max_daily", methods={"PUT"})
     */

    public function update_max_daily(Request $request, ValidatorInterface $validator) {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get values from POST data
        $maxDaily = $request->request->get('max_daily');
        $configId = $request->request->get('config_id');
        
        // Store the value in the database
        $entityManager = $this->getDoctrine()->getManager();
        $configRow = $entityManager
            ->getRepository(Config::class)
            ->find($configId);

        if (!$configRow) {
            throw $this->createNotFoundException(
                'No config setting found for id ' . $configId
            );
        }

        // Update the config value
        $configRow->setValue($maxDaily);
        $entityManager->flush();

        // Return the new value to show in the UI
        return $this->json(['configValue' => $maxDaily]);
    }

}