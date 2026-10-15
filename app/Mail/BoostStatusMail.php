<?php

namespace App\Mail;

use App\Models\VendorBoost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Every boost email except expiry (BoostExpiryMail). The same subject/body
 * pair is stored as the vendor's in-app notification, so the two never say
 * different things.
 *
 * Events: requested, admin_new_request, approved, scheduled, live,
 * ending_soon, rejected, cancelled.
 */
class BoostStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 180, 300];

    public function __construct(public VendorBoost $boost, public string $event)
    {
    }

    /**
     * @return array{0: string, 1: string}  [subject, body]
     */
    public static function summary(VendorBoost $boost, string $event): array
    {
        $package = $boost->package?->name ?? 'Boost';
        $position = $boost->position?->name ?? 'the home page';
        $count = $boost->boostedProducts?->count() ?? 0;
        $products = $count === 1 ? '1 product' : "{$count} products";
        $start = $boost->start_date?->format('M j, Y');
        $end = $boost->end_date?->format('M j, Y');
        $vendor = trim(($boost->vendor?->first_name ?? '') . ' ' . ($boost->vendor?->last_name ?? '')) ?: ($boost->vendor?->email ?? 'A vendor');
        $amount = strtoupper($boost->currency ?? 'usd') . ' ' . number_format((float) $boost->amount, 2);

        return match ($event) {
            'requested' => [
                'Boost request received',
                "We received your request to boost {$products} in {$position} ({$package}). Our team will contact you by email to complete it.",
            ],
            'admin_new_request' => [
                "New boost request: {$vendor} - {$package}",
                "{$vendor} requested {$package} in {$position} for {$products}. Quoted amount: {$amount}. Send payment details, then approve it in the admin panel under Boost Requests.",
            ],
            'approved' => [
                'Your boost is live',
                "Your {$package} boost is live in {$position} for {$products} until {$end}.",
            ],
            'scheduled' => [
                'Your boost is scheduled',
                "Your {$package} boost is approved. {$position} is fully booked until then, so it starts on {$start} and runs until {$end}.",
            ],
            'live' => [
                'Your boost has started',
                "Your {$package} boost is now live in {$position} for {$products} until {$end}.",
            ],
            'ending_soon' => [
                'Your boost ends soon',
                "Your {$package} boost in {$position} ends on {$end}. Request a new boost in the app to keep your products featured.",
            ],
            'rejected' => [
                'Boost request not approved',
                'Your boost request was not approved' . ($boost->rejection_reason ? ": {$boost->rejection_reason}" : '.'),
            ],
            'cancelled' => [
                'Boost cancelled',
                "Your {$package} boost in {$position} was cancelled" . ($boost->rejection_reason ? ": {$boost->rejection_reason}" : '.'),
            ],
            default => ['Boost update', "Your {$package} boost was updated."],
        };
    }

    public function build()
    {
        [$subject, $body] = self::summary($this->boost, $this->event);

        return $this->subject($subject)->view('Mails.boost_status', [
            'subject' => $subject,
            'body' => $body,
            'boost' => $this->boost,
            'forAdmin' => $this->event === 'admin_new_request',
            'appName' => config('app.name', 'Part Synch'),
        ]);
    }
}
