@extends('layouts.app')

@section('title', 'Layout Test')

@section('content')
    <div class="space-y-6">
        <!-- Test Success Message -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Layout Test Page</h2>
            <p class="text-gray-600 mb-4">This page tests if the layout is working correctly.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-medium text-blue-900">Sidebar</h3>
                    <p class="text-sm text-blue-700">Should be visible on left</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="font-medium text-green-900">Header</h3>
                    <p class="text-sm text-green-700">Should be at the top</p>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h3 class="font-medium text-yellow-900">Content</h3>
                    <p class="text-sm text-yellow-700">Should fill main area</p>
                </div>
            </div>

            <!-- Test Flash Message Buttons -->
            <div class="mt-6 space-x-4">
                <a href="?test=success" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    Test Success Message
                </a>
                <a href="?test=error" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                    Test Error Message
                </a>
                <a href="?test=warning" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700">
                    Test Warning Message
                </a>
                <a href="?test=info" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Test Info Message
                </a>
            </div>
        </div>

        <!-- Sample Content -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Sample Content</h3>
            <p class="text-gray-600 mb-4">
                This is sample content to test the layout. The page should be properly spaced with the sidebar on the left,
                header at the top, and this content in the main area.
            </p>

            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium mb-2">Layout Check:</h4>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>✓ Sidebar visible on desktop (hidden on mobile)</li>
                    <li>✓ Header spans the top</li>
                    <li>✓ Content scrollable in main area</li>
                    <li>✓ Flash messages appear top-right</li>
                    <li>✓ Responsive design works</li>
                </ul>
            </div>
        </div>
    </div>

    @if (request('test'))
        @php
            $testType = request('test');
            $messages = [
                'success' => 'This is a test success message!',
                'error' => 'This is a test error message!',
                'warning' => 'This is a test warning message!',
                'info' => 'This is a test info message!',
            ];
            session()->flash($testType, $messages[$testType] ?? 'Test message');
        @endphp
    @endif
@endsection
