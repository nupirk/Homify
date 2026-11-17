<header class="sticky top-0 z-30 bg-white border-b border-slate-200">
    <div class="flex items-center justify-between h-16 px-4 lg:px-6 max-w-full">
        <div class="flex items-center flex-1 min-w-0 mr-4">
            <button id="mobile-menu-button"
                class="lg:hidden inline-flex items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg mr-3 flex-shrink-0 p-2 transition-colors duration-150"
                onclick="toggleMobileMenu()">
                <i class="fas fa-bars text-base"></i>
            </button>

            <div class="flex-1 max-w-xs">
                <div class="relative">
                    <input type="text" placeholder="Search..."
                        class="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 text-sm text-slate-900 placeholder-slate-400 min-w-0 transition-colors duration-150">
                    <i class="fas fa-search absolute left-3 top-2 text-slate-400 text-xs"></i>
                </div>
            </div>
        </div>

        <div class="flex items-center space-x-1.5 flex-shrink-0">
            <!-- Notifications -->
            <div class="relative">
                <button
                    class="relative inline-flex items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg p-2 transition-colors duration-150">
                    <i class="fas fa-bell text-base"></i>
                    <span
                        class="absolute -top-0.5 -right-0.5 bg-rose-600 text-white text-[10px] font-medium rounded-full h-4 min-w-[16px] px-1 flex items-center justify-center ring-2 ring-white">3</span>
                </button>
            </div>

            <!-- User Profile -->
            <div class="relative">
                <button id="user-menu-button"
                    class="flex items-center space-x-2 text-left hover:bg-slate-100 rounded-lg p-1.5 transition-colors duration-150"
                    onclick="toggleUserMenu()">
                    <div class="w-8 h-8 bg-slate-200 rounded-full overflow-hidden flex-shrink-0">
                        <img src="https://ui-avatars.com/api/?name={{ Auth::user()->first_name ?? 'Admin' }}&background=4F46E5&color=fff"
                            alt="Admin" class="w-full h-full object-cover">
                    </div>
                    <div class="hidden sm:block min-w-0 text-left">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ Auth::user()->first_name ?? 'Admin' }}
                            {{ Auth::user()->last_name ?? '' }}</p>
                        <p class="text-xs text-slate-500 truncate">
                            {{ ucfirst(Auth::user()->user_type ?? 'Administrator') }}</p>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-slate-400 hidden sm:block"></i>
                </button>

                <!-- Dropdown Menu -->
                <div id="user-menu"
                    class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-sm border border-slate-200 z-50 py-1.5 overflow-hidden">
                    <div class="px-4 py-2 text-xs text-slate-500 border-b border-slate-100 mb-1">
                        Signed in as <span class="font-medium text-slate-700">{{ Auth::user()->email ?? 'admin@homify.com.np' }}</span>
                    </div>
                    <a href="#"
                        class="flex items-center px-4 py-2 text-sm text-slate-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors duration-150">
                        <i class="fas fa-user-circle w-4 mr-2.5 text-slate-400"></i>
                        Profile Settings
                    </a>
                    <a href="#"
                        class="flex items-center px-4 py-2 text-sm text-slate-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors duration-150">
                        <i class="fas fa-cog w-4 mr-2.5 text-slate-400"></i>
                        Account Settings
                    </a>
                    <div class="border-t border-slate-100 my-1"></div>
                    <form method="POST" action="{{ route('admin.logout') }}" class="block">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center text-left px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 transition-colors duration-150">
                            <i class="fas fa-sign-out-alt w-4 mr-2.5"></i>
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
