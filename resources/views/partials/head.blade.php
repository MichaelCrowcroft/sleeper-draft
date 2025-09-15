<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

@if(isset($description))
<meta name="description" content="{{ $description }}">
@endif

<!-- SEO Meta Tags -->
<meta name="keywords" content="fantasy football, sleeper, fantasy football ai, sleeper draft, nfl fantasy, fantasy football chatgpt, trade evaluation, player rankings, adp, trending players">
<meta name="author" content="Sleeper Draft">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $title ?? config('app.name') }}">
@if(isset($description))
<meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:site_name" content="Sleeper Draft">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
@if(isset($description))
<meta name="twitter:description" content="{{ $description }}">
@endif

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

@production
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-1NFFKH984T"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-1NFFKH984T');
    </script>
@endproduction
