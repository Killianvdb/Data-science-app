<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\StripeClient;

class StripeWebhookController extends WebhookController
{

    public function handleCheckoutSessionCompleted($payload)
    {
        $session = $payload['data']['object'] ?? null;
        if (!$session) return $this->successMethod();

        $customerId = $session['customer'] ?? null;
        $subscriptionId = $session['subscription'] ?? null;

        Log::info('checkout.session.completed received', [
            'customer' => $customerId,
            'subscription' => $subscriptionId,
            'metadata' => $session['metadata'] ?? null,
        ]);

        if (!$customerId || !$subscriptionId) {
            Log::warning('customer or subscription missing in checkout session');
            return $this->successMethod();
        }

        $user = User::where('stripe_id', $customerId)->first();
        if (!$user) {
            Log::warning('user not found by stripe_id', ['stripe_id' => $customerId]);
            return $this->successMethod();
        }

        $stripe = new StripeClient(config('cashier.secret'));

        $subscription = $stripe->subscriptions->retrieve($subscriptionId, [
            'expand' => ['items.data.price'],
        ]);

        $priceId = $subscription->items->data[0]->price->id ?? null;

        Log::info('subscription read (checkout)', [
            'subscription_id' => $subscriptionId,
            'price_id' => $priceId,
        ]);

        if (!$priceId) {
            Log::warning('could not read price_id (checkout)');
            return $this->successMethod();
        }

        $this->applyPlanToUserByPriceId($user, $priceId);

        return $this->successMethod();
    }


    public function handleCustomerSubscriptionCreated($payload)
    {
        return $this->syncPlanFromSubscriptionEvent($payload, 'customer.subscription.created');
    }


    public function handleCustomerSubscriptionUpdated($payload)
    {
        return $this->syncPlanFromSubscriptionEvent($payload, 'customer.subscription.updated');
    }


    public function handleCustomerSubscriptionDeleted($payload)
    {
        $sub = $payload['data']['object'] ?? null;
        if (!$sub) return $this->successMethod();

        $customerId = $sub['customer'] ?? null;

        Log::info('customer.subscription.deleted received', [
            'customer' => $customerId,
        ]);

        if (!$customerId) return $this->successMethod();

        $user = User::where('stripe_id', $customerId)->first();
        if (!$user) return $this->successMethod();

        $freePlan = Plan::where('slug', 'free')->first();

        if ($freePlan) {
            $user->plan_id = $freePlan->id;
            $user->files_used_this_month = 0;
            $user->save();

            Log::info('User reverted to FREE plan', [
                'user_id' => $user->id,
                'plan_id' => $freePlan->id,
            ]);
        } else {
            Log::warning('Plan free (slug=free) does not exist to assign on cancellation');
        }

        return $this->successMethod();
    }


    private function syncPlanFromSubscriptionEvent(array $payload, string $eventName)
    {
        $sub = $payload['data']['object'] ?? null;
        if (!$sub) return $this->successMethod();

        $customerId = $sub['customer'] ?? null;

        $priceId = $sub['items']['data'][0]['price']['id'] ?? null;

        Log::info("{$eventName} received", [
            'customer' => $customerId,
            'price_id' => $priceId,
        ]);

        if (!$customerId || !$priceId) return $this->successMethod();

        $user = User::where('stripe_id', $customerId)->first();
        if (!$user) {
            Log::warning('user not found by stripe_id (subscription event)', ['stripe_id' => $customerId]);
            return $this->successMethod();
        }

        $this->applyPlanToUserByPriceId($user, $priceId);

        return $this->successMethod();
    }


    private function applyPlanToUserByPriceId(User $user, string $priceId): void
    {
        $plan = Plan::where('stripe_price_id', $priceId)->first();

        if (!$plan) {
            Log::warning('does not exist a plan with that stripe_price_id', ['price_id' => $priceId]);
            return;
        }

        $user->plan_id = $plan->id;
        $user->files_used_this_month = 0;
        $user->save();

        Log::info('PLAN UPDATED', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'price_id' => $priceId,
        ]);
    }
}
