<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Toggle the authenticated agent's availability for automated workload assignment.
     */
    public function toggle(Request $request): RedirectResponse
    {
        /** @var \App\Models\Agent $agent */
        $agent = auth('agent')->user();
        $newStatus = !$agent->is_available;

        $agent->update([
            'is_available' => $newStatus,
        ]);

        $statusText = $newStatus ? 'Available' : 'Away (Paused)';

        return back()->with('success', "Your status has been updated to: {$statusText}.");
    }
}
