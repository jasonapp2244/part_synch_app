<?php

namespace App\Mail;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Every service request email. The same subject/body pair is stored as the
 * recipient's in-app notification, so the two never say different things.
 *
 * Events: requested, accepted, declined, in_progress, completed, cancelled,
 * paid.
 */
class ServiceRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ServiceRequest $request, public string $event, public string $audience)
    {
    }

    /**
     * @param  string  $to  'customer' | 'vendor' — who is reading it
     * @return array{0: string, 1: string}  [subject, body]
     */
    public static function summary(ServiceRequest $request, string $event, string $to): array
    {
        $number = $request->request_number;
        $title = $request->title;
        $customer = $request->customer?->name ?: 'A customer';
        $vendor = $request->vendor?->sellerName() ?: 'The seller';
        $when = $request->scheduled_at?->format('M j, Y \a\t H:i');
        $by = match ($request->cancelled_by) {
            'customer' => $to === 'customer' ? 'You' : $customer,
            'vendor' => $to === 'vendor' ? 'You' : $vendor,
            'admin' => 'Part Synch support',
            default => 'It',
        };
        $reason = fn (?string $text) => $text ? ": {$text}" : '.';

        return match ($event) {
            'requested' => [
                'New service request',
                "{$customer} requested \"{$title}\" ({$number}). Open Service requests in the app to accept or decline it.",
            ],
            'accepted' => [
                'Service request accepted',
                "{$vendor} accepted your request for \"{$title}\" ({$number})" . ($when ? " and booked it for {$when}." : '.'),
            ],
            'declined' => [
                'Service request declined',
                "{$vendor} could not take your request for \"{$title}\" ({$number})" . $reason($request->decline_reason),
            ],
            'in_progress' => [
                'Service started',
                "{$vendor} has started \"{$title}\" ({$number}).",
            ],
            'completed' => [
                'Service completed',
                "{$vendor} marked \"{$title}\" ({$number}) as completed.",
            ],
            'paid' => [
                'Service payment received',
                "{$vendor} recorded your payment for \"{$title}\" ({$number}).",
            ],
            'cancelled' => [
                'Service request cancelled',
                "{$by} cancelled the request for \"{$title}\" ({$number})" . $reason($request->cancel_reason),
            ],
            default => ['Service request updated', "Your service request {$number} was updated."],
        };
    }

    public function build()
    {
        [$subject, $body] = self::summary($this->request, $this->event, $this->audience);

        return $this->subject($subject)->view('Mails.service_request', [
            'subject' => $subject,
            'body' => $body,
            'serviceRequest' => $this->request,
            'appName' => config('app.name', 'Part Synch'),
        ]);
    }
}
