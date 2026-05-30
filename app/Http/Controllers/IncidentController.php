<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $status = strtoupper((string) $request->query('status'));
        $incidents = Incident::with('site')
            ->whereHas('site', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when(in_array($status, ['OPEN', 'RESOLVED'], true), fn ($q) => $q->where('status', $status))
            ->latest('opened_at')
            ->paginate(30);

        return view('incidents.index', compact('incidents'));
    }
}
