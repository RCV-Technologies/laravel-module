@extends('core::layouts.error')

@section('title', 'Module Disabled')

@section('content')
<div class="error-container">
    <div class="error-icon">
        <i class="fas fa-power-off"></i>
    </div>
    
    <h1>Module Disabled</h1>
    
    <div class="error-message">
        <p>The <strong>{{ $module }}</strong> module is currently disabled.</p>
        <p>Please contact your system administrator to enable this module.</p>
    </div>
    
    <div class="error-actions">
        <a href="{{ url()->previous() }}" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Go Back
        </a>
        
        <a href="{{ \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/') }}" class="btn btn-secondary">
            <i class="fas fa-home"></i> Go to Home
        </a>
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
    color: #dc3545;
    margin-bottom: 1rem;
}

.error-message {
    margin: 2rem 0;
    color: #6c757d;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}
</style>
@endsection 