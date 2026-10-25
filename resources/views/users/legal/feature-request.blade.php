@extends('users.layouts.app', [
    'activeHeaderUrl' => 'fixtures',
    'fixtuesUrl' => route('home.en'),
    'moneywayUrl' => route('football.moneyway.page'),
])

@section('title', 'Feature Request | BF Markets')

@section('content')
    <section class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-[80%] rounded-[32px] border border-[rgb(var(--border))] bg-[rgb(var(--surface))] p-6 shadow-sm sm:p-8">
            <div>
                <h1 class="text-2xl font-semibold text-[rgb(var(--text-main))] sm:text-3xl">{{__('Feature Request')}}</h1>
                <p class="mt-2 text-sm text-[rgb(var(--text-muted))]">
                    {{__('Share your idea with us. We will store it and send it to the admin for review.')}}
                </p>
            </div>

            @if (session('success'))
                <div class="mt-6 rounded-2xl border border-[rgb(var(--success)_/_0.25)] bg-[rgb(var(--success)_/_0.10)] px-4 py-3 text-sm font-medium text-[rgb(var(--success))]">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('feature.request.store') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-[rgb(var(--text-soft))]">{{ __('Email') }}</label>
                    <input id="email" type="text" name="email" value="{{ old('email', auth('customer')->user()->email ?? '') }}" placeholder="{{__('Enter your email')}}" class="w-full rounded-2xl border border-[rgb(var(--border))] px-4 py-3 text-sm text-[rgb(var(--text-main))] outline-none transition focus:border-[rgb(var(--primary))] focus:ring-2 focus:ring-[rgb(var(--ring))]">
                    @error('email')
                        <p class="mt-2 text-sm text-[rgb(var(--danger))]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="message" class="mb-2 block text-sm font-medium text-[rgb(var(--text-soft))]">
                        {{ __('Feature Request') }}
                    </label>
                    <textarea
                        id="message"
                        name="message"
                        rows="7"
                        placeholder="{{ __('Write your feature request here') }}"
                        class="w-full rounded-2xl border border-[rgb(var(--border))] px-4 py-3 text-sm text-[rgb(var(--text-main))] outline-none transition focus:border-[rgb(var(--primary))] focus:ring-2 focus:ring-[rgb(var(--ring))]"
                    >{{ old('message') }}</textarea>
                    @error('message')
                        <p class="mt-2 text-sm text-[rgb(var(--danger))]">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-2xl bg-[rgb(var(--primary))] px-6 py-3 text-sm font-semibold text-[rgb(var(--surface))] transition hover:bg-[rgb(var(--primary-strong))]"
                >
                    {{ __('Submit Request') }}
                </button>
            </form>
        </div>
    </section>
@endsection
