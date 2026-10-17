<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePublicController extends Controller
{
    public function download(Invoice $invoice)
    {
        $merchant = $invoice->merchant;

        $pdf = Pdf::loadView('exports.invoice-pdf', [
            'invoice' => $invoice->load(['items', 'customer', 'payments.paymentMethod']),
            'merchant' => $merchant,
            'logoDataUri' => $merchant->logoDataUri(),
            'brandColor' => $merchant->brandColor(),
            'includeImages' => false,
            'itemImages' => [],
            'qrDataUri' => $invoice->qrDataUri(),
        ])->setPaper('a4', 'portrait');

        $filename = ($invoice->isDraft() ? 'proforma-' : 'invoice-').$invoice->number.'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
