@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="dark">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen font-sans antialiased">
    <main class="mx-auto max-w-[750px] px-5 py-12 sm:py-16">
        {{ $slot }}
    </main>
</body>
</html>
