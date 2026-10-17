<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KycDocumentResource;
use App\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KycDocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = Auth::user()->merchant->kycDocuments()->latest()->get();

        return KycDocumentResource::collection($documents);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'document_type' => ['required', 'in:national_id,business_license,tin_certificate,other'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $document = KycDocument::create([
            'merchant_id' => Auth::user()->merchant_id,
            'document_type' => $data['document_type'],
            'status' => KycDocument::STATUS_PENDING,
        ]);

        $document->addMedia($request->file('file')->getRealPath())
            ->usingFileName($request->file('file')->getClientOriginalName())
            ->toMediaCollection('file');

        return new KycDocumentResource($document->fresh());
    }
}
