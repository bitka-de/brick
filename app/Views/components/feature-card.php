<div class="card">
    <div class="card-body">
        <h5 class="card-title">{{ $feature['name'] ?? 'Feature' }}</h5>
        <p class="card-text">{{ $feature['description'] ?? 'No description available.' }}</p>
        
        @if($feature['status'] ?? false === 'ready')
            <span class="badge bg-success">✅ Ready</span>
        @else
            <span class="badge bg-warning">🚧 In Development</span>
        @endif
    </div>
</div>