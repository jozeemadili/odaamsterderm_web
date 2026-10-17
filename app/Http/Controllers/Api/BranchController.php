<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Http\Resources\InventoryItemResource;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::where('merchant_id', Auth::user()->merchant_id)
            ->withCount('inventoryItems')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return BranchResource::collection($branches);
    }

    public function show(Request $request, Branch $branch)
    {
        $this->authorizeBranch($request, $branch);

        return response()->json([
            'data' => (new BranchResource($branch))->toArray($request),
            'items' => InventoryItemResource::collection(
                $branch->inventoryItems()->with('category')->orderBy('name')->get()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']] + $this->detailRules());

        $branch = Branch::create([...$data, 'merchant_id' => Auth::user()->merchant_id]);

        return new BranchResource($branch->fresh());
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorizeBranch($request, $branch);

        $data = $request->validate(['name' => ['sometimes', 'string', 'max:255']] + $this->detailRules());

        $branch->update($data);

        return new BranchResource($branch->fresh());
    }

    /** Contact, location and pickup fields shared by create and update. */
    protected function detailRules(): array
    {
        $time = ['nullable', 'date_format:H:i'];

        return [
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'pickup_instructions' => ['nullable', 'string', 'max:1000'],
            // {"mon": {"open": "08:00", "close": "18:00"}, "sun": null, ...}
            'opening_hours' => ['nullable', 'array:'.implode(',', array_keys(Branch::DAYS))],
            'opening_hours.*' => ['nullable', 'array:open,close'],
            'opening_hours.*.open' => [...$time, 'required_with:opening_hours.*.close'],
            'opening_hours.*.close' => [...$time, 'required_with:opening_hours.*.open'],
            'is_marketplace_enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function authorizeBranch(Request $request, Branch $branch): void
    {
        abort_unless($branch->merchant_id === $request->user()->merchant_id, 403);
    }
}
