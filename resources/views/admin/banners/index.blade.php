@extends('layouts.app')

@section('title', 'Banners - Homify Admin')

@section('content')
    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Banners</h1>
                <p class="text-gray-600">Promo creatives shown inside the customer app</p>
            </div>
            <a href="{{ route('admin.banners.create') }}"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>New Banner</span>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6 flex items-center">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <i class="fas fa-images text-indigo-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total banners</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $banners->count() }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i class="fas fa-toggle-on text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Switched on</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $activeCount }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Live right now</p>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ $banners->filter(fn($b) => $b->is_active && $b->valid_from <= now() && (!$b->valid_until || $b->valid_until >= now()))->count() }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creative</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Placement</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Runs</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                // Two banners advertising one code means one of them
                                // is silently hiding a surface of that campaign —
                                // the app admin flags the same thing in its list.
                                $promoCounts = $banners
                                    ->where('link_type', 'promo')
                                    ->groupBy(fn($b) => strtoupper(trim((string) $b->link_value)))
                                    ->map->count();
                                $placementLabels = [
                                    'home_mid' => 'Home — below categories',
                                    'explore_top' => 'Explore — top',
                                    'cart_top' => 'Cart — top',
                                ];
                            @endphp
                            @forelse ($banners as $banner)
                                <tr>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center space-x-3">
                                            @if ($banner->image_url)
                                                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}"
                                                    class="w-20 h-12 object-cover rounded-lg border border-gray-200">
                                            @else
                                                <div class="w-20 h-12 rounded-lg border border-dashed border-gray-300 flex items-center justify-center">
                                                    <i class="fas fa-image text-gray-300"></i>
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">{{ $banner->title }}</p>
                                                <p class="text-xs text-gray-500 truncate">{{ $banner->subtitle ?: 'No subtitle' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">
                                        {{ $placementLabels[$banner->placement] ?? ucfirst(str_replace('_', ' ', $banner->placement)) }}
                                        <span class="block text-xs text-gray-400">
                                            {{ ($banner->display_style ?? 'banner') === 'popup' ? 'Modal popup' : ucfirst($banner->display_style ?? 'banner') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">
                                        {{ ucfirst($banner->link_type ?? 'none') }}
                                        <span class="block text-xs text-gray-400 truncate max-w-[160px]">{{ $banner->link_value ?: '—' }}</span>
                                        @if ($banner->link_type === 'promo' && ($promoCounts[strtoupper(trim((string) $banner->link_value))] ?? 0) > 1)
                                            <span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                Repeats a promo code
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">
                                        {{ $banner->valid_from?->format('M d, Y H:i') ?? '—' }}
                                        <span class="block text-xs text-gray-400">
                                            {{ $banner->valid_until ? 'until ' . $banner->valid_until->format('M d, Y H:i') : 'no end date' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">{{ $banner->sort_order }}</td>
                                    <td class="px-4 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $banner->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $banner->is_active ? 'On' : 'Off' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-medium space-x-3 whitespace-nowrap">
                                        <a href="{{ route('admin.banners.edit', $banner) }}"
                                            class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        <form action="{{ route('admin.banners.toggle', $banner) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-gray-600 hover:text-gray-900">
                                                {{ $banner->is_active ? 'Turn off' : 'Turn on' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" class="inline"
                                            onsubmit="return confirm('Delete this banner and its image?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <i class="fas fa-images text-gray-300 text-4xl mb-3"></i>
                                        <h3 class="text-lg font-medium text-gray-900 mb-1">No banners yet</h3>
                                        <p class="text-gray-500 mb-4">Banners you create here appear inside the app right away.</p>
                                        <a href="{{ route('admin.banners.create') }}"
                                            class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm">
                                            Create your first banner
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
