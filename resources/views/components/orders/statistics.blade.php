@props(['stats' => []])

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Today's Overview -->
    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow-sm border border-blue-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-blue-600 mb-1">Today's Orders</p>
                <p class="text-3xl font-bold text-blue-900">{{ $stats['orders_today'] ?? 0 }}</p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-blue-700">Revenue:</span>
                    <span class="text-sm font-semibold text-blue-800 ml-1">Rs {{ $stats['revenue_today'] ?? 0 }}</span>
                </div>
            </div>
            <div class="p-3 bg-blue-200 rounded-lg">
                <i class="fas fa-calendar-day text-blue-700 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Total Orders & Growth -->
    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl shadow-sm border border-emerald-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-600 mb-1">Total Orders</p>
                <p class="text-3xl font-bold text-emerald-900">{{ $stats['total_orders'] ?? 0 }}</p>
                <div class="flex items-center mt-2">
                    <i class="fas fa-arrow-up text-xs text-emerald-600 mr-1"></i>
                    <span class="text-xs text-emerald-700">{{ $stats['growth_percentage'] ?? 0 }}% this month</span>
                </div>
            </div>
            <div class="p-3 bg-emerald-200 rounded-lg">
                <i class="fas fa-chart-line text-emerald-700 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl shadow-sm border border-purple-200 p-6">
        <div class="flex items-center justify-between">
            <div class="w-full">
                <p class="text-sm font-medium text-purple-600 mb-2">Status Overview</p>
                <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-purple-700">Pending</span>
                        <span class="font-medium">{{ $stats['status_pending'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-purple-700">Active</span>
                        <span class="font-medium">{{ $stats['status_active'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-purple-700">Completed</span>
                        <span class="font-medium">{{ $stats['status_completed'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue & Performance -->
    <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl shadow-sm border border-orange-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-orange-600 mb-1">Total Revenue</p>
                <p class="text-3xl font-bold text-orange-900">Rs {{ $stats['total_revenue'] ?? 0 }}</p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-orange-700">Avg Order:</span>
                    <span class="text-sm font-semibold text-orange-800 ml-1">Rs
                        {{ $stats['avg_order_value'] ?? 0 }}</span>
                </div>
            </div>
            <div class="p-3 bg-orange-200 rounded-lg">
                <i class="fas fa-dollar-sign text-orange-700 text-2xl"></i>
            </div>
        </div>
    </div>
</div>
