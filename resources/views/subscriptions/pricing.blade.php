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

                <form method="POST" action="{{ route('subscription.change') }}">
                    @csrf
                    <input type="hidden" name="plan_slug" value="{{ $plan->slug }}">

                    <button type="submit"
                        class="w-full text-left bg-white border-4 border-indigo-200 rounded-2xl p-6
                            shadow-sm hover:shadow-lg hover:-translate-y-1
                            hover:border-indigo-500 transition-all duration-300">

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
                            <li>Max file size: {{ $plan->max_file_size_mb }} MB</li>
                            <li>Files/transaction: {{ $plan->max_files_per_transaction }}</li>
                        </ul>

                        <div class="mt-6 text-center">
                            <span class="inline-block bg-indigo-600 text-white px-4 py-2 rounded-xl">
                                Choose {{ $plan->name }}
                            </span>
                        </div>

                    </button>
                </form>

            @endforeach
        </div>


    </div>
</x-app-layout>
