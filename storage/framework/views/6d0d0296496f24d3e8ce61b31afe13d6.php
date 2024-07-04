<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

        <title><?php echo e(config('app.name', 'Laravel')); ?></title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

        <?php echo app('Illuminate\Foundation\Vite')(['themes/tailwind/css/app.css', 'themes/tailwind/js/app.js'], 'tailwind'); ?>
    </head>
    <body>
        <div id="app" class="font-sans antialiased text-gray-900">
            <?php echo e($slot); ?>

        </div>
    </body>
</html>
<?php /**PATH /home/jay/Downloads/laravel/chatApplication/themes/tailwind/views/layouts/guest.blade.php ENDPATH**/ ?>