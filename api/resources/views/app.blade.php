<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="background:#050505;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CodeCraft</title>
    @inertiaHead
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="background:#050505; margin:0; padding:0; overflow:hidden;">
    @inertia
</body>
</html>
