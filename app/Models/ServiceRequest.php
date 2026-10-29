<?php

namespace App\Models;

use App\Mail\ServiceRequestMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * A customer asking a vendor for one of their services.
 *
 * The service's equivalent of an order: paid outside the app, moved on by the
 * vendor, tracked (and if need be cancelled) by the admin. Every change is
 * written to the other side's in-app notifications and emailed, best effort.
 */
class ServiceRequest extends Model
{
    public const STATUS_LABELS = [
        'requested' => 'Requested',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Where the vendor may move a request from each status. Forward only;
     * cancelling stays possible until the work has started.
     */
    public const VENDOR_FLOW = [
        'requested' => ['accepted', 'declined', 'cancelled'],
        'accepted' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        'completed' => [],
        'declined' => [],
        'cancelled' => [],
    ];

    /** Still waiting on someone: what the admin badge counts. */
    public const OPEN = ['requested', 'accepted', 'in_progress'];

    /** The customer may withdraw until the vendor has started. */
    public const CUSTOMER_CANCELLABLE = ['requested', 'accepted'];

    protected $fillable = [
        'request_number',
        'service_id',
        'customer_id',
        'vendor_id',
        'title',
        'price',
        'quoted_price',
        'preferred_date',
        'scheduled_at',
        'address',
        'phone',
        'note',
        'status',
        'decline_reason',
        'cancelled_by',
        'cancel_reason',
        'paid_outside',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'scheduled_at' => 'datetime',
        'paid_outside' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public static function newNumber(): string
    {
        do {
            $number = 'SRV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (static::where('request_number', $number)->exists());

        return $number;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** The shape both apps read. Names only: no emails leave the server. */
    public function toApi(): array
    {
        $this->loadMissing(['customer:id,first_name,last_name', 'vendor:id,first_name,last_name,business_name', 'service:id,product_id']);

        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'service_id' => $this->service_id,
            'product_id' => $this->service?->product_id,
            'title' => $this->title,
            'price' => $this->price,
            'quoted_price' => $this->quoted_price,
            'preferred_date' => $this->preferred_date?->format('Y-m-d'),
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i'),
            'address' => $this->address,
            'phone' => $this->phone,
            'note' => $this->note,
            'decline_reason' => $this->decline_reason,
            'cancelled_by' => $this->cancelled_by,
            'cancel_reason' => $this->cancel_reason,
            'paid_outside' => (bool) $this->paid_outside,
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null,
            'vendor' => $this->vendor ? ['id' => $this->vendor->id, 'name' => $this->vendor->sellerName()] : null,
        ];
    }

    /**
     * Tell one side what happened: an in-app notification, then an email.
     * Neither may fail the change that triggered it.
     *
     * @param  string  $to  'customer' | 'vendor'
     */
    public function notifyParty(string $event, string $to): void
    {
        $this->loadMissing(['customer', 'vendor']);
        [$subject, $body] = ServiceRequestMail::summary($this, $event, $to);
        $recipient = $to === 'vendor' ? $this->vendor : $this->customer;

        try {
            Notification::create([
                ($to === 'vendor' ? 'vendor_id' : 'user_id') => $to === 'vendor' ? $this->vendor_id : $this->customer_id,
                'service_request_id' => $this->id,
                'email_subject' => $subject,
                'email_body' => $body,
                'status' => 'sent',
            ]);
        } catch (\Throwable $e) {
            Log::error('Service request notification failed', ['service_request_id' => $this->id, 'error' => $e->getMessage()]);
        }

        if ($recipient?->email) {
            try {
                Mail::to($recipient->email)->send(new ServiceRequestMail($this, $event, $to));
            } catch (\Throwable $e) {
                Log::error('Service request email failed', ['service_request_id' => $this->id, 'event' => $event, 'error' => $e->getMessage()]);
            }
        }
    }
}
