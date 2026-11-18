<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VehicleCountSurveyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $report,
        private readonly string $pdfContent,
        private readonly string $pdfFilename
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('VehiSights Vehicle Count Survey — '.$this->report['dateRangeLabel'])
            ->view('emails.vehicle-count-survey')
            ->attachData($this->pdfContent, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
