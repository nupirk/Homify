@props(['partners' => []])

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <form method="GET" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Orders</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}"
                        class="w-full max-w-xs px-3 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                        placeholder="Order ID, customer name...">
                    @if (request('search'))
                        <button type="button" onclick="clearSearch()"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <div class="relative">
                    <button type="button" id="statusDropdownBtn" onclick="toggleStatusDropdown()"
                        class="w-full px-3 py-1.5 border border-gray-300 rounded-md bg-white text-left text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent flex items-center justify-between">
                        <span id="statusDropdownText">All Statuses</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>

                    <div id="statusDropdownMenu"
                        class="hidden absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg">
                        <div class="py-1 max-h-48 overflow-y-auto">
                            @php
                                $statuses = [
                                    'created' => 'Created',
                                    'awaiting_partner' => 'Awaiting Partner',
                                    'confirmed' => 'Confirmed',
                                    'enroute' => 'En Route',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'cancelled_by_admin' => 'Cancelled',
                                ];
                            @endphp
                            @foreach ($statuses as $value => $label)
                                <label class="flex items-center px-3 py-2 hover:bg-gray-100 cursor-pointer">
                                    <input type="checkbox" name="status[]" value="{{ $value }}"
                                        {{ in_array($value, request('status', [])) ? 'checked' : '' }}
                                        onchange="updateStatusDisplay()"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm ml-2">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Partner Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Partner</label>
                <select name="partner_id"
                    class="w-full px-3 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                    <option value="">All Partners</option>
                    <option value="unassigned" {{ request('partner_id') === 'unassigned' ? 'selected' : '' }}>
                        Unassigned
                    </option>
                    @foreach ($partners as $partner)
                        <option value="{{ $partner->user_id }}"
                            {{ request('partner_id') == $partner->user_id ? 'selected' : '' }}>
                            {{ $partner->first_name }} {{ $partner->last_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                <select name="date_range" onchange="toggleCustomDateInputs(this.value)"
                    class="w-full px-3 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                    <option value="" {{ request('date_range') === '' ? 'selected' : '' }}>All Time</option>
                    <option value="today" {{ request('date_range') === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="yesterday" {{ request('date_range') === 'yesterday' ? 'selected' : '' }}>Yesterday
                    </option>
                    <option value="this_week" {{ request('date_range') === 'this_week' ? 'selected' : '' }}>This Week
                    </option>
                    <option value="this_month" {{ request('date_range') === 'this_month' ? 'selected' : '' }}>This
                        Month</option>
                    <option value="custom" {{ request('date_range') === 'custom' ? 'selected' : '' }}>Custom Range
                    </option>
                </select>
            </div>
        </div>

        <!-- Custom Date Inputs -->
        <div id="customDateInputs" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4"
            style="display: {{ request('date_range') === 'custom' ? 'grid' : 'none' }};">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}"
                    class="w-full px-3 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}"
                    class="w-full px-3 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
            </div>
        </div>

        <!-- Filter Actions -->
        <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t">
            <div class="flex items-center space-x-2">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <i class="fas fa-search mr-2"></i>Apply Filters
                </button>
                <a href="{{ url()->current() }}"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-md text-sm font-medium">
                    <i class="fas fa-undo mr-2"></i>Clear All
                </a>
            </div>
            <div class="flex items-center space-x-2">
                <button type="button" onclick="exportOrders()"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <i class="fas fa-download mr-2"></i>Export
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
    <script>
        function toggleStatusDropdown() {
            const menu = document.getElementById('statusDropdownMenu');
            menu.classList.toggle('hidden');
        }

        function updateStatusDisplay() {
            const checkboxes = document.querySelectorAll('input[name="status[]"]:checked');
            const text = document.getElementById('statusDropdownText');

            if (checkboxes.length === 0) {
                text.textContent = 'All Statuses';
            } else if (checkboxes.length === 1) {
                text.textContent = checkboxes[0].nextElementSibling.textContent;
            } else {
                text.textContent = `${checkboxes.length} statuses selected`;
            }
        }

        function toggleCustomDateInputs(value) {
            const customInputs = document.getElementById('customDateInputs');
            customInputs.style.display = value === 'custom' ? 'grid' : 'none';
        }

        function clearSearch() {
            document.querySelector('input[name="search"]').value = '';
            document.querySelector('form').submit();
        }

        function exportOrders() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            const params = new URLSearchParams();

            for (let [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }

            window.open(`{{ route('admin.orders.export') }}?${params.toString()}`, '_blank');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('statusDropdownMenu');
            const button = document.getElementById('statusDropdownBtn');

            if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Initialize display on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateStatusDisplay();
        });
    </script>
@endpush
