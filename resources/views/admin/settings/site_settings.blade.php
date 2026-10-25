@extends('admin.layouts.main', ['active' => 'site_settings'])

@section('title', 'Site Settings | Admin')

@section('content')
    <div class="min-h-screen px-4 py-6 sm:px-6 lg:px-8 theme-bg-base">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                    Settings
                </p>
                <h1 class="mt-2 text-3xl font-bold theme-text-main">
                    Logo Settings
                </h1>
                <p class="mt-2 text-sm theme-text-muted">
                    Update the website name, tagline, logos, and browser tab icon from one place.
                </p>
            </div>

            @if (session('success'))
                <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-chip-success">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.site_settings.update') }}" enctype="multipart/form-data"
                class="space-y-6 rounded-[2rem] p-6 betguru-card-elevated">
                @csrf

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <label for="site_name" class="betguru-label">Website Name</label>
                        <input id="site_name" name="site_name" type="text"
                            value="{{ old('site_name', $settings['site_name'] ?? '') }}" class="betguru-input"
                            placeholder="Enter website name">
                        @error('site_name')
                            <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    @foreach(translation_form_locales() as $locale => $localeLabel)
                        <div>
                            <label class="betguru-label">Website Name ({{ $localeLabel }})</label>
                            <input type="text" name="site_name_translations[{{ $locale }}]" value="{{ old("site_name_translations.$locale", data_get(json_decode($settings['site_name_translations']), $locale)) }}" placeholder="Translated Website Name" class="betguru-input">

                            @error("site_name_translations.$locale")
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div>
                        <label for="tagline" class="betguru-label">Short Tagline</label>
                        <input id="tagline" name="tagline" type="text"
                            value="{{ old('tagline', $settings['tagline'] ?? '') }}" class="betguru-input"
                            placeholder="Enter short tagline">
                        @error('tagline')
                            <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    @foreach(translation_form_locales() as $locale => $localeLabel)
                        <div>
                            <label class="betguru-label">Website Name ({{ $localeLabel }})</label>
                            <input type="text" name="tagline_translations[{{ $locale }}]" value="{{ old("tagline_translations.$locale", data_get(json_decode($settings['tagline_translations']), $locale)) }}" placeholder="Translated Website Name" class="betguru-input">
                            @error("tagline_translations.$locale")
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                        <div>
                            <h2 class="text-lg font-semibold theme-text-main">Main Logo</h2>
                            <p class="mt-1 text-sm theme-text-muted">
                                This logo appears in the website header.
                            </p>
                        </div>

                        <div
                            class="flex h-28 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            @if (!empty($settings['logo']))
                                <img src="{{ asset('storage/' . $settings['logo']) }}" alt="Main logo preview"
                                    class="max-h-full max-w-full object-contain">
                            @else
                                <span class="text-sm theme-text-muted">No logo uploaded yet.</span>
                            @endif
                        </div>

                        <div>
                            <label for="logo" class="betguru-label">Upload Main Logo</label>
                            <input id="logo" name="logo" type="file"
                                class="betguru-input file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                            @error('logo')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                        <div>
                            <h2 class="text-lg font-semibold theme-text-main">Dark Theme Logo</h2>
                            <p class="mt-1 text-sm theme-text-muted">
                                Use this logo where a dark version is needed.
                            </p>
                        </div>

                        <div
                            class="flex h-28 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            @if (!empty($settings['dark_logo']))
                                <img src="{{ asset('storage/' . $settings['dark_logo']) }}" alt="Dark logo preview"
                                    class="max-h-full max-w-full object-contain">
                            @else
                                <span class="text-sm theme-text-muted">No dark logo uploaded yet.</span>
                            @endif
                        </div>

                        <div>
                            <label for="dark_logo" class="betguru-label">Upload Dark Theme Logo</label>
                            <input id="dark_logo" name="dark_logo" type="file"
                                class="betguru-input file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                            @error('dark_logo')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                        <div>
                            <h2 class="text-lg font-semibold theme-text-main">Browser Tab Icon</h2>
                            <p class="mt-1 text-sm theme-text-muted">
                                This icon appears in the browser tab and bookmarks.
                            </p>
                        </div>

                        <div
                            class="flex h-28 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            @if (!empty($settings['favicon']))
                                <img src="{{ asset('storage/' . $settings['favicon']) }}" alt="Favicon preview"
                                    class="max-h-16 max-w-16 object-contain">
                            @else
                                <span class="text-sm theme-text-muted">No browser tab icon uploaded yet.</span>
                            @endif
                        </div>

                        <div>
                            <label for="favicon" class="betguru-label">Upload Browser Tab Icon</label>
                            <input id="favicon" name="favicon" type="file"
                                class="betguru-input file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                            @error('favicon')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @foreach (translation_form_locales() as $locale => $localeLabel)
                        <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                            <div>
                                <h2 class="text-lg font-semibold theme-text-main">Main Logo ({{ $localeLabel }})</h2>
                                <p class="mt-1 text-sm theme-text-muted">
                                    This logo appears in the website header.
                                </p>
                            </div>

                            <div class="flex h-28 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                                @if (!empty($settings['logo']))
                                    <img src="{{ asset('storage/' . data_get(json_decode($settings['logo_translations']), $locale)) }}" alt="Main logo preview" class="max-h-full max-w-full object-contain">
                                @else
                                    <span class="text-sm theme-text-muted">No logo uploaded yet.</span>
                                @endif
                            </div>

                            <div>
                                <label for="logo" class="betguru-label">Upload Main Logo</label>
                                <input id="logo" name="logo_translations[{{ $locale }}]" type="file" class="betguru-input file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                                @error('logo_translations')
                                    <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                            <div>
                                <h2 class="text-lg font-semibold theme-text-main">Dark Theme Logo ({{ $localeLabel }})</h2>
                                <p class="mt-1 text-sm theme-text-muted">
                                    Use this logo where a dark version is needed.
                                </p>
                            </div>

                            <div
                                class="flex h-28 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                                @if (!empty($settings['dark_logo']))
                                    <img src="{{ asset('storage/' . data_get(json_decode($settings['dark_logo_translations']), $locale)) }}" alt="Dark logo preview" class="max-h-full max-w-full object-contain">
                                @else
                                    <span class="text-sm theme-text-muted">No dark logo uploaded yet.</span>
                                @endif
                            </div>

                            <div>
                                <label for="dark_logo" class="betguru-label">Upload Dark Theme Logo</label>
                                <input id="dark_logo" name="dark_logo_translations[{{ $locale }}]" type="file"
                                    class="betguru-input file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                                @error('dark_logo')
                                    <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                    <div>
                        <h2 class="text-lg font-semibold theme-text-main">Betfair API key</h2>
                    </div>
                    <div class="grid gap-2 xl:grid-cols-1">
                        <div>
                            <label for="frontend_api_key" class="betguru-label">Betfair API Key</label>
                            <input id="frontend_api_key" name="frontend_api_key" type="text"
                                value="{{ old('frontend_api_key', $settings['frontend_api_key'] ?? '') }}"
                                class="betguru-input" placeholder="Enter Betair API Key">
                            @error('frontend_api_key')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                    <div>
                        <h2 class="text-lg font-semibold theme-text-main">Paypal payments</h2>
                    </div>
                    <div class="grid gap-2 xl:grid-cols-2">
                        <div>
                            <label for="paypal_id" class="betguru-label">Paypal ID</label>
                            <input id="paypal_id" name="paypal_id" type="text"
                                value="{{ old('paypal_id', $settings['paypal_id'] ?? '') }}" class="betguru-input"
                                placeholder="Paypal ID">
                            @error('paypal_id')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="paypal_secret" class="betguru-label">Paypal Secret</label>
                            <input id="paypal_secret" name="paypal_secret" type="text"
                                value="{{ old('paypal_secret', $settings['paypal_secret'] ?? '') }}"
                                class="betguru-input" placeholder="Paypal Secret">
                            @error('paypal_secret')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="grid gap-2 xl:grid-cols-1 mt-5">
                        <div>
                            <label for="paypal_base_url" class="betguru-label">Paypal Base URL</label>
                            <input id="paypal_base_url" name="paypal_base_url" type="text"
                                value="{{ old('paypal_base_url', $settings['paypal_base_url'] ?? '') }}"
                                class="betguru-input" placeholder="Paypal base url">
                            @error('paypal_base_url')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                    <div>
                        <h2 class="text-lg font-semibold theme-text-main">Bank Of Georgia Payment</h2>
                    </div>
                    <div class="grid gap-2 xl:grid-cols-2">
                        <div>
                            <label for="bog_client_id" class="betguru-label">BOG Client Id</label>
                            <input id="bog_client_id" name="bog_client_id" type="text"
                                value="{{ old('bog_client_id', $settings['bog_client_id'] ?? '') }}"
                                class="betguru-input" placeholder="BOG client Id">
                            @error('bog_client_id')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="bog_secret" class="betguru-label">BOG Secret</label>
                            <input id="bog_secret" name="bog_secret" type="text"
                                value="{{ old('bog_secret', $settings['bog_secret'] ?? '') }}" class="betguru-input"
                                placeholder="Enter Secret key">
                            @error('bog_secret')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mt-3">
                            <label for="bog_auth_url" class="betguru-label">BOG Auth URL</label>
                            <input id="bog_auth_url" name="bog_auth_url" type="text"
                                value="{{ old('bog_auth_url', $settings['bog_auth_url'] ?? '') }}" class="betguru-input"
                                placeholder="BOG Auth URL">
                            @error('bog_auth_url')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mt-3">
                            <label for="bog_base_url" class="betguru-label">BOG Base URL</label>
                            <input id="bog_base_url" name="bog_base_url" type="text"
                                value="{{ old('bog_base_url', $settings['bog_base_url'] ?? '') }}" class="betguru-input"
                                placeholder="BOG Base URL">
                            @error('bog_base_url')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                    <div>
                        <h2 class="text-lg font-semibold theme-text-main">TBC Payment</h2>
                    </div>
                    <div class="grid gap-2 xl:grid-cols-2">
                        <div>
                            <label for="tbc_merchant_id" class="betguru-label">TBC Merchant Id</label>
                            <input id="tbc_merchant_id" name="tbc_merchant_id" type="text"
                                value="{{ old('tbc_merchant_id', $settings['tbc_merchant_id'] ?? '') }}"
                                class="betguru-input" placeholder="TBC Payment">
                            @error('tbc_merchant_id')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="tbc_secret_key" class="betguru-label">TBC Secret Key</label>
                            <input id="tbc_secret_key" name="tbc_secret_key" type="text"
                                value="{{ old('tbc_secret_key', $settings['tbc_secret_key'] ?? '') }}"
                                class="betguru-input" placeholder="TBC Secret key">
                            @error('tbc_secret_key')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mt-3">
                            <label for="tbc_base_url" class="betguru-label">TBC Base URL</label>
                            <input id="tbc_base_url" name="tbc_base_url" type="text"
                                value="{{ old('tbc_base_url', $settings['tbc_base_url'] ?? '') }}" class="betguru-input"
                                placeholder="TBC Base URL">
                            @error('tbc_base_url')
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end">
                    <button type="submit"
                        class="rounded-2xl px-6 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 betguru-gradient-cta">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
