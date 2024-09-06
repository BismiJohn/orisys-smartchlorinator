<?php

namespace App\Http\Controllers;

use App\Models\Alerts;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $alerts = Alerts::with(['sensors'])->paginate(8);
        return view('alerts.index', compact('alerts'));
    }



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    public function count()
    {
        $totalAlertsCount = Alerts::count();
        $activeAlertsCount = Alerts::where('status', 'Active')->count();
        $inactiveAlertsCount = Alerts::where('status', 'Resolved')->count();

        return view('alerts.count', compact('totalAlertsCount', 'activeAlertsCount', 'inactiveAlertsCount'));
    }
}
