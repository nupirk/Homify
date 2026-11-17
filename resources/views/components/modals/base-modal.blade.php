@props([
    'id' => 'modal',
    'title' => 'Modal Title',
    'size' => 'md', // sm, md, lg, xl
    'closable' => true,
    'backdrop' => true,
])

@php
    $sizeClasses = [
        'sm' => 'w-11/12 md:w-1/3 lg:w-1/4',
        'md' => 'w-11/12 md:w-1/2 lg:w-1/3',
        'lg' => 'w-11/12 md:w-2/3 lg:w-1/2',
        'xl' => 'w-11/12 md:w-3/4 lg:w-2/3',
    ];
@endphp

<div id="{{ $id }}" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50"
    @if ($backdrop) onclick="if(event.target === this) close{{ Str::studly($id) }}Modal()" @endif>
    <div class="relative top-20 mx-auto p-5 border {{ $sizeClasses[$size] }} shadow-lg rounded-xl bg-white">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-900">{{ $title }}</h3>
            @if ($closable)
                <button onclick="close{{ Str::studly($id) }}Modal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            @endif
        </div>

        <!-- Content -->
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>
