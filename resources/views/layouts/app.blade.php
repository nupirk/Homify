<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ServiceCo Admin')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/admin-utils.js') }}"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    transitionProperty: {
                        'width': 'width',
                        'margin': 'margin',
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom responsive sidebar */
        .sidebar-full {
            width: 16rem;
        }

        .sidebar-mini {
            width: 4rem;
        }

        .content-full {
            margin-left: 16rem;
        }

        .content-mini {
            margin-left: 4rem;
        }

        .sidebar-transition {
            transition: width 0.15s ease-out;
        }

        .content-transition {
            transition: margin-left 0.15s ease-out;
        }

        /* Hide text in mini mode */
        .sidebar-mini .sidebar-text {
            display: none;
        }

        .sidebar-mini .sidebar-divider {
            display: none;
        }

        /* Mobile first - sidebar hidden */
        @media (max-width: 1023px) {

            .sidebar-full,
            .sidebar-mini {
                width: 16rem;
                transform: translateX(-100%);
            }

            .sidebar-open {
                transform: translateX(0);
            }

            .content-full,
            .content-mini {
                margin-left: 0 !important;
            }
        }

        /* Desktop - responsive margins */
        @media (min-width: 1024px) {

            .sidebar-full,
            .sidebar-mini {
                transform: translateX(0);
            }

            .content-full {
                margin-left: 16rem;
            }

            .content-mini {
                margin-left: 4rem;
            }
        }
    </style>
</head>

<body class="bg-slate-50">
    <div class="flex h-screen max-w-full overflow-hidden">
        <!-- Sidebar -->
        @include('components.sidebar')

        <!-- Flash Messages -->
        @include('components.flash-messages')

        <!-- Main Content -->
        <div id="main-content" class="flex flex-col flex-1 content-full content-transition min-w-0 lg:ml-64">
            <!-- Header -->
            @include('components.header')

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-4 sm:p-6">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Toggle mobile menu
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            if (window.innerWidth >= 1024) return; // Only work on mobile

            sidebar.classList.toggle('sidebar-open');
            overlay.classList.toggle('hidden');
        }

        // Toggle desktop sidebar (collapse/expand)
        function toggleSidebar() {
            if (window.innerWidth < 1024) return; // Only work on desktop

            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleIcon = document.getElementById('toggle-icon');

            sidebar.classList.toggle('sidebar-mini');
            sidebar.classList.toggle('sidebar-full');

            if (sidebar.classList.contains('sidebar-mini')) {
                mainContent.classList.remove('content-full');
                mainContent.classList.add('content-mini');
                mainContent.classList.remove('lg:ml-64');
                mainContent.classList.add('lg:ml-16');
                if (toggleIcon) toggleIcon.style.transform = 'rotate(180deg)';
            } else {
                mainContent.classList.remove('content-mini');
                mainContent.classList.add('content-full');
                mainContent.classList.remove('lg:ml-16');
                mainContent.classList.add('lg:ml-64');
                if (toggleIcon) toggleIcon.style.transform = 'rotate(0deg)';
            }

            // Save state
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('sidebar-mini'));
        }

        // Restore sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleIcon = document.getElementById('toggle-icon');

            if (isCollapsed && window.innerWidth >= 1024) {
                sidebar.classList.remove('sidebar-full');
                sidebar.classList.add('sidebar-mini');
                mainContent.classList.remove('content-full', 'lg:ml-64');
                mainContent.classList.add('content-mini', 'lg:ml-16');
                if (toggleIcon) toggleIcon.style.transform = 'rotate(180deg)';
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const overlay = document.getElementById('mobile-overlay');

            if (window.innerWidth >= 1024) {
                // Desktop: restore saved state
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                sidebar.classList.remove('sidebar-open');
                overlay.classList.add('hidden');

                if (isCollapsed) {
                    sidebar.classList.add('sidebar-mini');
                    sidebar.classList.remove('sidebar-full');
                    mainContent.classList.add('content-mini');
                    mainContent.classList.remove('content-full');
                } else {
                    sidebar.classList.add('sidebar-full');
                    sidebar.classList.remove('sidebar-mini');
                    mainContent.classList.add('content-full');
                    mainContent.classList.remove('content-mini');
                }
            } else {
                // Mobile: always full sidebar, no margin on content
                sidebar.classList.add('sidebar-full');
                sidebar.classList.remove('sidebar-mini');
                mainContent.classList.remove('content-full', 'content-mini');
            }
        });

        // Close mobile menu when clicking overlay
        document.getElementById('mobile-overlay').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            sidebar.classList.remove('sidebar-open');
            overlay.classList.add('hidden');
        }); // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuButton = document.getElementById('mobile-menu-button');
            const overlay = document.getElementById('mobile-overlay');

            if (!sidebar.contains(event.target) && !menuButton.contains(event.target) && !overlay.classList
                .contains('hidden')) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        });

        // User menu dropdown functionality
        function toggleUserMenu() {
            const userMenu = document.getElementById('user-menu');
            userMenu.classList.toggle('hidden');
        }

        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');

            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
    </script>

    @stack('scripts')
</body>

</html>
