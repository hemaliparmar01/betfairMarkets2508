@extends('users.layout.main')

@section('title', __('Pinnacle Odds Drops Guide · BF Markets'))

@section('content')
    @if (app()->getLocale() === 'ka')
        @include('users.staticPages.pinnacleOddsDropsGuideGeorgian')
    @else
        @include('users.staticPages.pinnacleOddsDropsGuideEnglish')
    @endif
@endsection
