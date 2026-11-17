{{-- Flash Messages Component --}}
@if (session('success') || session('error') || session('warning') || session('info') || $errors->any())
    <div id="flash-messages" class="fixed top-4 right-4 z-50 space-y-2">
        {{-- Success Message --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl shadow-sm max-w-sm w-full flex items-center justify-between"
                role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-emerald-600 mr-2.5"></i>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
                <button type="button" class="ml-3 text-emerald-600 hover:text-emerald-700 transition-colors duration-150" onclick="closeAlert(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        {{-- Error Message --}}
        @if (session('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl shadow-sm max-w-sm w-full flex items-center justify-between"
                role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-rose-600 mr-2.5"></i>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
                <button type="button" class="ml-3 text-rose-600 hover:text-rose-700 transition-colors duration-150" onclick="closeAlert(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        {{-- Warning Message --}}
        @if (session('warning'))
            <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl shadow-sm max-w-sm w-full flex items-center justify-between"
                role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-amber-600 mr-2.5"></i>
                    <span class="text-sm font-medium">{{ session('warning') }}</span>
                </div>
                <button type="button" class="ml-3 text-amber-600 hover:text-amber-700 transition-colors duration-150" onclick="closeAlert(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        {{-- Info Message --}}
        @if (session('info'))
            <div class="bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-3 rounded-xl shadow-sm max-w-sm w-full flex items-center justify-between"
                role="alert">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-indigo-600 mr-2.5"></i>
                    <span class="text-sm font-medium">{{ session('info') }}</span>
                </div>
                <button type="button" class="ml-3 text-indigo-600 hover:text-indigo-700 transition-colors duration-150" onclick="closeAlert(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl shadow-sm max-w-sm w-full"
                role="alert">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-rose-600 mr-2.5 mt-0.5"></i>
                    <div class="flex-1">
                        <span class="text-sm font-medium block mb-1.5">Please fix the following errors:</span>
                        <ul class="text-sm list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" class="ml-3 text-rose-600 hover:text-rose-700 transition-colors duration-150" onclick="closeAlert(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- JavaScript for handling alerts --}}
    <script>
        function closeAlert(button) {
            const alert = button.closest('[role="alert"]');
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(100%)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }

        // Auto-hide alerts after 5 seconds (except error messages)
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('#flash-messages [role="alert"]');
            alerts.forEach(function(alert) {
                // Don't auto-hide error messages or validation errors
                if (!alert.classList.contains('bg-rose-50')) {
                    setTimeout(function() {
                        if (alert.parentNode) {
                            closeAlert(alert.querySelector('button'));
                        }
                    }, 5000);
                }
            });
        });

        // Add slide-in animation
        document.addEventListener('DOMContentLoaded', function() {
            const flashContainer = document.getElementById('flash-messages');
            if (flashContainer) {
                flashContainer.style.transform = 'translateX(100%)';
                flashContainer.style.opacity = '0';

                setTimeout(() => {
                    flashContainer.style.transition = 'all 0.15s ease-out';
                    flashContainer.style.transform = 'translateX(0)';
                    flashContainer.style.opacity = '1';
                }, 100);
            }
        });
    </script>

    <style>
        #flash-messages [role="alert"] {
            transition: opacity 0.15s ease-out, transform 0.15s ease-out;
        }
    </style>
@endif
