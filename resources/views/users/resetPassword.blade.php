@extends('users.layout.main')

@section('title', __('Reset password · BF Markets'))

@push('head')
    @vite('resources/users/js/auth/app.js')
@endpush

@section('content')
    <section class="customer-auth mx-auto my-8 max-w-lg">
        <div class="betguru-card-elevated rounded-3xl p-6 sm:p-8">
            <h1 class="mb-3 text-2xl font-bold theme-text-main">{{ __('Reset password') }}</h1>
            <p class="mb-5 text-sm theme-text-muted">{{ __('Choose a new password for your BF Markets account.') }}</p>
            @if (session('status'))
                <p role="status" class="mb-4 rounded-xl p-3 theme-alert-success">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-4 rounded-xl p-3 theme-text-danger">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('customers.password.update') }}" class="space-y-4">
                @csrf
                <label class="betguru-label" for="email">{{ __('Email address') }}</label>
                <input class="betguru-input" id="email" name="email" type="email" value="{{ old('email', $email ?? '') }}" autocomplete="email" required>
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="betguru-label" for="password">{{ __('New password') }}</label>
                <input class="betguru-input" id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
                <label class="betguru-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                <input class="betguru-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                <button class="betguru-btn-primary" type="submit">{{ __('Reset password') }}</button>
            </form>
            <a href="{{ route('login') }}" class="betguru-link mt-5 inline-block">{{ __('Back to login') }}</a>
        </div>
    </section>
@endsection
