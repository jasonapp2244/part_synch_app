<?php

namespace App\Http\Controllers;

use App\Models\VendorBoost;
use App\Services\BoostWorkflow;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;

/**
 * Stripe events for the in-app boost payment. The app also confirms the
 * payment itself; this covers an app closed before it could, and both paths
 * end in the same idempotent BoostWorkflow::markPaidByStripe().
 */
class StripeWebhookController extends Controller
{
    public function __construct(private StripeService $stripe, private BoostWorkflow $workflow)
    {
    }

    public function handle(Request $request)
    {
        try {
            $event = $this->stripe->webhookEvent($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $intent = $event->data->object ?? null;
        if (! $intent instanceof PaymentIntent) {
            return response()->json(['received' => true]);
        }

        $boost = VendorBoost::find((int) ($intent->metadata['vendor_boost_id'] ?? 0));
        if (! $boost) {
            return response()->json(['received' => true]);
        }

        if ($event->type === 'payment_intent.succeeded') {
            try {
                $this->workflow->markPaidByStripe($boost, $intent);
            } catch (ValidationException $e) {
                Log::error('Stripe webhook payment did not match its boost', ['vendor_boost_id' => $boost->id, 'payment_intent' => $intent->id]);
            }
        } elseif ($event->type === 'payment_intent.payment_failed') {
            Log::info('Boost card payment failed', [
                'vendor_boost_id' => $boost->id,
                'payment_intent' => $intent->id,
                'reason' => $intent->last_payment_error?->message,
            ]);
        }

        // 200 for anything handled or deliberately ignored, so Stripe stops retrying.
        return response()->json(['received' => true]);
    }
}
