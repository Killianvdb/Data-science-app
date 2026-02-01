<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dataset;

class DashboardController extends Controller
{
    public function index() {
        return view('dashboard', [
            'datasetsCount' => Dataset::count(),
        ]);
    }
}
