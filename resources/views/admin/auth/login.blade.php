<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - HomifyServe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gradient-to-br from-blue-600 to-purple-700 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <div class="mx-auto h-16 w-16 flex items-center justify-center bg-white rounded-full shadow-lg">
                    <i class="fas fa-shield-alt text-2xl text-blue-600"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-white">
                    Admin Login
                </h2>
                <p class="mt-2 text-center text-sm text-blue-100">
                    Sign in to access the admin dashboard
                </p>
            </div>

            <!-- Flash Messages -->
            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <form class="mt-8 space-y-6" method="POST" action="{{ route('admin.login.post') }}">
                @csrf
                <div class="bg-white rounded-xl shadow-xl p-8">
                    <div class="space-y-6">
                        <!-- Email Field -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-1"></i>
                                Email Address
                            </label>
                            <input id="email" name="email" type="email" autocomplete="email" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 @error('email') border-red-500 @enderror"
                                placeholder="Enter your email" value="{{ old('email') }}">
                            @error('email')
                                <p class="mt-2 text-sm text-red-600">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-1"></i>
                                Password
                            </label>
                            <div class="relative">
                                <input id="password" name="password" type="password" autocomplete="current-password"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 @error('password') border-red-500 @enderror"
                                    placeholder="Enter your password">
                                <button type="button" onclick="togglePassword()"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <i id="password-icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-2 text-sm text-red-600">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember" name="remember" type="checkbox"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="remember" class="ml-2 block text-sm text-gray-700">
                                    Remember me
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                                <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                    <i class="fas fa-sign-in-alt text-blue-500 group-hover:text-blue-400"></i>
                                </span>
                                Sign in to Dashboard
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Footer -->
            <div class="text-center">
                <p class="text-blue-100 text-sm">
                    <i class="fas fa-shield-alt mr-1"></i>
                    HomifyServe Admin Panel - Secure Access Required
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Auto-hide flash messages
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('bg-green-100')) {
                    setTimeout(function() {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-10px)';
                        setTimeout(() => alert.remove(), 300);
                    }, 3000);
                }
            });
        });
    </script>
</body>

</html>
