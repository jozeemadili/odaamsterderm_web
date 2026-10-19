<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\StoreAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public const ROLES = [
        'merchant_manager' => 'Manager',
        'merchant_supervisor' => 'Supervisor',
        'merchant_sales' => 'Sales',
        'merchant_accountant' => 'Accountant',
    ];

    public function index(Request $request)
    {
        abort_unless($request->user()->can('manage-own-staff'), 403);

        $staff = User::where('merchant_id', $request->user()->merchant_id)->with('roles')->get();

        return UserResource::collection($staff);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('manage-own-staff'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:'.implode(',', array_keys(self::ROLES))],
        ] + $this->storeRules($request));

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make(Str::random(16)),
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => $request->user()->merchant_id,
        ]);

        $user->assignRole($data['role']);
        $this->saveStores($user, $data);

        return new UserResource($user->fresh());
    }

    /** Change a staff member's role and/or store access. */
    public function update(Request $request, User $staff)
    {
        abort_unless($request->user()->can('manage-own-staff'), 403);
        abort_unless($staff->merchant_id === $request->user()->merchant_id && $staff->id !== $request->user()->id, 404);

        $data = $request->validate([
            'role' => ['sometimes', 'in:'.implode(',', array_keys(self::ROLES))],
        ] + $this->storeRules($request));

        if (isset($data['role'])) {
            $staff->syncRoles([$data['role']]);
        }
        $this->saveStores($staff, $data);

        return new UserResource($staff->fresh());
    }

    protected function storeRules(Request $request): array
    {
        return [
            'all_stores' => ['sometimes', 'boolean'],
            'store_ids' => [$request->boolean('all_stores') || ! $request->has('all_stores') ? 'nullable' : 'required', 'array'],
            'store_ids.*' => ['integer', Rule::exists('branches', 'id')->where('merchant_id', $request->user()->merchant_id)],
        ];
    }

    /** New staff default to all stores unless stores are given. */
    protected function saveStores(User $user, array $data): void
    {
        if (! array_key_exists('all_stores', $data) && ! array_key_exists('store_ids', $data)) {
            if (! $user->wasRecentlyCreated) {
                return;
            }
            $data['all_stores'] = true;
        }

        $all = (bool) ($data['all_stores'] ?? false);
        $user->update(['all_branches' => $all]);
        $user->branches()->sync($all ? [] : ($data['store_ids'] ?? []));
        StoreAccess::forget();
    }
}
