<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductOutOfStockMail extends Mailable
{
    use Queueable, SerializesModels;

    public $product;
    public $user;
    public $type;

    /**
     * Create a new message instance.
     *
     * @param  mixed  $product
     * @param  mixed  $user (user or vendor)
     * @param  string $type ('customer' or 'vendor')
     */
    public function __construct($product,$user,$type)
    {
        $this->product = $product;
        $this->user = $user;
        $this->type = $type;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Product Out of Stock')
                    ->view('Mails.product_out_of_stock');
    }
}
