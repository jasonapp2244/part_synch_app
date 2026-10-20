<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\VendorBoost;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * The only Stripe code in the backend: the one-time in-app card payment for a
 * vendor boost. Products and services are paid outside the app.
 *
 * Every method can throw \Stripe\Exception\ApiErrorException; callers turn it
 * into a clean response.
 */
class StripeService
{
    private ?StripeClient $client = null;

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.key')) && filled(config('services.stripe.secret'));
    }

    /**
     * In-app card payment for boosts is the normal flow; the admin Settings
     * switch can pause it (the app then falls back to request-and-approve).
     */
    public function cardPaymentEnabled(): bool
    {
        return $this->isConfigured() && (string) Setting::get('boost_card_payment', '1') === '1';
    }

    public function publishableKey(): ?string
    {
        return config('services.stripe.key');
    }

    /**
     * Amount in the currency's minor unit, as Stripe expects.
     */
    public static function minorUnits(VendorBoost $boost): int
    {
        return (int) round(((float) $boost->amount) * 100);
    }

    public function createBoostPaymentIntent(VendorBoost $boost): PaymentIntent
    {
        $amount = self::minorUnits($boost);

        return $this->client()->paymentIntents->create([
            'amount' => $amount,
            'currency' => strtolower($boost->currency ?: 'usd'),
            'automatic_payment_methods' => ['enabled' => true],
            'description' => "Part Synch boost #{$boost->id}",
            'metadata' => [
                'vendor_boost_id' => (string) $boost->id,
                'vendor_id' => (string) $boost->vendor_id,
            ],
        ], [
            // A retried request for the same boost and price reuses the intent.
            'idempotency_key' => "boost-{$boost->id}-{$amount}",
        ]);
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($id);
    }

    public function cancelPaymentIntent(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->cancel($id);
    }

    public function refund(string $paymentIntentId): Refund
    {
        return $this->client()->refunds->create(['payment_intent' => $paymentIntentId], [
            'idempotency_key' => "refund-{$paymentIntentId}",
        ]);
    }

    /**
     * Verify the Stripe-Signature header and parse the event.
     *
     * @throws \Stripe\Exception\SignatureVerificationException|\UnexpectedValueException
     */
    public function webhookEvent(string $payload, string $signature): Event
    {
        return Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'));
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) config('services.stripe.secret'));
    }
}
