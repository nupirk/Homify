<!-- Mobile Menu Overlay -->
<div id="mobile-overlay" class="fixed inset-0 bg-slate-900/60 z-40 lg:hidden hidden"></div>

<!-- Sidebar -->
<div id="sidebar"
    class="fixed left-0 top-0 h-full bg-slate-900 border-r border-white/10 z-50 sidebar-full sidebar-transition flex flex-col">
    <!-- Header with Logo and Toggle -->
    <div class="flex items-center justify-between h-16 px-4 border-b border-white/10 flex-shrink-0">
        <div class="flex items-center min-w-0">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-home text-white text-base"></i>
            </div>
            <h1 class="ml-3 text-base font-semibold text-white sidebar-text truncate">Urban Admin</h1>
        </div>
        <!-- Desktop Toggle Button -->
        <button id="sidebar-toggle"
            class="hidden lg:flex items-center justify-center w-8 h-8 text-slate-400 hover:text-white hover:bg-white/10 rounded-lg transition-colors duration-150"
            onclick="toggleSidebar()">
            <i class="fas fa-angle-left text-sm transform transition-transform" id="toggle-icon"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-4">
        <div class="px-3 space-y-1">
            <!-- Dashboard -->
            <a href="{{ route('admin.dashboard') }}"
                class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                <i class="fas fa-chart-pie w-5 h-5 flex-shrink-0 mr-3"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
        </div>

        <!-- Users Section -->
        <div class="mt-6">
            <div class="px-3 sidebar-text sidebar-divider">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Users</p>
            </div>
            <div class="mt-2 px-3 space-y-1">
                <a href="{{ route('admin.clients.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.clients.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-users w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Customers</span>
                </a>
                <a href="{{ route('admin.partners.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.partners.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-user-tie w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Partners</span>
                </a>
            </div>
        </div>

        <!-- Services Section -->
        <div class="mt-6">
            <div class="px-3 sidebar-text sidebar-divider">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Services</p>
            </div>
            <div class="mt-2 px-3 space-y-1">
                <a href="{{ route('admin.categories.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.categories.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-tags w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Categories</span>
                </a>
                <a href="{{ route('admin.services.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.services.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-cogs w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Services</span>
                </a>
            </div>
        </div>

        <!-- Marketing Section -->
        <div class="mt-6">
            <div class="px-3 sidebar-text sidebar-divider">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Marketing</p>
            </div>
            <div class="mt-2 px-3 space-y-1">
                <a href="{{ route('admin.notifications.campaigns.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.notifications.campaigns.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-bullhorn w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Campaigns</span>
                </a>
                <a href="{{ route('admin.promotions.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.promotions.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-ticket-alt w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Discount Offers</span>
                </a>
            </div>
        </div>

        <!-- Operations Section -->
        <div class="mt-6">
            <div class="px-3 sidebar-text sidebar-divider">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Operations</p>
            </div>
            <div class="mt-2 px-3 space-y-1">
                <a href="{{ route('admin.orders.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.orders.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-shopping-cart w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Orders</span>
                </a>
                <a href="{{ route('admin.reviews.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.reviews.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-star w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Reviews</span>
                </a>
                <a href="{{ route('admin.earnings.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.earnings.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-dollar-sign w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Earnings</span>
                </a>
                <a href="{{ route('admin.earnings.commission-rules') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.earnings.commission-rules*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-percentage w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Commission Rules</span>
                </a>
            </div>
        </div>

        <!-- Financial Section -->
        <div class="mt-6">
            <div class="px-3 sidebar-text sidebar-divider">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Financial</p>
            </div>
            <div class="mt-2 px-3 space-y-1">
                <a href="{{ route('admin.ledger.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.ledger.index') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-book w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Ledger</span>
                </a>
                <a href="{{ route('admin.ledger.analytics') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.ledger.analytics') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-chart-bar w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Analytics</span>
                </a>
                <a href="{{ route('admin.ledger.reconciliation') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.ledger.reconciliation') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-balance-scale w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Reconciliation</span>
                </a>
                <a href="{{ route('admin.ledger.balances') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.ledger.balances') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-wallet w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">User Balances</span>
                </a>
                <a href="{{ route('admin.ledger.partner-payouts') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.ledger.partner-payouts') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-wallet w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Partner Payout</span>
                </a>
            </div>
        </div>

        <!-- System Section -->
        <div class="mt-6">
            <div class="px-3 sidebar-text sidebar-divider">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">System</p>
            </div>
            <div class="mt-2 px-3 space-y-1">
                <a href="{{ route('admin.notifications.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.notifications.*') && !request()->routeIs('admin.notifications.campaigns.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-bell w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Notifications</span>
                </a>
                <a href="{{ route('admin.error-reports.index') }}"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.error-reports.*') ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white font-medium' }}">
                    <i class="fas fa-bug w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Error Reports</span>
                </a>
                <a href="#"
                    class="group flex items-center px-3 py-2.5 text-sm rounded-lg transition-colors duration-150 text-slate-300 hover:bg-white/5 hover:text-white font-medium">
                    <i class="fas fa-cog w-5 h-5 flex-shrink-0 mr-3"></i>
                    <span class="sidebar-text">Settings</span>
                </a>
            </div>
        </div>
    </nav>
</div>
