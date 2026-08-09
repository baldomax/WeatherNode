<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Dashboard\DashboardPayloadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardPayloadService $dashboardPayload): Response
    {
        $hybridDefault = filter_var((string) env('DASHBOARD_HYBRID_SSR', 'false'), FILTER_VALIDATE_BOOLEAN);
        $hybridEnabled = (bool) Setting::getValue('dashboard.hybrid_ssr_enabled', $hybridDefault);
        $ssrDashboard = $hybridEnabled ? $dashboardPayload->getDashboardPayload($request) : null;

        return response()
            ->view('weather.dashboard', [
                'dashboardHybridSsrEnabled' => $hybridEnabled,
                'ssrDashboard' => $ssrDashboard,
            ])
            ->header('Cache-Control', DashboardPayloadService::browserCacheControl($request->user()));
    }
}
