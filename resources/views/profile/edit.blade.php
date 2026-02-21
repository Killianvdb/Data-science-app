<x-app-layout :title="'My Profile'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            My Profile
        </h2>
    </x-slot>

    <div class="max-w-3xl mx-auto p-6 bg-white rounded-lg shadow space-y-6">
        @if (session('status') === 'profile-updated')
            <div class="mb-4 rounded bg-green-100 px-4 py-3 text-green-800">
                Profile updated successfully!
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 rounded bg-red-100 px-4 py-3 text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="mb-4 rounded bg-green-100 px-4 py-3 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 bg-red-100 text-red-800 rounded mb-4">
                <ul class="list-disc ml-6">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="relative bg-indigo-50 border-4 border-indigo-600 rounded-2xl p-6 shadow-md">

                <div class="absolute -top-3 right-4 bg-indigo-600 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">
                    Current plan
                </div>

                <h3 class="text-lg font-semibold text-indigo-800 mb-3">
                    Subscription
                </h3>

                <div class="flex items-center justify-between flex-wrap gap-3">

                    <div>
                        <p class="text-gray-800 font-semibold text-lg">
                            {{ $currentPlan->name }}
                        </p>

                        <p class="text-gray-500 text-sm">
                            @if($currentPlan->price == 0)
                                0€/month
                            @else
                                €{{ $currentPlan->price / 100 }}/month
                            @endif
                        </p>
                    </div>

                    @if($currentPlan->price == 0)
                        <p class="text-xs text-indigo-600 font-medium mt-1">
                            Upgrade anytime
                        </p>
                    @endif

                    <a href="{{ route('pricing') }}"
                    class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-medium
                            hover:bg-indigo-700 transition shadow-sm">
                        Change plan
                    </a>

                </div>

            </div>

            <div>
                <label for="name" class="block font-medium text-gray-700">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="block font-medium text-gray-700">User Type</label>
                <select name="user_type"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select one</option>
                    @foreach (['student','researcher','business','developer','other'] as $type)
                        <option value="{{ $type }}"
                            @selected(old('user_type', $user->user_type) === $type)>
                            {{ ucfirst($type) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-medium text-gray-700">Phone Number</label>
                <input type="text" name="phone_number"
                    value="{{ old('phone_number', $user->phone_number) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Email</label>
                <input
                    type="email"
                    value="{{ $user->email }}"
                    readonly
                    class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 cursor-not-allowed"
                >
                <p class="text-sm text-gray-500 mt-1">
                    Email cannot be changed.
                </p>
            </div>

            <div>
                <label for="password" class="block font-medium text-gray-700">New Password (leave blank to keep current)</label>
                <input id="password" type="password" name="password"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label for="password_confirmation" class="block font-medium text-gray-700">Confirm New Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <button type="submit"
                    class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    Update Profile
                </button>
            </div>
        </form>

        <hr class="my-10">

        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-red-700 mb-2">
                Delete Account
            </h3>

            <p class="text-sm text-red-600 mb-4">
                Once your account is deleted, all of your data will be permanently removed.
                This action cannot be undone.
            </p>

            <button
                onclick="document.getElementById('delete-account-modal').classList.remove('hidden')"
                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
            >
                Delete my account
            </button>
        </div>

        <div id="delete-account-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">

            <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">
                    Are you sure?
                </h2>

                <p class="text-gray-600 mb-6">
                    Are you sure you want to delete your account?
                    This action is permanent and cannot be undone.
                </p>

                <form method="POST" action="{{ route('profile.destroy') }}">
                    @csrf
                    @method('DELETE')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Confirm your password
                        </label>
                        <input type="password" name="password" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        @error('password', 'userDeletion')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button"
                            onclick="document.getElementById('delete-account-modal').classList.add('hidden')"
                            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                            Cancel
                        </button>

                        <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                            Yes, delete my account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-app-layout>
