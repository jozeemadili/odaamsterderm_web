<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin', ['title' => 'Staff & Roles'])]
class Index extends Component
{
    public string $name = '';

    public string $email = '';

    public string $role = '';

    public bool $showForm = false;

    public function create()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make(str()->random(16)),
            'user_type' => User::TYPE_INTERNAL,
        ]);

        $user->assignRole($this->role);

        $this->reset(['name', 'email', 'role', 'showForm']);
        session()->flash('status', "Staff account created for {$user->name}. They should use 'forgot password' to set their own password.");
    }

    public function updateRole(int $userId, string $role)
    {
        $user = User::findOrFail($userId);
        $user->syncRoles([$role]);
    }

    public function render()
    {
        return view('livewire.admin.users.index', [
            'staff' => User::where('user_type', User::TYPE_INTERNAL)->with('roles')->latest()->get(),
            'roles' => Role::whereIn('name', [
                'super_admin', 'risk_compliance_officer', 'support_agent', 'finance_ops',
            ])->orderBy('name')->pluck('name'),
        ]);
    }
}
