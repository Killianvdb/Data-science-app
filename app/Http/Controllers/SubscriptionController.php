<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    //

    public function index()
    {
        $plans = Plan::orderBy('price')->get();
        return view('subscriptions.pricing', compact('plans'));
    }

    public function change(Request $request)
    {
        $request->validate([
            'plan_slug' => 'required|exists:plans,slug'
        ]);

        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $plan = Plan::where('slug', $request->plan_slug)->first();

        $user->plan_id = $plan->id;
        $user->files_used_this_month = 0; // opcional reset
        $user->save();

        return back()->with('success', 'Plan updated!');
    }

}
