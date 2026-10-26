<?php

namespace App\Mail;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public Quotation $quotation;
    public ?string $customSubject;
    public ?string $customMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(Quotation $quotation, ?string $customSubject = null, ?string $customMessage = null)
    {
        $this->quotation = $quotation->load(['items', 'lead', 'creator']);
        $this->customSubject = $customSubject;
        $this->customMessage = $customMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        if ($this->customSubject) {
            $subject = $this->customSubject;
        } else {
            $cleanSubj = trim(preg_replace('/^Official Vehicle Quotation\s*[–-]?\s*/i', '', $this->quotation->subject ?? ''));
            if ($cleanSubj) {
                $subject = "Official Quotation: {$cleanSubj} – Defence Autolink [Ref: {$this->quotation->quotation_number}]";
            } else {
                $subject = "Official Vehicle Quotation – Defence Autolink [Ref: {$this->quotation->quotation_number}]";
            }
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.quotation',
            with: [
                'quotation' => $this->quotation,
                'customMessage' => $this->customMessage,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        try {
            $pdf = Pdf::loadView('pdf.quotation', ['quotation' => $this->quotation]);
            $pdfContent = $pdf->output();

            return [
                Attachment::fromData(fn () => $pdfContent, "Quotation-{$this->quotation->quotation_number}.pdf")
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            \Log::error('PDF generation error for Quotation email attachment: ' . $e->getMessage());
            return [];
        }
    }
}
