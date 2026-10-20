<?php

namespace App\Services;

use App\Mail\BoostStatusMail;
use App\Models\BoostedProduct;
use App\Models\BoostPackage;
use App\Models\BoostPosition;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorBoost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

/**
 * Every state change of a boost goes through here so the app API, the admin
 * panel, the Stripe webhook and the scheduler cannot drift apart.
 */
class BoostWorkflow
{
    public function __construct(private BoostCapacity $capacity, private StripeService $stripe)
    {
    }

    public static function price(BoostPackage $package, BoostPosition $position): float
    {
        return round((float) $package->price * (float) $position->price_multiplier, 2);
    }

    /**
     * Create a pending request (from the app, or by an admin on a vendor's
     * behalf). Throws ValidationException with field-keyed messages.
     *
     * @param  array<int>  $productIds
     */
    public function request(User $vendor, BoostPackage $package, BoostPosition $position, array $productIds, ?string $note = null, bool $notify = true): VendorBoost
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if (! $package->status) {
            throw ValidationException::withMessages(['package_id' => 'This package is not available.']);
        }
        if (! $position->status) {
            throw ValidationException::withMessages(['position_id' => 'This position is not available.']);
        }
        if (count($productIds) > $package->product_limit) {
            throw ValidationException::withMessages(['product_ids' => "This package boosts up to {$package->product_limit} product(s)."]);
        }
        if (count($productIds) > $position->display_limit) {
            throw ValidationException::withMessages(['product_ids' => "{$position->name} shows up to {$position->display_limit} product(s) at a time."]);
        }

        $owned = Product::whereIn('id', $productIds)
            ->where('user_id', $vendor->id)
            ->where('status', 'active')
            ->count();
        if ($owned !== count($productIds)) {
            throw ValidationException::withMessages(['product_ids' => 'Choose only your own active products.']);
        }

        // One open boost per product and position: pending, scheduled or live.
        $clash = BoostedProduct::whereIn('product_id', $productIds)
            ->whereHas('vendorBoost', function ($q) use ($position) {
                $q->where('boost_position_id', $position->id)
                    ->where(function ($q) {
                        $q->where('approval_status', 'pending')
                            ->orWhere(function ($q) {
                                $q->where('approval_status', 'approved')
                                    ->where('is_active', true)
                                    ->where('end_date', '>', now());
                            });
                    });
            })
            ->with('product:id,name')
            ->get();
        if ($clash->isNotEmpty()) {
            $names = $clash->pluck('product.name')->filter()->unique()->implode(', ');
            throw ValidationException::withMessages(['product_ids' => "Already requested or boosted in {$position->name}: {$names}."]);
        }

        $boost = DB::transaction(function () use ($vendor, $package, $position, $productIds, $note) {
            $boost = VendorBoost::create([
                'vendor_id' => $vendor->id,
                'package_id' => $package->id,
                'boost_position_id' => $position->id,
                'approval_status' => 'pending',
                'amount' => self::price($package, $position),
                'currency' => $package->currency,
                'payment_status' => 'pending',
                'is_active' => false,
                'vendor_note' => $note ? trim($note) : null,
            ]);

            foreach ($productIds as $productId) {
                BoostedProduct::create([
                    'vendor_boost_id' => $boost->id,
                    'product_id' => $productId,
                    'is_active' => false,
                ]);
            }

            return $boost;
        });

        if ($notify) {
            $this->notifyVendor($boost, 'requested');
            $this->notifyAdmin($boost);
        }

        return $boost;
    }

    /**
     * Record the payment taken outside the app and approve. The boost goes
     * live at $start (now or later); refuses a start that would overfill the
     * position.
     */
    public function approve(VendorBoost $boost, User $admin, array $payment, Carbon $start): VendorBoost
    {
        if ($boost->approval_status !== 'pending') {
            throw ValidationException::withMessages(['boost' => 'Only pending requests can be approved.']);
        }

        $boost->loadMissing(['package', 'position', 'boostedProducts']);
        $days = (int) $boost->package->duration_days;
        $count = $boost->boostedProducts->count();
        $start = $start->copy()->max(now());

        if (! $this->capacity->fits($boost->position, $count, $start, $days)) {
            $next = $this->capacity->nextAvailableStart($boost->position, $count, $days, $start);
            throw ValidationException::withMessages([
                'start_date' => "{$boost->position->name} is full then. Earliest start: " . ($next ? $next->format('M j, Y H:i') : 'never (too many products for this position)') . '.',
            ]);
        }

        return $this->activate($boost, $admin, $payment, $start);
    }

    /**
     * Record a succeeded in-app card payment and approve the boost. It goes
     * live now, or at the position's next free slot when it is full. Safe to
     * call twice (the app's confirm and the webhook both arrive): a boost
     * already paid is returned unchanged.
     */
    public function markPaidByStripe(VendorBoost $boost, PaymentIntent $intent): VendorBoost
    {
        if ($intent->status !== 'succeeded'
            || (string) ($intent->metadata['vendor_boost_id'] ?? '') !== (string) $boost->id
            || (int) $intent->amount_received !== StripeService::minorUnits($boost)
            || strtolower((string) $intent->currency) !== strtolower($boost->currency ?: 'usd')) {
            throw ValidationException::withMessages(['payment' => 'This payment does not match the boost.']);
        }

        // The row lock makes the check and the write one step, so a confirm
        // racing the webhook cannot approve (or notify) twice.
        [$boost, $event] = DB::transaction(function () use ($boost, $intent) {
            $boost = VendorBoost::whereKey($boost->id)->lockForUpdate()->firstOrFail();

            if ($boost->payment_status === 'success') {
                return [$boost, null];
            }

            $boost->loadMissing(['package', 'position', 'boostedProducts']);
            $days = (int) $boost->package->duration_days;
            $count = $boost->boostedProducts->count();
            $start = $this->capacity->fits($boost->position, $count, now(), $days)
                ? now()
                : $this->capacity->nextAvailableStart($boost->position, $count, $days, now());

            // Paid, but it can no longer go live (withdrawn or cancelled while
            // the vendor was paying, or no slot will ever fit): keep the money
            // on record and leave the boost for an admin to resolve or refund.
            if ($boost->approval_status !== 'pending' || ! $start) {
                $boost->update([
                    'payment_status' => 'success',
                    'payment_method' => 'stripe',
                    'payment_reference' => $intent->id,
                    'transaction_id' => $intent->id,
                    'paid_at' => now(),
                ]);
                Log::warning('Stripe-paid boost could not be activated', ['vendor_boost_id' => $boost->id, 'approval_status' => $boost->approval_status]);

                return [$boost, 'admin'];
            }

            $boost = $this->activate($boost, null, [
                'method' => 'stripe',
                'reference' => $intent->id,
                'amount' => $intent->amount_received / 100,
            ], $start, false);

            return [$boost, $boost->status === 'scheduled' ? 'scheduled' : 'approved'];
        });

        if ($event === 'admin') {
            $this->notifyAdmin($boost, 'admin_paid_not_live');
        } elseif ($event) {
            $this->notifyVendor($boost, $event);
        }

        return $boost;
    }

    /**
     * Shared by admin approval and card payment. $admin is null when the boost
     * was approved by its own payment.
     */
    private function activate(VendorBoost $boost, ?User $admin, array $payment, Carbon $start, bool $notify = true): VendorBoost
    {
        $days = (int) $boost->package->duration_days;
        $end = $start->copy()->addDays($days);

        DB::transaction(function () use ($boost, $admin, $payment, $start, $end) {
            $boost->update([
                'approval_status' => 'approved',
                'payment_status' => 'success',
                'payment_method' => $payment['method'],
                'payment_reference' => $payment['reference'] ?? null,
                'transaction_id' => $payment['method'] === 'stripe' ? $payment['reference'] : $boost->transaction_id,
                'amount' => $payment['amount'],
                'paid_at' => now(),
                'admin_note' => $payment['note'] ?? null,
                'approved_by' => $admin?->id,
                'approved_at' => now(),
                'start_date' => $start,
                'end_date' => $end,
                'is_active' => true,
                'live_notified_at' => $start->lte(now()) ? now() : null,
            ]);

            $boost->boostedProducts()->update([
                'boost_start' => $start,
                'boost_end' => $end,
                'is_active' => true,
            ]);

            Product::whereIn('id', $boost->boostedProducts->pluck('product_id'))->update([
                'is_top' => true,
                'top_start_date' => $start,
                'top_expire_date' => $end,
            ]);
        });

        $boost->refresh();
        if ($notify) {
            $this->notifyVendor($boost, $boost->status === 'scheduled' ? 'scheduled' : 'approved');
        }

        return $boost;
    }

    public function reject(VendorBoost $boost, User $admin, string $reason): VendorBoost
    {
        if ($boost->approval_status !== 'pending') {
            throw ValidationException::withMessages(['boost' => 'Only pending requests can be rejected.']);
        }

        $boost->update([
            'approval_status' => 'rejected',
            'payment_status' => 'failed',
            'rejection_reason' => $reason,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'is_active' => false,
        ]);

        $this->notifyVendor($boost, 'rejected');

        return $boost;
    }

    /**
     * Cancel a pending, scheduled or live boost and free its slots.
     * $byVendor: the vendor withdrew their own pending request.
     */
    public function cancel(VendorBoost $boost, ?string $reason = null, bool $refunded = false, bool $byVendor = false): VendorBoost
    {
        if (! in_array($boost->status, ['pending', 'scheduled', 'active'], true)) {
            throw ValidationException::withMessages(['boost' => 'This boost is already closed.']);
        }

        // A card payment taken in the app is refunded to the card first; the
        // boost is only marked refunded once Stripe has accepted the refund.
        if ($refunded && $boost->payment_status === 'success' && $boost->payment_method === 'stripe' && $boost->transaction_id) {
            try {
                $this->stripe->refund($boost->transaction_id);
            } catch (ApiErrorException $e) {
                Log::error('Stripe refund failed', ['vendor_boost_id' => $boost->id, 'error' => $e->getMessage()]);
                throw ValidationException::withMessages(['refunded' => 'Stripe did not accept the refund: ' . $e->getMessage()]);
            }
        }

        // An unpaid request may have a card payment open in the app; close it
        // so it can no longer be paid.
        if ($boost->payment_status !== 'success' && str_starts_with((string) $boost->transaction_id, 'pi_')) {
            try {
                $this->stripe->cancelPaymentIntent($boost->transaction_id);
            } catch (ApiErrorException $e) {
                Log::warning('Stripe payment cancel failed', ['vendor_boost_id' => $boost->id, 'error' => $e->getMessage()]);
            }
        }

        DB::transaction(function () use ($boost, $reason, $refunded) {
            $wasPaid = $boost->payment_status === 'success';

            $boost->update([
                'approval_status' => 'cancelled',
                'is_active' => false,
                'payment_status' => $wasPaid ? ($refunded ? 'refunded' : 'success') : 'failed',
                'rejection_reason' => $reason,
            ]);
            $boost->boostedProducts()->update(['is_active' => false]);

            $this->clearTopFlags($boost->boostedProducts()->pluck('product_id')->all());
        });

        if (! $byVendor) {
            $this->notifyVendor($boost, 'cancelled');
        }

        return $boost;
    }

    /**
     * Drop the legacy is_top flag from products that no longer have an
     * approved boost running or scheduled.
     */
    public function clearTopFlags(array $productIds): void
    {
        foreach ($productIds as $productId) {
            $stillBoosted = BoostedProduct::where('product_id', $productId)
                ->where('is_active', true)
                ->where('boost_end', '>', now())
                ->whereHas('vendorBoost', fn ($q) => $q->approvedAndPaid())
                ->exists();

            if (! $stillBoosted) {
                Product::where('id', $productId)->update([
                    'is_top' => false,
                    'top_start_date' => null,
                    'top_expire_date' => null,
                ]);
            }
        }
    }

    /**
     * In-app notification (read by /vendor/notifications) plus email.
     */
    public function notifyVendor(VendorBoost $boost, string $event): void
    {
        $boost->loadMissing(['vendor', 'package', 'position', 'boostedProducts.product']);
        [$subject, $body] = BoostStatusMail::summary($boost, $event);

        try {
            Notification::create([
                'vendor_id' => $boost->vendor_id,
                'email_subject' => $subject,
                'email_body' => $body,
                'status' => 'sent',
            ]);
        } catch (\Throwable $e) {
            Log::error('Boost notification row failed', ['vendor_boost_id' => $boost->id, 'error' => $e->getMessage()]);
        }

        if ($boost->vendor?->email) {
            try {
                Mail::to($boost->vendor->email)->send(new BoostStatusMail($boost, $event));
            } catch (\Throwable $e) {
                Log::error('Boost email failed', ['vendor_boost_id' => $boost->id, 'event' => $event, 'error' => $e->getMessage()]);
            }
        }
    }

    public function notifyAdmin(VendorBoost $boost, string $event = 'admin_new_request'): void
    {
        // config(), not env(): deploy.sh caches config, after which env() is null.
        $adminEmail = config('app.admin_email');
        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $boost->loadMissing(['vendor', 'package', 'position', 'boostedProducts.product']);
            Mail::to($adminEmail)->send(new BoostStatusMail($boost, $event));
        } catch (\Throwable $e) {
            Log::error('Boost admin email failed', ['vendor_boost_id' => $boost->id, 'error' => $e->getMessage()]);
        }
    }
}
