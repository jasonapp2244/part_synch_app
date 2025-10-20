<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $viewName;
    public $subject;

    public function __construct($data, $viewName, $subject)
    {
        $this->data = $data;
        $this->viewName = $viewName;
        $this->subject = $subject;
    }
    public function build()
    {
        return $this->subject($this->subject)
                    ->view($this->viewName, ['data' => $this->data]);
    }
}
