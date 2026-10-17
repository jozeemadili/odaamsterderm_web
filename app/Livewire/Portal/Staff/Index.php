<?php

namespace App\Livewire\Portal\Staff;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Staff'])]
class Index extends Component
{
    public const ROLES = [
        'merchant_manager' => 'Manager',
        'merchant_supervisor' => 'Supervisor',
        'merchant_sales' => 'Sales',
        'merchant_accountant' => 'Accountant',
    ];

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'merchant_sales';

    public bool $showForm = false;

    public function mount()
    {
        abort_unless(Auth::user()->can('manage-own-staff'), 403);
    }

    public function create()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:'.implode(',', array_keys(self::ROLES))],
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'password' => Hash::make(str()->random(16)),
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => Auth::user()->merchant_id,
        ]);

        $user->assignRole($this->role);

        $this->reset(['name', 'email', 'phone', 'showForm']);
        $this->role = 'merchant_sales';
        session()->flash('status', "Staff account created for {$user->name}. Share their email so they can use \"Forgot password?\" on the sign-in page to set their password.");
    }

    public function updateRole(int $userId, string $role): void
    {
        if (! array_key_exists($role, self::ROLES)) {
            return;
        }

        $user = User::where('merchant_id', Auth::user()->merchant_id)
            ->where('id', '!=', Auth::id())
            ->findOrFail($userId);

        $user->syncRoles([$role]);
    }

    public function render()
    {
        return view('livewire.portal.staff.index', [
            'staff' => User::where('merchant_id', Auth::user()->merchant_id)
                ->where('id', '!=', Auth::id())
                ->with('roles')
                ->latest()
                ->get(),
            'roles' => self::ROLES,
        ]);
    }
}
