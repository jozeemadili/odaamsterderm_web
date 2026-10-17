<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessTypeResource;
use App\Http\Resources\RegionResource;
use App\Models\BusinessType;
use App\Models\Region;

/**
 * Public (unauthenticated) reference-data lookups — the mobile app needs
 * these on the registration screen, before a session token exists.
 */
class LookupController extends Controller
{
    public function businessTypes()
    {
        return BusinessTypeResource::collection(
            BusinessType::active()->orderBy('name')->get()
        );
    }

    public function regions()
    {
        return RegionResource::collection(
            Region::active()->orderBy('name')->get()
        );
    }
}
