<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;

class StripeService
{
    protected function setApiKey()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPaymentIntent($amount, $currency)
    {
        $this->setApiKey();

        //
        //

        $pi = PaymentIntent::create([
            'amount'   => $amount * 100,
            'currency' => $currency,
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ]
        ]);
        return $pi->client_secret;
    }

    public function verifyPaymentIntent($token)
    {
        $this->setApiKey();
        $pi = PaymentIntent::retrieve($token);
        return $pi->confirm();
    }
}
