<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

        @vite(['themes/tailwind/css/app.css', 'themes/tailwind/js/app.js'], 'tailwind')
    </head>
    <body>
        <div id="app" class="font-sans antialiased text-gray-900">
            {{ $slot }}
        </div>
    </body>
    <script>
        function handleRoleChange(role) {
            const isWorker = role === 'guest';
            const fields = ['password', 'password-confirm', 'photo'];

            fields.forEach(field => {
                const input = document.getElementById(field);
                input.disabled = isWorker;
                input.classList.toggle('deactivated', isWorker);

                if (isWorker) {
                    input.value = '';
                }
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const passwordInput = document.getElementById('password');

            function updatePasswordField() {
                const selectedRole = roleSelect.value;
                if (selectedRole === 'guest') {
                    passwordInput.disabled = true;
                    passwordInput.classList.add('disabled-input'); // Add CSS class when disabled
                } else {
                    passwordInput.disabled = false;
                    passwordInput.classList.remove('disabled-input'); // Remove CSS class when enabled
                }
            }

            // Initialize the password field state
            updatePasswordField();

            // Update the password field state when the role changes
            roleSelect.addEventListener('change', updatePasswordField);
        });


    </script>
    <style>
        .deactivated {
            background-color: #f3f3f3;
            border-color: #d1d5db;
            cursor: not-allowed;
        }
        .disabled-input {
            background-color: #f3f4f6; 
            color: #9ca3af; 
            cursor: not-allowed;
        }
    </style>
</html>
