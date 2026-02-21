<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;

class SubscriptionController extends Controller
{
    //

    public function index()
    {
        $plans = Plan::orderBy('price')->get();

        /** @var User $user */
        $user = Auth::user();

        $currentPlanId = $user?->plan_id;

        return view('subscriptions.pricing', compact('plans', 'currentPlanId'));
    }

    /*
    public function change(Request $request)
    {
        $request->validate([
            'plan_slug' => 'required|exists:plans,slug'
        ]);

        /** @var User $user */
        /*
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
    */

    public function checkout(Request $request)
    {
        $request->validate([
            'plan_slug' => ['required', 'exists:plans,slug'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        if (!$user) abort(403);

        $plan = Plan::where('slug', $request->plan_slug)->firstOrFail();

        if ((int) $user->plan_id === (int) $plan->id) {
            return back()->with('success', 'You already have this plan.');
        }

        if ((int) $plan->price === 0) {

            if ($user->subscribed('default')) {
                $user->subscription('default')->cancel();
            }

            $user->plan_id = $plan->id;
            $user->files_used_this_month = 0;
            $user->save();

            return back()->with('success', 'Plan updated! Subscription will cancel at period end.');
        }

        if (empty($plan->stripe_price_id)) {
            return back()->withErrors(['plan_slug' => ' This plan does not have a stripe_price_id configured.']);
        }

        if ($user->subscribed('default')) {
            return redirect()->route('billing.portal');
        }

        return $user->newSubscription('default', $plan->stripe_price_id)->checkout([
            'success_url' => route('subscription.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('subscription.cancel'),
            'metadata'    => [
                'plan_id' => (string) $plan->id,
                'user_id' => (string) $user->id,
            ],
        ]);
    }

    public function success()
    {
        return redirect()->route('pricing')->with('success', 'Payment successful! Your subscription has been updated.');
    }

    public function cancel()
    {
        return redirect()->route('pricing')->with('success', 'Payment cancelled.');
    }


}
