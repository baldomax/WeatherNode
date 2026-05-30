<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Alerts\AlertAggregatorService;
use App\Services\Alerts\LocalWarningService;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    public function index()
    {
        $allAlerts      = app(AlertAggregatorService::class)->getAll();
        $externalAlerts = array_values(array_filter($allAlerts, fn ($a) => ($a['source'] ?? '') !== 'internal'));
        $statusSections = app(LocalWarningService::class)->getStatusSections();
        $regionName     = Setting::getValue('alerts.region_name', '');
        $enabled        = (bool) Setting::getValue('alerts.enabled', true);

        return view('weather.alerts', compact('allAlerts', 'externalAlerts', 'statusSections', 'regionName', 'enabled'));
    }

    /**
     * Partial view for AJAX refresh — returns only the refreshable content fragment.
     */
    public function partial()
    {
        $allAlerts      = app(AlertAggregatorService::class)->getAll();
        $externalAlerts = array_values(array_filter($allAlerts, fn ($a) => ($a['source'] ?? '') !== 'internal'));
        $statusSections = app(LocalWarningService::class)->getStatusSections();
        $regionName     = Setting::getValue('alerts.region_name', '');

        return view('weather.alerts-partial', compact('externalAlerts', 'statusSections', 'regionName'));
    }
}
