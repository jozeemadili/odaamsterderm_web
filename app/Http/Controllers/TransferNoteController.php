<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use Illuminate\Support\Facades\URL;

class TransferNoteController extends Controller
{
    /** Signed link, so the app can open and share it without a session. */
    public static function url(StockTransfer $transfer): string
    {
        return URL::signedRoute('transfers.note', $transfer);
    }

    public function __invoke(StockTransfer $transfer)
    {
        $transfer->load(['items', 'merchant', 'fromBranch', 'toBranch', 'sender', 'receiver']);

        return view('receipts.transfer-note', [
            'transfer' => $transfer,
            'merchant' => $transfer->merchant,
        ]);
    }
}
