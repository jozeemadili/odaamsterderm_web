<?php

namespace App\Livewire\Portal\Kyc;

use App\Models\KycDocument;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal', ['title' => 'KYC Documents'])]
class Index extends Component
{
    use WithFileUploads;

    public string $document_type = 'national_id';

    public $file;

    public array $documentTypes = [
        'national_id' => 'National ID',
        'business_license' => 'Business license',
        'tin_certificate' => 'TIN certificate',
        'other' => 'Other',
    ];

    public function upload()
    {
        $this->validate([
            'document_type' => ['required', 'in:national_id,business_license,tin_certificate,other'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $document = KycDocument::create([
            'merchant_id' => Auth::user()->merchant_id,
            'document_type' => $this->document_type,
            'status' => KycDocument::STATUS_PENDING,
        ]);

        $document->addMedia($this->file->getRealPath())
            ->usingFileName($this->file->getClientOriginalName())
            ->toMediaCollection('file');

        $this->reset(['file']);
        session()->flash('status', 'Document uploaded and sent for review.');
    }

    public function render()
    {
        return view('livewire.portal.kyc.index', [
            'merchant' => Auth::user()->merchant,
            'documents' => Auth::user()->merchant->kycDocuments()->latest()->get(),
        ]);
    }
}
