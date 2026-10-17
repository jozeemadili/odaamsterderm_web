<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function index(Request $request)
    {
        $merchant = $request->user()->merchant;
        $period = $request->query('period', 'month');

        return response()->json($this->metrics->summary($merchant, $period));
    }
}
