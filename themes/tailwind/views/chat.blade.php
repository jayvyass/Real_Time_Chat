<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ $friend->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="flex h-screen">
                    <!-- User List -->
                    <div class="w-1/3 border-r border-gray-200 overflow-y-auto user-container">
                        <div class="p-5">
                            <div class="grid grid-cols-1 gap-6">
                                @foreach ($users as $user)
                                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg mb-3">
                                        <div class="p-5">
                                            <div class="flex items-center">
                                                <a href="{{ route('chat', $user) }}">
                                                    <div class="ml-4">
                                                        <div class="text-lg font-bold text-gray-900">{{ $user->name }}</div>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Chat Window -->
                    <div class="w-2/3 flex flex-col h-full">
                        <chat-component :friend="{{ $friend }}" :current-user="{{ auth()->user() }}" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<style>
    /* Ensure the user list container is scrollable */
    .user-container {
        overflow-y: auto; /* Enable vertical scrolling */
        max-height: calc(100vh - 3.5rem); /* Adjust based on your layout needs */
        border-left: 1px solid #e5e7eb; /* Example border style */
    }
</style>