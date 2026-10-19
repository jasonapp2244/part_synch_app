<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a vendor their business account was approved (they can now sign in to
 * the app) or that the approval was withdrawn. The same subject/body pair is
 * stored as the vendor's in-app notification.
 */
class VendorApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $vendor, public bool $approved)
    {
    }

    /**
     * @return array{0: string, 1: string}  [subject, body]
     */
    public static function summary(bool $approved): array
    {
        $app = config('app.name', 'Part Synch');

        return $approved
            ? ["Your {$app} business account is approved", "Your business account has been approved. You can now sign in to the {$app} app with the email and password you registered with and start listing your products."]
            : ["Your {$app} business account is on hold", "Your business account approval has been withdrawn, so you can no longer sign in as a vendor. Please contact support if you think this is a mistake."];
    }

    public function build()
    {
        [$subject, $body] = self::summary($this->approved);

        return $this->subject($subject)->view('Mails.vendor_approval', [
            'subject' => $subject,
            'body' => $body,
            'name' => trim($this->vendor->first_name . ' ' . $this->vendor->last_name) ?: $this->vendor->email,
            'appName' => config('app.name', 'Part Synch'),
        ]);
    }
}
