<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerApprovalEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $requisition;
    public $approveLink;
    public $rejectLink;

    public function __construct($requisition)
    {
        $this->requisition = $requisition;
        
        $baseUrl = env('APP_URL', 'http://127.0.0.1:8000');
        $id = $requisition->_id ?? $requisition->id;
        $token = $requisition->approval_token;

        $this->approveLink = "{$baseUrl}/api/requisitions/{$id}/email-approval?token={$token}&action=approve";
        $this->rejectLink = "{$baseUrl}/api/requisitions/{$id}/email-approval?token={$token}&action=reject";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Purchase Requisition #' . substr((string)($this->requisition->_id ?? $this->requisition->id), -6),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.approval',
        );
    }
}