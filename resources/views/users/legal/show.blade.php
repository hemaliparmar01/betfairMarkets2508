@extends('users.layouts.app', ['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'), 'moneywayUrl' => route('football.moneyway.page')])

@section('title', $pageTitle . ' | BF Markets')
{{-- @section('meta_description', $metaDescription) --}}

<style>
    .policy-content > :first-child {
        margin-top: 0 !important;
    }

    .policy-content p {
        margin: 0 0 1rem;
    }

    .policy-content ol {
        list-style: decimal;
        padding-left: 1.5rem;
        margin: 0 0 1rem;
    }

    .policy-content ul {
        list-style: disc;
        padding-left: 1.5rem;
        margin: 0 0 1rem;
    }

    .policy-content li {
        margin-bottom: 0.5rem;
    }

    .policy-content .ql-ui {
        display: none;
    }
</style>

@section('content')
    @php
        function convertQuillLists($html)
        {
            if (blank($html)) {
                return $html;
            }

            $html = preg_replace('/<span class="ql-ui"[^>]*><\/span>/', '', $html);

            $html = preg_replace_callback('/<ol>(.*?)<\/ol>/is', function ($matches) {
                $listHtml = $matches[1];

                if (str_contains($listHtml, 'data-list="bullet"')) {
                    $listHtml = str_replace(' data-list="bullet"', '', $listHtml);
                    return '<ul>' . $listHtml . '</ul>';
                }

                $listHtml = str_replace(' data-list="ordered"', '', $listHtml);
                return '<ol>' . $listHtml . '</ol>';
            }, $html);

            return $html;
        }

        $pageContent = convertQuillLists($pageContent);
    @endphp

    <div class="betguru-auth-bg min-h-screen">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <div class="rounded-[2rem] p-6 sm:p-8 lg:p-10 betguru-card-elevated">
                    {{-- <span class="betguru-pill">{{ __($pageTitle) }}</span> --}}

                    <h1 class="mt-5 text-3xl font-bold theme-text-main sm:text-4xl">
                        {{ __($pageTitle) }}
                    </h1>

                    {{-- <p class="mt-3 text-sm theme-text-muted">
                        {{ __($metaDescription) }}
                    </p> --}}

                    <div class="mt-8 rounded-[1.5rem] p-5 sm:p-6 betguru-card-soft">
                        @if (filled($pageContent))
                            <div class="policy-content text-sm leading-8 theme-text-soft">
                                {!! $pageContent !!}
                            </div>
                        @else
                            <p class="text-sm theme-text-muted">
                                {{ __('This content will be updated soon.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
