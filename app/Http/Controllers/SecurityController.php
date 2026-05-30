<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        return view('security.index', ['sites' => $request->user()->sites()->orderBy('security_grade')->get()]);
    }

    public function show(Site $site)
    {
        return view('security.show', compact('site'));
    }
}
