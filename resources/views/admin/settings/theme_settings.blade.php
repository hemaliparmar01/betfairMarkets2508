@extends('admin.layouts.main', ['active' => 'theme_settings'])

@section('content')
    <div x-data="themeColorSettings()" class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Website Color Settings</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Update the website colors for light and dark mode from one place.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('admin.theme_settings.default') }}">
                    @csrf

                    <button
                        type="submit"
                        class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Default Setting
                    </button>
                </form>

                <div class="rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
                    <button
                        type="button"
                        @click="activeTheme = 'light'"
                        :class="activeTheme === 'light' ? 'bg-sky-500 text-white' : 'text-slate-700'"
                        class="rounded-xl px-5 py-2 text-sm font-semibold transition"
                    >
                        Light
                    </button>

                    <button
                        type="button"
                        @click="activeTheme = 'dark'"
                        :class="activeTheme === 'dark' ? 'bg-slate-900 text-white' : 'text-slate-700'"
                        class="rounded-xl px-5 py-2 text-sm font-semibold transition"
                    >
                        Dark
                    </button>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.theme_settings.update') }}">
            @csrf

            <template x-for="mode in ['light', 'dark']" :key="mode">
                <div x-show="activeTheme === mode" x-cloak>
                    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                            <div>
                                <h2
                                    class="text-lg font-bold capitalize text-slate-900"
                                    x-text="mode + ' Theme Colors'"
                                ></h2>

                                <p class="mt-1 text-sm text-slate-500">
                                    Choose which color should appear in these website areas.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-[1fr_380px]">
                            <div class="space-y-4">
                                <template x-for="(color, index) in colors[mode]" :key="color.id">
                                    <div class="grid grid-cols-1 items-end gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1.2fr_1fr]">
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                                Used For
                                            </label>

                                            <input
                                                type="hidden"
                                                :name="`colors[${mode}][${index}][key]`"
                                                x-model="color.key"
                                            >

                                            <div class="rounded-xl border border-slate-300 bg-white px-4 py-3">
                                                <p class="text-sm font-semibold text-slate-900" x-text="color.label || color.key"></p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500" x-text="color.help || 'This color updates the selected website area.'"></p>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                                Pick Color
                                            </label>

                                            <div class="flex items-center gap-3">
                                                <input
                                                    type="color"
                                                    x-model="color.hex"
                                                    :name="`colors[${mode}][${index}][hex_value]`"
                                                    class="h-11 w-14 cursor-pointer rounded-xl border border-slate-300 bg-white"
                                                >

                                                <input
                                                    type="text"
                                                    x-model="color.hex"
                                                    class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 outline-none focus:border-sky-400 focus:ring-4 focus:ring-slate-200"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Live Preview --}}
                            <div class="lg:sticky lg:top-6">
                                <div
                                    class="rounded-[28px] border p-5 shadow-lg transition"
                                    :style="previewStyle(mode, {
                                        background: 'surface',
                                        borderColor: 'border',
                                        color: 'text-main'
                                    })"
                                >
                                    <p
                                        class="text-xs font-bold uppercase tracking-[0.2em]"
                                        :style="previewStyle(mode, { color: 'primary' })"
                                    >
                                        Live Preview
                                    </p>

                                    <h3 class="mt-2 text-2xl font-bold">
                                        BetGuru Theme
                                    </h3>

                                    <p
                                        class="mt-2 text-sm leading-6"
                                        :style="previewStyle(mode, { color: 'text-muted' })"
                                    >
                                        This preview updates instantly when you change colors.
                                    </p>

                                    <div
                                        class="mt-6 rounded-2xl border p-4"
                                        :style="previewStyle(mode, {
                                            background: 'surface-soft',
                                            borderColor: 'border'
                                        })"
                                    >
                                        <div class="flex items-center justify-between gap-4">
                                            <div>
                                                <p class="text-sm font-bold">
                                                    Barcelona vs Madrid
                                                </p>

                                                <p
                                                    class="mt-1 text-xs"
                                                    :style="previewStyle(mode, { color: 'text-muted' })"
                                                >
                                                    La Liga · Live Market
                                                </p>
                                            </div>

                                            <span
                                                class="rounded-full px-3 py-1 text-xs font-bold"
                                                :style="previewBadgeStyle(mode)"
                                            >
                                                LIVE
                                            </span>
                                        </div>

                                        <div class="mt-4 grid grid-cols-3 gap-3">
                                            <div
                                                class="rounded-xl border p-3 text-center"
                                                :style="previewStyle(mode, {
                                                    background: 'surface',
                                                    borderColor: 'border'
                                                })"
                                            >
                                                <p class="text-xs opacity-60">1</p>
                                                <p class="mt-1 font-bold">2.14</p>
                                            </div>

                                            <div
                                                class="rounded-xl border p-3 text-center"
                                                :style="previewStyle(mode, {
                                                    background: 'surface',
                                                    borderColor: 'border'
                                                })"
                                            >
                                                <p class="text-xs opacity-60">X</p>
                                                <p class="mt-1 font-bold">3.40</p>
                                            </div>

                                            <div
                                                class="rounded-xl border p-3 text-center"
                                                :style="previewStyle(mode, {
                                                    background: 'surface',
                                                    borderColor: 'border'
                                                })"
                                            >
                                                <p class="text-xs opacity-60">2</p>
                                                <p class="mt-1 font-bold">3.80</p>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="mt-5 w-full rounded-2xl px-4 py-3 text-sm font-bold"
                                            :style="previewStyle(mode, {
                                                background: 'primary',
                                                color: 'surface'
                                            })"
                                        >
                                            Primary Button
                                        </button>
                                    </div>

                                    <div
                                        class="mt-5 rounded-2xl border p-4"
                                        :style="previewStyle(mode, {
                                            background: 'bg-soft',
                                            borderColor: 'border'
                                        })"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span
                                                class="text-sm font-semibold"
                                                :style="previewStyle(mode, { color: 'text-soft' })"
                                            >
                                                Odds Movement
                                            </span>

                                            <span
                                                class="text-sm font-bold"
                                                :style="previewStyle(mode, { color: 'success' })"
                                            >
                                                ▲ 4.8%
                                            </span>
                                        </div>

                                        <div class="mt-3 h-2 overflow-hidden rounded-full"
                                             :style="previewStyle(mode, { background: 'border' })">
                                            <div
                                                class="h-full w-2/3 rounded-full"
                                                :style="previewStyle(mode, { background: 'primary' })"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="submit"
                            class="rounded-2xl bg-sky-500 px-7 py-3 text-sm font-bold text-white shadow-lg shadow-sky-500/20 hover:bg-sky-600"
                        >
                            Save Colors
                        </button>
                    </div>
                </div>
            </template>
        </form>
    </div>

    @php
        $colorMeta = [
            'bg' => ['order' => 1, 'label' => 'Website Background', 'help' => 'Main page background behind the full site.'],
            'bg-soft' => ['order' => 2, 'label' => 'Soft Background Areas', 'help' => 'Hero glow areas and softly tinted sections.'],
            'surface' => ['order' => 3, 'label' => 'Main Card Background', 'help' => 'Standard cards, content boxes and dropdown panels.'],
            'surface-soft' => ['order' => 4, 'label' => 'Soft Card Background', 'help' => 'Inputs, subtle boxes and muted content sections.'],
            'surface-elevated' => ['order' => 5, 'label' => 'Raised Card Background', 'help' => 'Sidebar cards, highlighted panels and raised sections.'],
            'border' => ['order' => 6, 'label' => 'Main Border Color', 'help' => 'Default borders, separators and outlines across the site.'],
            'border-strong' => ['order' => 7, 'label' => 'Strong Border Color', 'help' => 'More visible dividers, checkbox lines and stronger outlines.'],
            'text-main' => ['order' => 8, 'label' => 'Main Heading Text', 'help' => 'Primary titles and the most important text.'],
            'text-soft' => ['order' => 9, 'label' => 'Regular Text', 'help' => 'Body text, labels and standard copy.'],
            'text-muted' => ['order' => 10, 'label' => 'Muted Text', 'help' => 'Helper text, timestamps and low-emphasis details.'],
            'primary' => ['order' => 11, 'label' => 'Main Action Color', 'help' => 'Primary buttons, links and active highlights.'],
            'primary-strong' => ['order' => 12, 'label' => 'Strong Action Color', 'help' => 'Hovered links and stronger active action states.'],
            'secondary' => ['order' => 13, 'label' => 'Secondary Accent Color', 'help' => 'Second tone used in gradients and supporting highlights.'],
            'accent' => ['order' => 14, 'label' => 'Highlight Accent', 'help' => 'Featured badges, attention labels and special accents.'],
            'success' => ['order' => 15, 'label' => 'Success Color', 'help' => 'Positive statuses, active plans and confirmation messages.'],
            'danger' => ['order' => 16, 'label' => 'Alert Color', 'help' => 'Errors, remove actions and important alerts.'],
            'warning' => ['order' => 17, 'label' => 'Warning Color', 'help' => 'Caution notes and warning badges.'],
            'ring' => ['order' => 18, 'label' => 'Focus Glow Color', 'help' => 'Input focus outline and active field glow.'],
            'shadow-soft' => ['order' => 19, 'label' => 'Soft Shadow Tone', 'help' => 'Regular card shadows across the website.'],
            'shadow-primary' => ['order' => 20, 'label' => 'Action Shadow Tone', 'help' => 'Glow behind primary buttons and selected cards.'],
        ];

        $lightColorsFormatted = ($groups['light'] ?? collect([]))
            ->sortBy(fn($c) => $colorMeta[$c->key]['order'] ?? 999)
            ->map(
                fn($c) => [
                    'id' => $c->id,
                    'key' => $c->key,
                    'hex' => $c->value,
                    'label' => $colorMeta[$c->key]['label'] ?? ucwords(str_replace(['-', '_'], ' ', $c->key)),
                    'help' => $colorMeta[$c->key]['help'] ?? 'Update this color to change the related website area.',
                ],
            )
            ->values();

        $darkColorsFormatted = ($groups['dark'] ?? collect([]))
            ->sortBy(fn($c) => $colorMeta[$c->key]['order'] ?? 999)
            ->map(
                fn($c) => [
                    'id' => $c->id,
                    'key' => $c->key,
                    'hex' => $c->value,
                    'label' => $colorMeta[$c->key]['label'] ?? ucwords(str_replace(['-', '_'], ' ', $c->key)),
                    'help' => $colorMeta[$c->key]['help'] ?? 'Update this color to change the related website area.',
                ],
            )
            ->values();
    @endphp

    <script>
        function themeColorSettings() {
            return {
                activeTheme: 'light',

                colors: {
                    light: @json($lightColorsFormatted),
                    dark: @json($darkColorsFormatted),
                },

                getColor(mode, key) {
                    const color = this.colors[mode].find(item => item.key === key);

                    if (!color || !color.hex) {
                        return this.colors[mode][0]?.hex || '';
                    }

                    return color.hex;
                },

                hexToRgb(hex) {
                    if (!hex || !hex.startsWith('#')) {
                        return '';
                    }

                    hex = hex.replace('#', '');

                    const r = parseInt(hex.substring(0, 2), 16);
                    const g = parseInt(hex.substring(2, 4), 16);
                    const b = parseInt(hex.substring(4, 6), 16);

                    return `${r}, ${g}, ${b}`;
                },

                previewStyle(mode, map, opacity = 1) {
                    let style = '';

                    Object.entries(map).forEach(([property, key]) => {
                        const hex = this.getColor(mode, key);
                        const rgb = this.hexToRgb(hex);

                        if (property === 'background') {
                            style += `background: rgba(${rgb}, ${opacity});`;
                        }

                        if (property === 'borderColor') {
                            style += `border-color: rgba(${rgb}, ${opacity});`;
                        }

                        if (property === 'color') {
                            style += `color: rgba(${rgb}, ${opacity});`;
                        }
                    });

                    return style;
                },

                previewBadgeStyle(mode) {
                    const success = this.hexToRgb(this.getColor(mode, 'success'));
                    const surface = this.hexToRgb(this.getColor(mode, 'surface'));

                    return `
                        background: rgba(${success}, 0.14);
                        color: rgba(${success}, 1);
                        border: 1px solid rgba(${success}, 0.25);
                    `;
                },
            }
        }
    </script>
@endsection
