<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/">
            <img src="{{ asset('logo.png') }}" alt="{{ $appName ?? 'Brick' }}" width="30">
            {{ $appName ?? 'Brick Framework' }}
        </a>
        
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="/">Home</a>
            <a class="nav-link" href="/about">About</a>
            
            @if($currentUser ?? false)
                <a class="nav-link" href="/dashboard">Dashboard</a>
                <a class="nav-link" href="/logout">Logout ({{ $currentUser->name }})</a>
            @else
                <a class="nav-link" href="/login">Login</a>
            @endif
        </div>
    </div>
</nav>