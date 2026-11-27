@extends('users.layout.main')

@section('content')
    <section class="market-edge-landing" data-homepage>
        @include(app()->isLocale('ka') ? 'users.home-ka' : 'users.home-en')
    </section>
@endsection
