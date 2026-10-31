@extends('users.layouts.app')

@section('title', __('BetGuru | ') . $sportLabel)
@section('meta_description', 'BetGuru ' . $sportLabel . ' fixtures overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
@endpush

@section('content')
    <div data-simple-tabs data-sport="{{ $sportKey }}" class="countrywise-page">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => $sportKey])

        <section class="countrywise-main">
            <div class="countrywise-grid">
                <div class="countrywise-content">
                    <article class="countrywise-card countrywise-hero">
                        <div class="countrywise-hero-top">
                            <h1 class="countrywise-title">{{ $sportLabel }}</h1>
                        </div>

                        <div class="countrywise-main-tabs">
                            <button type="button" class="countrywise-main-tab is-active" data-simple-tab data-tab-group="main">
                                {{ __('Fixtures') }}
                            </button>
                            <button type="button" class="countrywise-main-tab" data-simple-tab data-tab-group="main">
                                {{ __('Moneyway') }}
                            </button>
                        </div>
                    </article>

                    <article class="countrywise-card countrywise-filter-bar">
                        <div class="countrywise-filter-pills">
                            <button type="button" class="countrywise-pill is-active" data-simple-tab data-tab-group="market">
                                {{ __('Live') }}
                            </button>
                            <button type="button" class="countrywise-pill" data-simple-tab data-tab-group="market">
                                {{ __('Upcoming') }}
                            </button>
                            <button type="button" class="countrywise-pill" data-simple-tab data-tab-group="market">
                                {{ __('Finished') }}
                            </button>
                        </div>
                    </article>

                    <article class="countrywise-card p-5 sm:p-6">
                        <div class="theme-text-soft text-sm">{{ $sportDescription }}</div>
                        <div class="theme-panel-subtle mt-4 rounded-2xl px-4 py-5 text-sm">
                            {{ $sportLeagueLabel }}
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>
@endsection
