<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->metrics->summary()]);
    }
}
