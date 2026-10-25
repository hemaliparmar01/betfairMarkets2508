@extends('admin.layouts.main', ['active' => 'cricket_countries'])

@section('title', 'Add Cricket Country | Admin')

@section('content')
    <div class="min-h-screen px-4 py-6 sm:px-6 lg:px-8 theme-bg-base">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                    Admin Panel
                </p>
                <h1 class="mt-2 text-3xl font-bold theme-text-main">
                    Add Cricket Country
                </h1>
                <p class="mt-2 text-sm theme-text-muted">
                    Create a cricket country item for the user-side leagues filter.
                </p>
            </div>

            <div class="rounded-[2rem] p-6 betguru-card-elevated">
                @if(session('error'))
                    <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-chip-danger">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-5 rounded-2xl px-4 py-3 text-sm theme-chip-danger">
                        <p class="font-semibold">Please fix the highlighted fields.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.cricket_countries.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold theme-text-main">Country Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="betguru-input" placeholder="Argentina">
                            @error('name')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        @foreach(translation_form_locales() as $locale => $localeLabel)
                            <div>
                                <label class="mb-2 block text-sm font-semibold theme-text-main">Country Name ({{ $localeLabel }})</label>
                                <input type="text" name="name_translations[{{ $locale }}]" value="{{ old("name_translations.$locale") }}" class="betguru-input" placeholder="Translated country name">
                                @error("name_translations.$locale")
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach

                        <div>
                            <label class="mb-2 block text-sm font-semibold theme-text-main">Slug</label>
                            <input type="text" name="slug" value="{{ old('slug') }}" class="betguru-input" placeholder="ar">
                            @error('slug')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold theme-text-main">Key</label>
                            <input type="text" name="key" value="{{ old('key') }}" class="betguru-input" placeholder="argentina">
                            <p class="mt-2 text-xs theme-text-muted">Optional. If blank, it will be generated from the country name.</p>
                            @error('key')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold theme-text-main">Flag Key</label>
                            <input type="text" name="flag" value="{{ old('flag') }}" class="betguru-input" placeholder="argentina">
                            <p class="mt-2 text-xs theme-text-muted">Optional. If blank, it will follow the key value.</p>
                            @error('flag')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold theme-text-main">Status</label>
                            <select name="is_active" class="betguru-select">
                                <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('is_active') == 0 ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('is_active')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="submit" class="rounded-2xl px-5 py-3 text-sm font-semibold text-white betguru-gradient-cta">
                            Save Country
                        </button>

                        <a href="{{ route('admin.cricket_countries.index') }}" class="rounded-2xl px-5 py-3 text-sm font-semibold theme-panel-subtle">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
