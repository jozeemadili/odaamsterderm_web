<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make(Str::random(16)),
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => $request->user()->merchant_id,
        ]);

        $user->assignRole($data['role']);

        return new UserResource($user);
    }
}
