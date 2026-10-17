<?php

namespace App\Livewire\Admin\Merchants;

use App\Models\BusinessType;
use App\Models\KycDocument;
use App\Models\Merchant;
use App\Models\Region;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'Merchant Detail'])]
class Show extends Component
{
    public Merchant $merchant;

    public string $reviewNotes = '';

    public bool $isEditing = false;

    public string $business_name = '';

    public string $owner_name = '';

    public string $phone = '';

    public string $email = '';

    public string $business_type_id = '';

    public string $tin_number = '';

    public string $physical_address = '';

    public string $region_id = '';

    public string $city = '';

    public string $subscription_tier = 'basic';

    public function mount(Merchant $merchant)
    {
        $this->merchant = $merchant;
        $this->reviewNotes = (string) $merchant->review_notes;
        $this->fillProfileFields();
    }

    protected function fillProfileFields(): void
    {
        $this->business_name = $this->merchant->business_name;
        $this->owner_name = $this->merchant->owner_name;
        $this->phone = $this->merchant->phone;
        $this->email = (string) $this->merchant->email;
        $this->business_type_id = (string) $this->merchant->business_type_id;
        $this->tin_number = (string) $this->merchant->tin_number;
        $this->physical_address = (string) $this->merchant->physical_address;
        $this->region_id = (string) $this->merchant->region_id;
        $this->city = (string) $this->merchant->city;
        $this->subscription_tier = $this->merchant->subscription_tier;
    }

    public function startEditing(): void
    {
        $this->fillProfileFields();
        $this->isEditing = true;
    }

    public function cancelEditing(): void
    {
        $this->fillProfileFields();
        $this->isEditing = false;
    }

    public function updateProfile(): void
    {
        $this->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_type_id' => ['nullable', 'exists:business_types,id'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'physical_address' => ['nullable', 'string', 'max:255'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'subscription_tier' => ['required', 'in:basic,business,professional'],
        ]);

        $this->merchant->update([
            'business_name' => $this->business_name,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'email' => $this->email ?: null,
            'business_type_id' => $this->business_type_id ?: null,
            'tin_number' => $this->tin_number ?: null,
            'physical_address' => $this->physical_address ?: null,
            'region_id' => $this->region_id ?: null,
            'city' => $this->city ?: null,
            'subscription_tier' => $this->subscription_tier,
        ]);

        $this->isEditing = false;
        session()->flash('status', 'Merchant profile updated.');
    }

    public function toggleStatus(): void
    {
        $newStatus = $this->merchant->isActive() ? Merchant::STATUS_SUSPENDED : Merchant::STATUS_ACTIVE;

        $this->merchant->update(['status' => $newStatus]);

        session()->flash('status', $newStatus === Merchant::STATUS_SUSPENDED
            ? 'Merchant deactivated. Their users can no longer sign in.'
            : 'Merchant reactivated. Their users can sign in again.');
    }

    public function approveKyc()
    {
        $this->merchant->update([
            'kyc_status' => Merchant::KYC_APPROVED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_notes' => $this->reviewNotes,
        ]);

        session()->flash('status', 'Merchant KYC approved.');
    }

    public function rejectKyc()
    {
        $this->merchant->update([
            'kyc_status' => Merchant::KYC_REJECTED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_notes' => $this->reviewNotes,
        ]);

        session()->flash('status', 'Merchant KYC rejected.');
    }

    public function markUnderReview()
    {
        $this->merchant->update([
            'kyc_status' => Merchant::KYC_UNDER_REVIEW,
            'reviewed_by' => Auth::id(),
        ]);

        session()->flash('status', 'Marked as under review.');
    }

    public function approveDocument(int $documentId)
    {
        KycDocument::where('merchant_id', $this->merchant->id)->findOrFail($documentId)->update([
            'status' => KycDocument::STATUS_APPROVED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejectDocument(int $documentId)
    {
        KycDocument::where('merchant_id', $this->merchant->id)->findOrFail($documentId)->update([
            'status' => KycDocument::STATUS_REJECTED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
    }

    public function render()
    {
        $this->merchant->refresh();

        return view('livewire.admin.merchants.show', [
            'documents' => $this->merchant->kycDocuments()->latest()->get(),
            'salesTotal' => $this->merchant->salesRecords()->sum('amount'),
            'expensesTotal' => $this->merchant->expenses()->sum('amount'),
            'users' => $this->merchant->users()->get(),
            'businessTypes' => BusinessType::active()->orderBy('name')->get(),
            'regions' => Region::active()->orderBy('name')->get(),
        ]);
    }
}
