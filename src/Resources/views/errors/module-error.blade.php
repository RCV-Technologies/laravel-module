@extends('core::layouts.error')

@section('title', 'Module Error')

@section('content')
<div class="error-container">
    <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
    </div>
    
    <h1>Module Error</h1>
    
    <div class="error-message">
        <p>An error occurred in the <strong>{{ $module }}</strong> module:</p>
        <div class="error-details">
            {{ $error }}
        </div>
    </div>
    
    <div class="error-actions">
        <a href="{{ url()->previous() }}" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Go Back
        </a>
        
        <a href="{{ \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/') }}" class="btn btn-secondary">
            <i class="fas fa-home"></i> Go to Home
        </a>
        
        <button onclick="window.location.reload()" class="btn btn-info">
            <i class="fas fa-sync"></i> Retry
        </button>
    </div>
</div>

<style>
.error-container {
    text-align: center;
    padding: 2rem;
    max-width: 600px;
    margin: 0 auto;
}

.error-icon {
    font-size: 4rem;
    color: #ffc107;
    margin-bottom: 1rem;
}

.error-message {
    margin: 2rem 0;
    color: #6c757d;
}

.error-details {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 1rem;
    margin-top: 1rem;
    text-align: left;
    font-family: monospace;
    white-space: pre-wrap;
    word-break: break-word;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}
</style>
@endsection 