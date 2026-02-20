<x-app-layout :title="'Pricing'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Pricing
        </h2>
    </x-slot>

    <div class="py-6 max-w-5xl mx-auto">

        @if(session('success'))
            <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($plans as $plan)

            @php
                $isCurrent = ($currentPlanId ?? null) === $plan->id;
            @endphp

                <form method="POST" action="{{ route('subscription.change') }}">
                    @csrf
                    <input type="hidden" name="plan_slug" value="{{ $plan->slug }}">

                    <button type="submit"
                        @if($isCurrent) disabled @endif
                        class="w-full text-left rounded-2xl p-6 border-4 transition-all duration-300
                            {{ $isCurrent
                                ? 'bg-indigo-50 border-indigo-600 shadow-md cursor-default pointer-events-none'
                                : 'bg-white border-indigo-200 shadow-sm hover:shadow-lg hover:-translate-y-1 hover:border-indigo-500'
                            }}">

                        <h3 class="text-xl font-bold text-center text-gray-900">
                            {{ $plan->name }}
                        </h3>

                        <p class="mt-2 text-gray-600 text-center">
                            @if($plan->price == 0)
                                0€/month
                            @else
                                €{{ $plan->price / 100 }}/month
                            @endif
                        </p>

                        <ul class="mt-6 text-sm text-gray-700 space-y-2">
                            <li>Files/month: {{ $plan->monthly_limit ?? 'Unlimited (fair use)' }}</li>
                            <li>Max file size: {{ $plan->max_total_mb_per_transaction }} MB</li>
                            <li>Files/transaction: {{ $plan->max_files_per_transaction }}</li>
                        </ul>

                        <div class="mt-6 text-center">
                            @if($isCurrent)
                                <span class="inline-block bg-gray-400 text-white px-4 py-2 rounded-xl">
                                    Current plan
                                </span>
                            @else
                                <span class="inline-block bg-indigo-600 text-white px-4 py-2 rounded-xl">
                                    Choose {{ $plan->name }}
                                </span>
                            @endif
                        </div>

                    </button>
                </form>

            @endforeach
        </div>


    </div>
</x-app-layout>
