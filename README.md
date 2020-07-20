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
