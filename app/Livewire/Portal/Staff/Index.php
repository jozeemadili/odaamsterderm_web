<?php

namespace App\Livewire\Portal\Staff;

use App\Models\Branch;
use App\Models\User;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

    /** 'all' or 'selected' */
    public string $storeAccess = 'selected';

    /** @var list<string> store ids for 'selected' */
    public array $storeIds = [];

    /** Staff member whose store access is being edited. */
    public ?int $editingAccessFor = null;

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
        ] + $this->accessRules());

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'password' => Hash::make(str()->random(16)),
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => Auth::user()->merchant_id,
        ]);

        $user->assignRole($this->role);
        $this->saveAccess($user);

        $this->reset(['name', 'email', 'phone', 'showForm', 'storeIds']);
        $this->storeAccess = 'selected';
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

    public function editAccess(int $userId): void
    {
        $user = $this->staffMember($userId);
        $this->editingAccessFor = $user->id;
        $this->storeAccess = $user->all_branches ? 'all' : 'selected';
        $this->storeIds = $user->branches()->pluck('branches.id')->map(fn ($id) => (string) $id)->all();
        $this->resetErrorBag();
    }

    public function updateAccess(): void
    {
        $this->validate($this->accessRules());
        $user = $this->staffMember($this->editingAccessFor);
        $this->saveAccess($user);

        session()->flash('status', "Store access updated for {$user->name}.");
        $this->reset(['editingAccessFor', 'storeIds']);
        $this->storeAccess = 'selected';
    }

    public function cancelAccess(): void
    {
        $this->reset(['editingAccessFor', 'storeIds']);
        $this->storeAccess = 'selected';
    }

    protected function accessRules(): array
    {
        return [
            'storeAccess' => ['required', 'in:all,selected'],
            'storeIds' => [$this->storeAccess === 'selected' ? 'required' : 'nullable', 'array'],
            'storeIds.*' => ['integer', Rule::exists('branches', 'id')->where('merchant_id', Auth::user()->merchant_id)],
        ];
    }

    protected function saveAccess(User $user): void
    {
        $user->update(['all_branches' => $this->storeAccess === 'all']);
        $user->branches()->sync($this->storeAccess === 'all' ? [] : array_map('intval', $this->storeIds));
        StoreAccess::forget();
    }

    protected function staffMember(?int $userId): User
    {
        return User::where('merchant_id', Auth::user()->merchant_id)
            ->where('id', '!=', Auth::id())
            ->findOrFail($userId);
    }

    public function render()
    {
        return view('livewire.portal.staff.index', [
            'stores' => Branch::where('merchant_id', Auth::user()->merchant_id)->orderBy('name')->get(['id', 'name']),
            'staff' => User::where('merchant_id', Auth::user()->merchant_id)
                ->where('id', '!=', Auth::id())
                ->with(['roles', 'branches'])
                ->latest()
                ->get(),
            'roles' => self::ROLES,
        ]);
    }
}
