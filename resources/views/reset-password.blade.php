<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Reset Password</h2>
            <p class="text-gray-600 mb-6">Masukkan password baru Anda</p>
        </div>

        <!-- Loading -->
        <div id="loading" class="text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Memverifikasi token...</p>
        </div>

        <!-- Error Message -->
        <div id="error" class="hidden bg-red-50 border border-red-200 rounded-md p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error</h3>
                    <div class="mt-2 text-sm text-red-700" id="error-message"></div>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <div id="success" class="hidden bg-green-50 border border-green-200 rounded-md p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">Berhasil!</h3>
                    <div class="mt-2 text-sm text-green-700" id="success-message"></div>
                </div>
            </div>
        </div>

        <!-- Form -->
        <form id="resetForm" class="hidden space-y-4">
            <input type="hidden" id="token" name="token">
            <input type="hidden" id="email" name="email">
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password Baru</label>
                <input type="password" id="password" name="password" required 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Minimal 8 karakter">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ulangi password baru">
            </div>

            <button type="submit" id="submitBtn" 
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                <span id="submitText">Reset Password</span>
                <div id="submitLoading" class="hidden flex items-center">
                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                    Memproses...
                </div>
            </button>
        </form>

        <!-- Back to Login -->
        <div id="backToLogin" class="hidden mt-6 text-center">
            <a href="http://localhost:3000/login" class="text-blue-600 hover:text-blue-500 text-sm">
                ← Kembali ke halaman login
            </a>
        </div>
    </div>

    <script>
        console.log('Reset password page loaded');
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            const email = urlParams.get('email');
            
            console.log('Token:', token);
            console.log('Email:', email);
            
            // Validate parameters
            if (!token || !email) {
                showError('Token atau email tidak valid dalam URL');
                hideLoading();
                return;
            }
            
            // Set form values
            document.getElementById('token').value = token;
            document.getElementById('email').value = email;
            
            // Verify token
            verifyToken(token, email);
            
            // Form submit handler
            document.getElementById('resetForm').addEventListener('submit', function(e) {
                e.preventDefault();
                handleResetPassword();
            });
        });

        async function verifyToken(token, email) {
            console.log('Verifying token...');
            
            try {
                const response = await fetch('/api/v1/auth/verify-reset-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ token, email })
                });
                
                console.log('Verify response status:', response.status);
                
                const data = await response.json();
                console.log('Verify response data:', data);
                
                if (data.status === 'success') {
                    hideLoading();
                    showForm();
                } else {
                    hideLoading();
                    showError(data.message || 'Token tidak valid atau sudah kedaluwarsa');
                }
            } catch (error) {
                console.error('Verify token error:', error);
                hideLoading();
                showError('Terjadi kesalahan saat memverifikasi token');
            }
        }

        async function handleResetPassword() {
            console.log('Handling reset password...');
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            
            // Validate
            if (password.length < 8) {
                showError('Password minimal 8 karakter');
                return;
            }
            
            if (password !== confirmPassword) {
                showError('Konfirmasi password tidak cocok');
                return;
            }
            
            // Show loading
            showSubmitLoading();
            
            try {
                const response = await fetch('/api/v1/auth/reset-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        token: document.getElementById('token').value,
                        email: document.getElementById('email').value,
                        password: password,
                        password_confirmation: confirmPassword
                    })
                });
                
                console.log('Reset response status:', response.status);
                
                const data = await response.json();
                console.log('Reset response data:', data);
                
                if (data.status === 'success') {
                    hideForm();
                    showSuccess('Password berhasil direset! Anda sekarang dapat login dengan password baru.');
                    showBackToLogin();
                } else {
                    showError(data.message || 'Terjadi kesalahan saat reset password');
                }
            } catch (error) {
                console.error('Reset password error:', error);
                showError('Terjadi kesalahan saat reset password');
            } finally {
                hideSubmitLoading();
            }
        }

        function hideLoading() {
            document.getElementById('loading').classList.add('hidden');
        }

        function showForm() {
            document.getElementById('resetForm').classList.remove('hidden');
        }

        function hideForm() {
            document.getElementById('resetForm').classList.add('hidden');
        }

        function showError(message) {
            const errorDiv = document.getElementById('error');
            const errorMessage = document.getElementById('error-message');
            errorMessage.textContent = message;
            errorDiv.classList.remove('hidden');
            
            // Hide success if visible
            document.getElementById('success').classList.add('hidden');
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('success');
            const successMessage = document.getElementById('success-message');
            successMessage.textContent = message;
            successDiv.classList.remove('hidden');
            
            // Hide error if visible
            document.getElementById('error').classList.add('hidden');
        }

        function showBackToLogin() {
            document.getElementById('backToLogin').classList.remove('hidden');
        }

        function showSubmitLoading() {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitLoading = document.getElementById('submitLoading');
            
            submitBtn.disabled = true;
            submitText.classList.add('hidden');
            submitLoading.classList.remove('hidden');
        }

        function hideSubmitLoading() {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitLoading = document.getElementById('submitLoading');
            
            submitBtn.disabled = false;
            submitText.classList.remove('hidden');
            submitLoading.classList.add('hidden');
        }
    </script>
</body>
</html>