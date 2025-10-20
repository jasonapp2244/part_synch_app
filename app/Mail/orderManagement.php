<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class orderManagement extends Mailable
{
    use Queueable, SerializesModels;

    public $order, $user, $vendor, $recipientType;

    public function __construct($order, $user, $vendor, $recipientType)
    {
        $this->order = $order;
        $this->user = $user;
        $this->vendor = $vendor;
        $this->recipientType = $recipientType;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Status: ' . ucfirst($this->order->order_status),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'Mails.order-management',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
