<?php

namespace App\Services;

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\{
    Payer, Item, ItemList, Amount, Transaction,
    RedirectUrls, Payment, PaymentExecution
};

class PaypalService
{
    protected $apiContext;

    public function __construct()
    {
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                config('services.paypal.client_id'),
                config('services.paypal.secret')
            )
        );

        $this->apiContext->setConfig([
            'mode' => config('services.paypal.mode') // 'sandbox' or 'live'
        ]);
    }

    public function createPaypalPayment($amount, $currency)
    {
        try {
            // 1. Payer
            $payer = new Payer();
            $payer->setPaymentMethod("paypal");

            // 2. Item
            $item = new Item();
            $item->setName("Order Payment")
                 ->setCurrency($currency)
                 ->setQuantity(1)
                 ->setPrice(number_format($amount, 2, '.', ''));

            $itemList = new ItemList();
            $itemList->setItems([$item]); // ✅ must be array of Item objects

            // 3. Amount
            $amt = new Amount();
            $amt->setCurrency($currency)
                ->setTotal(number_format($amount, 2, '.', ''));

            // 4. Transaction
            $transaction = new Transaction();
            $transaction->setAmount($amt)
                        ->setItemList($itemList)
                        ->setDescription("Order Payment");

            // 5. Redirect URLs
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl(route('paypal.success')) // use named route if possible
                         ->setCancelUrl(route('paypal.cancel'));


                         // 5. Redirect URLs
                    
            // 6. Payment
            $payment = new Payment();
            $payment->setIntent("sale")
                    ->setPayer($payer)
                    ->setTransactions([$transaction]) // ✅ array of Transaction objects
                    ->setRedirectUrls($redirectUrls);

            $payment->create($this->apiContext);

            return $payment->getApprovalLink();

        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            logger()->error('PayPal Connection Error', ['data' => json_decode($e->getData(), true)]);
            throw $e;
        } catch (\Exception $e) {
            logger()->error('PayPal Payment Creation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function verifyPaypalPayment($paymentId, $payerId)
    {
        try {
            $payment = Payment::get($paymentId, $this->apiContext);
            $execution = new PaymentExecution();
            $execution->setPayerId($payerId);
            return $payment->execute($execution, $this->apiContext);
        } catch (\Exception $e) {
            logger()->error('PayPal Payment Verification Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
