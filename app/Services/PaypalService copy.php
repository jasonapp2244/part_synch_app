<?php

namespace App\Services;

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\{Payer, Amount, Transaction, RedirectUrls, Payment, PaymentExecution};

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
        $this->apiContext->setConfig(['mode' => config('services.paypal.mode')]);
    }



    public function createPaypalPayment($amount, $currency)
    {
        $payer = (new Payer())->setPaymentMethod('paypal');
        $amt   = (new Amount())->setTotal($amount)->setCurrency($currency);
        $txn   = (new Transaction())->setAmount($amt)->setDescription('Order Payment');
        $urls  = (new RedirectUrls())
            ->setReturnUrl(url('api/user/payment/paypal/success'))
            ->setCancelUrl(url('api/user/payment/paypal/cancel'));


// dd($urls);


        //
        // $urls  = (new RedirectUrls())
        //     ->setReturnUrl(url('api/payment/paypal/success'))
        //     ->setCancelUrl(url('api/payment/paypal/cancel'));

        // $payment = (new Payment())
        //     ->setIntent('sale')
        //     ->setPayer($payer)
        //     ->setTransactions([$txn]) // ✅ this line is correct
        //     ->setRedirectUrls($urls)
        //     ->create($this->apiContext);

        // return $payment->getApprovalLink();
        // //




        $payment = (new Payment())
            ->setIntent('sale')
            ->setPayer($payer)
            ->setTransactions([$txn])
            ->setRedirectUrls($urls)
            ->create($this->apiContext);

            dd($payment);
        return $payment->getApprovalLink();
    }

    public function verifyPaypalPayment($paymentId, $payerId)
    {
        $payment   = Payment::get($paymentId, $this->apiContext);
        $execution = (new PaymentExecution())->setPayerId($payerId);
        return $payment->execute($execution, $this->apiContext);
    }
}
