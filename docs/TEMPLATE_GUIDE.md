# 📖 Brick View Engine - Template Development Guide

Eine umfassende Anleitung für die optimale Entwicklung von Templates mit der Brick View Engine.

## 📚 Inhaltsverzeichnis

- [🚀 Template-Grundlagen](#-template-grundlagen)
- [🏗️ Template-Vererbung & Layout-System](#️-template-vererbung--layout-system)
- [📦 Content-Management mit Sections & Stacks](#-content-management-mit-sections--stacks)
- [🔗 Template-Includes & Partials](#-template-includes--partials)
- [🛡️ Sichere Ausgabe & XSS-Schutz](#️-sichere-ausgabe--xss-schutz)
- [🔄 Control-Flow & Logik](#-control-flow--logik)
- [⚡ Performance-Optimierung](#-performance-optimierung)
- [🎨 Asset-Management](#-asset-management)
- [📁 Empfohlene Verzeichnisstruktur](#-empfohlene-verzeichnisstruktur)
- [✅ Best Practices](#-best-practices)

---

## 🚀 Template-Grundlagen

### Template-Dateien

Templates sind `.php`-Dateien mit spezieller Blade-ähnlicher Syntax:

```blade
{{-- app/Views/pages/home.php --}}
<h1>Willkommen {{ $user->name }}!</h1>

@if($posts->isNotEmpty())
    <div class="posts">
        @foreach($posts as $post)
            <article>{{ $post->title }}</article>
        @endforeach
    </div>
@endif
```

### Template-Aufrufe aus Controller

```php
// Einfaches Template rendern
return View::make('pages.home', [
    'user' => $currentUser,
    'posts' => $blogPosts
]);

// Mit Response-Integration
return View::make('dashboard.overview')
    ->with('stats', $dashboardStats)
    ->with('notifications', $userNotifications);
```

---

## 🏗️ Template-Vererbung & Layout-System

### Master-Layout erstellen

```blade
{{-- app/Views/layouts/master.php --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Meine App')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    {{-- CSS Assets stacken --}}
    @stack('styles')
</head>
<body class="@yield('body-class', '')">
    
    {{-- Navigation einbinden --}}
    @include('partials.navigation')
    
    {{-- Flash-Messages anzeigen --}}
    @if(session('success'))
        @include('partials.alert', [
            'type' => 'success', 
            'message' => session('success')
        ])
    @endif
    
    {{-- Haupt-Content-Bereich --}}
    <main class="container">
        @yield('content')
    </main>
    
    {{-- Footer --}}
    @include('partials.footer')
    
    {{-- JavaScript Assets stacken --}}
    @stack('scripts')
</body>
</html>
```

### Child-Template mit Vererbung

```blade
{{-- app/Views/pages/dashboard.php --}}
@extends('layouts.master')

@section('title', 'Dashboard - Meine App')

@section('body-class', 'dashboard-page')

{{-- CSS für diese Seite hinzufügen --}}
@push('styles')
    {!! css('assets/css/dashboard.css') !!}
    <link rel="preload" href="/fonts/dashboard-icons.woff2" as="font" type="font/woff2" crossorigin>
@endpush

@section('content')
    <div class="dashboard-header">
        <h1>Dashboard</h1>
        <div class="user-info">
            Eingeloggt als: <strong>{{ $user->name }}</strong>
        </div>
    </div>
    
    {{-- Stats-Widget einbinden --}}
    @include('components.stats-widget', ['stats' => $dashboardStats])
    
    {{-- Recent Activities --}}
    <section class="recent-activities">
        <h2>Letzte Aktivitäten</h2>
        
        @if($activities->isEmpty())
            <p class="no-activities">Noch keine Aktivitäten vorhanden.</p>
        @else
            @foreach($activities as $activity)
                @include('components.activity-item', ['activity' => $activity])
            @endforeach
        @endif
    </section>
@endsection

{{-- JavaScript für diese Seite --}}
@push('scripts')
    {!! js('assets/js/dashboard.js') !!}
    <script>
        // Dashboard initialisieren
        Dashboard.init({
            userId: {{ $user->id }},
            refreshInterval: 30000
        });
    </script>
@endpush
```

---

## 📦 Content-Management mit Sections & Stacks

### Sections für Content-Bereiche

```blade
{{-- Einfache Section --}}
@section('sidebar')
    <div class="sidebar-content">
        @include('partials.user-menu')
        @include('partials.recent-posts')
    </div>
@endsection

{{-- Inline Section (für kurze Inhalte) --}}
@section('meta-description', 'Beschreibung der aktuellen Seite für SEO')

{{-- Section mit Default-Wert --}}
@yield('sidebar', '<p>Keine Sidebar-Inhalte verfügbar.</p>')

{{-- Conditional Sections --}}
@hasSection('sidebar')
    <aside class="sidebar">
        @yield('sidebar')
    </aside>
@endif
```

### Stacks für Asset-Management

```blade
{{-- CSS-Assets sammeln --}}
@push('styles')
    {!! css('vendor/datatables/datatables.css') !!}
    {!! css('custom/table-styles.css') !!}
    <style>
        .custom-table { border-radius: 8px; }
    </style>
@endpush

{{-- JavaScript-Assets mit Reihenfolge --}}
@push('scripts')
    {!! js('vendor/jquery/jquery.min.js') !!}
@endpush

@push('scripts')
    {!! js('vendor/datatables/datatables.js') !!}
    {!! js('custom/table-init.js') !!}
@endpush

{{-- Meta-Tags stacken --}}
@push('meta-tags')
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:description" content="{{ $post->excerpt }}">
    <meta property="og:image" content="{{ $post->featured_image }}">
@endpush
```

### @once für einmalige Includes

```blade
{{-- Verhindert doppeltes Laden von Libraries --}}
@once('chartjs-library')
    @push('scripts')
        {!! js('vendor/chart.js/chart.min.js') !!}
    @endpush
@endonce

{{-- Analytics-Code nur einmal einbinden --}}
@once('google-analytics')
    @push('scripts')
        <!-- Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=GA_TRACKING_ID"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'GA_TRACKING_ID');
        </script>
    @endpush
@endonce
```

---

## 🔗 Template-Includes & Partials

### Einfache Includes

```blade
{{-- Einfacher Include --}}
@include('partials.header')

{{-- Include mit Variablen-Übergabe --}}
@include('partials.user-card', [
    'user' => $currentUser,
    'showEmail' => true,
    'size' => 'large'
])

{{-- Include mit JSON-Daten (eval-frei!) --}}
@include('components.modal', {
    "id": "confirmModal",
    "title": "Bestätigung erforderlich",
    "closable": true
})

{{-- Conditional Include --}}
@includeIf('partials.admin-toolbar', ['user' => $user])

{{-- Include mit Fallback --}}
@includeFirst(['custom.header', 'partials.header'])
```

### Wiederverwendbare Komponenten

```blade
{{-- app/Views/components/alert.php --}}
<div class="alert alert-{{ $type ?? 'info' }} {{ $dismissible ?? false ? 'alert-dismissible' : '' }}">
    @if($dismissible ?? false)
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    @endif
    
    @if(isset($icon))
        <i class="alert-icon {{ $icon }}"></i>
    @endif
    
    <div class="alert-content">
        @if(isset($title))
            <h5 class="alert-title">{{ $title }}</h5>
        @endif
        
        {{ $message }}
        
        @if(isset($actions))
            <div class="alert-actions">
                {!! $actions !!}
            </div>
        @endif
    </div>
</div>
```

### Verwendung von Komponenten

```blade
{{-- Erfolgs-Alert --}}
@include('components.alert', [
    'type' => 'success',
    'icon' => 'fas fa-check-circle',
    'title' => 'Erfolgreich gespeichert!',
    'message' => 'Ihre Änderungen wurden erfolgreich übernommen.',
    'dismissible' => true
])

{{-- Warnung mit Aktionen --}}
@include('components.alert', [
    'type' => 'warning',
    'message' => 'Ihr Account läuft bald ab.',
    'actions' => '<a href="/upgrade" class="btn btn-primary">Jetzt upgraden</a>'
])
```

---

## 🛡️ Sichere Ausgabe & XSS-Schutz

### Escaped vs. Unescaped Output

```blade
{{-- ✅ SICHER: Auto-escaped (empfohlen) --}}
<h1>{{ $userInput }}</h1>
<p>Kommentar: {{ $comment->text }}</p>

{{-- ⚠️ VORSICHT: Unescaped (nur für vertrauenswürdigen Content) --}}
<div class="content">{!! $trustedHtml !!}</div>
<div class="markdown">{!! $processedMarkdown !!}</div>

{{-- ✅ Sichere HTML-Attribute --}}
<input type="text" value="{{ $formData['name'] ?? '' }}">
<img src="{{ $imageUrl }}" alt="{{ $imageAlt }}">

{{-- ✅ JSON-Daten sicher ausgeben --}}
<script>
    const userData = @json($user);
    const config = @json($appConfig);
</script>
```

### @verbatim für Raw Content

```blade
{{-- JavaScript-Code ohne Template-Parsing --}}
@verbatim
    <script>
        // Vue.js Template-Syntax wird nicht geparst
        new Vue({
            el: '#app',
            template: `
                <div>
                    <h1>{{ title }}</h1>
                    <p>{{ message }}</p>
                </div>
            `
        });
    </script>
@endverbatim

{{-- Blade-Syntax in Code-Beispielen anzeigen --}}
@verbatim
    <pre><code>
        {{-- Dieser Code wird nicht kompiliert --}}
        @if($condition)
            {{ $variable }}
        @endif
    </code></pre>
@endverbatim
```

---

## 🔄 Control-Flow & Logik

### Conditional Rendering

```blade
{{-- If-Else-Strukturen --}}
@if($user->isAdmin())
    <div class="admin-panel">
        @include('admin.toolbar')
    </div>
@elseif($user->isModerator())
    <div class="moderator-tools">
        @include('moderator.actions')
    </div>
@else
    <div class="user-content">
        Willkommen, {{ $user->name }}!
    </div>
@endif

{{-- Kompakte Conditionals --}}
@auth
    <p>Eingeloggt als: {{ auth()->user()->name }}</p>
@endauth

@guest
    <a href="/login">Anmelden</a>
@endguest

@env('production')
    <!-- Production-spezifischer Code -->
@endenv

{{-- Existenz-Prüfungen --}}
@isset($variable)
    <p>Variable ist gesetzt: {{ $variable }}</p>
@endisset

@empty($collection)
    <p>Keine Daten vorhanden.</p>
@endempty
```

### Schleifen & Iteration

```blade
{{-- Foreach mit Objekten --}}
@foreach($posts as $post)
    <article class="post">
        <h2>{{ $post->title }}</h2>
        <p>{{ $post->excerpt }}</p>
        
        {{-- Loop-Variablen nutzen --}}
        @if($loop->first)
            <span class="badge">Neuester Beitrag</span>
        @endif
        
        @if($loop->last)
            <hr>
        @endif
    </article>
@endforeach

{{-- Foreach mit leerer Fallback --}}
@forelse($comments as $comment)
    @include('partials.comment', ['comment' => $comment])
@empty
    <p class="no-comments">Noch keine Kommentare vorhanden.</p>
@endforelse

{{-- For-Schleifen für Zahlen-Bereiche --}}
@for($i = 1; $i <= $totalPages; $i++)
    <a href="?page={{ $i }}" 
       class="{{ $i === $currentPage ? 'active' : '' }}">
        {{ $i }}
    </a>
@endfor

{{-- While-Schleifen --}}
@while($condition)
    <!-- Wiederholender Content -->
@endwhile
```

### Erweiterte Loop-Features

```blade
@foreach($products as $product)
    <div class="product {{ $loop->iteration % 2 === 0 ? 'even' : 'odd' }}">
        <h3>{{ $product->name }}</h3>
        
        {{-- Loop-Status-Checks --}}
        @if($loop->first && $loop->count > 5)
            <div class="featured">Empfohlenes Produkt</div>
        @endif
        
        {{-- Nested Loops --}}
        @if($product->variants->isNotEmpty())
            <div class="variants">
                @foreach($product->variants as $variant)
                    <span class="variant">
                        {{ $variant->name }}
                        {{-- Parent Loop zugreifen --}}
                        ({{ $loop->parent->iteration }}.{{ $loop->iteration }})
                    </span>
                @endforeach
            </div>
        @endif
        
        {{-- Trennlinien zwischen Items --}}
        @unless($loop->last)
            <hr class="item-separator">
        @endunless
    </div>
@endforeach
```

---

## ⚡ Performance-Optimierung

### Template-Caching

```php
// Cache aktivieren (empfohlen für Production)
View::cache(true);

// Cache-Verzeichnis anpassen
View::setCachePath('/custom/cache/path');

// Cache manuell leeren
View::clearCache();
```

### Lazy Loading für schwere Includes

```blade
{{-- Schwere Komponenten nur bei Bedarf laden --}}
@if($showExpensiveWidget)
    @include('widgets.expensive-chart', ['data' => $chartData])
@endif

{{-- Conditional Asset Loading --}}
@if($needsDataTables)
    @once('datatables-assets')
        @push('styles')
            {!! css('vendor/datatables/datatables.css') !!}
        @endpush
        @push('scripts')
            {!! js('vendor/datatables/datatables.js') !!}
        @endpush
    @endonce
@endif
```

### Effiziente Datenübergabe

```blade
{{-- ✅ GUT: Nur benötigte Daten übergeben --}}
@include('partials.user-info', [
    'userName' => $user->name,
    'userEmail' => $user->email
])

{{-- ❌ SCHLECHT: Ganze Objekte übergeben wenn nicht nötig --}}
@include('partials.user-info', ['user' => $user])

{{-- ✅ GUT: Collections vorfiltern --}}
@include('components.post-list', [
    'posts' => $posts->where('published', true)->take(10)
])
```

---

## 🎨 Asset-Management

### Asset-Helper verwenden

```blade
{{-- CSS mit automatischer Versionierung --}}
{!! css('assets/css/app.css') !!}
{!! css('vendor/bootstrap/bootstrap.min.css', ['integrity' => 'sha384-...']) !!}

{{-- JavaScript mit Attributen --}}
{!! js('assets/js/app.js', ['defer' => true]) !!}
{!! js('vendor/jquery.min.js', ['async' => false]) !!}

{{-- Bilder und andere Assets --}}
<img src="{{ asset('images/logo.png') }}" alt="Logo">
<link rel="icon" href="{{ asset('favicon.ico') }}">
```

### Asset-Stacking für optimale Performance

```blade
{{-- Critical CSS inline --}}
@push('critical-styles')
    <style>
        /* Above-the-fold CSS */
        .header { display: flex; }
        .hero { min-height: 50vh; }
    </style>
@endpush

{{-- Non-critical CSS defer --}}
@push('deferred-styles')
    <link rel="preload" href="{{ asset('css/non-critical.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset('css/non-critical.css') }}"></noscript>
@endpush

{{-- JavaScript-Reihenfolge beachten --}}
@push('scripts')
    {!! js('vendor/jquery.min.js') !!}
@endpush

@push('scripts')
    {!! js('vendor/bootstrap.bundle.min.js') !!}
@endpush

@push('scripts')
    {!! js('custom/app.js') !!}
@endpush
```

---

## 📁 Empfohlene Verzeichnisstruktur

```
app/Views/
├── layouts/                    # Master-Templates
│   ├── master.php             # Haupt-Layout
│   ├── auth.php               # Layout für Login/Register
│   ├── admin.php              # Admin-Interface Layout
│   └── error.php              # Error-Page Layout
│
├── pages/                     # Vollständige Seiten
│   ├── home.php
│   ├── about.php
│   ├── contact.php
│   └── dashboard/
│       ├── index.php
│       ├── profile.php
│       └── settings.php
│
├── partials/                  # Wiederverwendbare Teile
│   ├── header.php
│   ├── navigation.php
│   ├── footer.php
│   ├── breadcrumbs.php
│   └── pagination.php
│
├── components/                # UI-Komponenten
│   ├── alert.php
│   ├── modal.php
│   ├── button.php
│   ├── form/
│   │   ├── input.php
│   │   ├── select.php
│   │   └── textarea.php
│   └── data/
│       ├── table.php
│       └── card.php
│
├── emails/                    # E-Mail Templates
│   ├── layouts/
│   │   └── mail.php
│   ├── auth/
│   │   ├── verify.php
│   │   └── reset-password.php
│   └── notifications/
│       └── welcome.php
│
└── errors/                    # Error-Seiten
    ├── 404.php
    ├── 500.php
    └── maintenance.php
```

---

## ✅ Best Practices

### 1. Template-Organisation

```blade
{{-- ✅ Klare Struktur mit Kommentaren --}}
{{-- app/Views/pages/product.php --}}
@extends('layouts.master')

{{-- Meta-Daten --}}
@section('title', $product->name . ' - Shop')
@section('meta-description', $product->description)

{{-- Page-spezifische Assets --}}
@push('styles')
    {!! css('assets/css/product.css') !!}
@endpush

{{-- Haupt-Content --}}
@section('content')
    {{-- Breadcrumbs --}}
    @include('partials.breadcrumbs', ['items' => $breadcrumbs])
    
    {{-- Produkt-Details --}}
    <div class="product-container">
        @include('components.product-gallery', ['images' => $product->images])
        @include('components.product-info', ['product' => $product])
    </div>
    
    {{-- Verwandte Produkte --}}
    @if($relatedProducts->isNotEmpty())
        @include('sections.related-products', ['products' => $relatedProducts])
    @endif
@endsection

{{-- JavaScript --}}
@push('scripts')
    {!! js('assets/js/product.js') !!}
@endpush
```

### 2. Sichere Datenbehandlung

```blade
{{-- ✅ Input-Validierung visualisieren --}}
<div class="form-group {{ $errors->has('email') ? 'has-error' : '' }}">
    <label for="email">E-Mail</label>
    <input type="email" 
           id="email" 
           name="email" 
           value="{{ old('email', $user->email ?? '') }}"
           class="form-control">
    
    @if($errors->has('email'))
        <div class="error-message">{{ $errors->first('email') }}</div>
    @endif
</div>

{{-- ✅ CSRF-Protection --}}
<form method="POST" action="/update-profile">
    @csrf
    @method('PUT')
    <!-- Form fields -->
</form>
```

### 3. Responsive & Accessibility

```blade
{{-- ✅ Responsive Bilder --}}
<picture>
    <source media="(max-width: 768px)" srcset="{{ asset('images/hero-mobile.jpg') }}">
    <source media="(max-width: 1024px)" srcset="{{ asset('images/hero-tablet.jpg') }}">
    <img src="{{ asset('images/hero-desktop.jpg') }}" 
         alt="{{ $hero->alt_text }}"
         loading="lazy">
</picture>

{{-- ✅ Accessibility-Features --}}
<button type="button" 
        class="btn btn-primary"
        aria-label="Produkt {{ $product->name }} zum Warenkorb hinzufügen"
        data-product-id="{{ $product->id }}">
    <span aria-hidden="true">🛒</span>
    In den Warenkorb
</button>

{{-- ✅ Skip-Navigation --}}
<a href="#main-content" class="skip-link">Zum Hauptinhalt springen</a>
```

### 4. Performance-Optimierungen

```blade
{{-- ✅ Preload wichtiger Assets --}}
@push('meta-tags')
    <link rel="preload" href="{{ asset('fonts/primary.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('css/critical.css') }}" as="style">
@endpush

{{-- ✅ Lazy Loading für Images --}}
<img src="{{ asset('images/placeholder.jpg') }}" 
     data-src="{{ $image->url }}" 
     alt="{{ $image->alt }}"
     loading="lazy"
     class="lazy-load">

{{-- ✅ Conditional Resource Loading --}}
@if($page->needsMapWidget)
    @once('google-maps')
        @push('scripts')
            <script async defer 
                    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&callback=initMap">
            </script>
        @endpush
    @endonce
@endif
```

### 5. Debugging & Development

```blade
{{-- Template-Debugging (nur in Development) --}}
@env('local')
    <!-- DEBUG: Template = pages.dashboard -->
    <!-- DEBUG: Data = @json(array_keys(get_defined_vars())) -->
@endenv

{{-- Conditional Development Tools --}}
@if(config('app.debug'))
    @include('debug.template-info', [
        'template' => 'pages.dashboard',
        'data' => get_defined_vars()
    ])
@endif
```

---

## 🎯 Fazit

Mit diesen Patterns und Best Practices können Sie:

- **Wartbare Templates** mit klarer Struktur entwickeln
- **Performance-optimierte** Anwendungen erstellen  
- **Sichere** XSS-geschützte Ausgaben gewährleisten
- **Wiederverwendbare Komponenten** effizient nutzen
- **Responsive & accessible** Benutzeroberflächen bauen

Die Brick View Engine bietet alle Werkzeuge für professionelle Template-Entwicklung - nutzen Sie sie optimal! 🚀