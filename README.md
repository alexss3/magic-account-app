# Magic Account App

This repo contains psuedo-code for two Symfony controllers that handle admin and user account related operations in the Magic Account app.

## Database Schema

The *schema-uml.pdf* contains a sample database schema for either MySQL or PostgreSQL to support the user stories of the application.

## Definitions

**Magic Account**
A general term for the account you have in which your money is deposited. The same as
"account" in a normal bank.

**Multiplied-Amount**
The amount in your Magic Account after any possible multiplication (as of now it is 3x) has
taken place.

**Deposited-Amount**
The amount of actual money you have deposited to the system. (If you now deposit 100 kr
Deposited Amount, you will get 300 kr Multiplied-Amount).

**Promotion money**
Promotion money can be spent like any other money on the magic account. Promotion money should be used before any other money deposited on the magic account.
Promotion money cannot be withdrawn.

## User Stories

**US-MA-1: Magic-Account**
As a user with access to a magic account, I want to have an account holding money that I
can spend in the bars registered in the app.

**US-MA-2: Deposit money**
As a user with access to a magic account, I want to be able to deposit money to my account
using a credit card payment method, such that I can use them on bars. If I do not have
access, I should not be able to deposit money.

**US-MA-3: Multiplied-Amount**
As a user with access to a magic account, I want to get all my deposits multiplied by a
multiplication factor (3x right now) when getting into my account, such that I get a benefit from spending the money here.

**US-MA-4: Promotion money add**
As an admin I want to add promotion money to a magic account such that I can motivate
users to get started

**US-MA-5: Deposited money payout**
As a user I should be able to withdraw my remaining amount on my Deposited-Amount
account, such that I will never lose any money if I stop using the app. Payout should only
include money that I deposited myself.

**US-MA-6: Maximum deposit balance**
As a user i can maximum have a balance of Deposited-Amount equal to 500kr

**US-MA-7: User deposit**
As user with access to magic account, I can only deposit exactly 100kr per day

**US-MA-8: Use magic account before 00.00**
As a user with access to a magic account I want to use my magic account as a payment
method before 00.00, so that the bar influences me to come early.

**US-MA-9: View available Deposited-Amount and Multiplied-Amount**
As a user I want to see an overview of my Magic account balances, separated into
promotion money, multiplied amount and deposited amount, such that I know what I have
available for spending or payout.

## Test Cases

### Account

1. A user loads their dashboard. Expect: Values for deposited balance, balance after multiplier, and promo balance should be shown.

### Deposits

1. A user tries to deposit more than the maximum account balance setting. Expect: Error
2. A user tries to deposit more than the maximum daily deposit limit in one day. Expect: Error
3. A user tries to deposit an amount within the daily limit and below the maximum allowed. Expect: a success message and updated balance.

### Payments

1. A user makes a payment at 01:00. Expect: Payment denied since it outside of allowed hours.
2. A user makes a payment at 23:30. Expect: Payment processed so long as funds are available.
3. A user makes a payment of 50kr with a promo balance of 100kr. Expect: 50kr is subtracted from promo balance but not from deposited balance.
4. A user makes a payment of 200kr with a promo balance of 100kr and deposit balance of 100kr. Expect: Promo balance becomes 0, only 33kr subtracted from deposit balance (100 / 3x = 33kr).
5. A user tries to make a payment of 300kr with a deposit balance of 50kr. Expect: Error since available funds is only 150kr (50 * 3x).

### Withdraws

1. A user tries to withdraw 100kr when the available funds shows 200kr. Expect: Error since deposit balance is only 66kr (66 * 3x = ~200). Can only withdraw what was deposited.
2. A user tries to withdraw 50kr when the available funds shows 150kr. Expect: Success (50kr * 3x = 150). Deposit balance and available funds will be 0.

### Promotions

1. An admin user adds a promotion for 100kr. Expect: A record is stored for the promotion and all accounts receive the 100kr in their promo balance.

### Settings

1. An admin user updates the multiplier to 2x instead of 3x. Expect: setting updated, all magic accounts show available funds using the new multiplier.
2. An admin user tries to set the multiplier to 0. Expect: the multiplier will be set to 1x as a fail safe.

## Design Challenges/Concerns

Deposits should have the multiplier stored in the record.  If the multiplier changes, only new deposits should use the new multiplier.
