<?php

namespace App\Livewire\Admin\AuditLog;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.admin', ['title' => 'Audit Log'])]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        $activities = Activity::with('causer', 'subject')->latest()->paginate(25);

        return view('livewire.admin.audit-log.index', compact('activities'));
    }
}
