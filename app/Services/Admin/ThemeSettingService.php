<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\{DB, Schema};

use App\Models\ThemeSettings;
use App\Repositories\Contracts\Admin\ThemeSettingRepositoryInterface;
use App\Repositories\Eloquent\Admin\ThemeSettingRepository;

class ThemeSettingService
{
    public function __construct(private ThemeSettingRepository $themeSettingRepository) {
        $this->themeSettingRepository = $themeSettingRepository;
    }

    public function getAll()
    {
        if (! Schema::hasTable('theme_settings')) {
            return collect();
        }

        return $this->themeSettingRepository->getGroupedByMode();
    }

    public function update(array $data)
    {
        return DB::transaction(function () use ($data) {
            foreach ($data['colors'] ?? [] as $mode => $colors) {
                foreach ($colors as $color) {
                    if (empty($color['key']) || empty($color['hex_value'])) {
                        continue;
                    }

                    ThemeSettings::updateOrCreate(
                        [
                            'mode' => $mode,
                            'key' => $color['key'],
                        ],
                        [
                            'hex_value' => $this->convertHexToRgb($color['hex_value']),
                            'value' => $color['hex_value'],
                        ]
                    );
                }
            }

            return ThemeSettings::query()->get();
        });
    }

    public function applyDefaultSettings()
    {
        return $this->update([
            'colors' => [
                'light' => [
                    ['key' => 'bg', 'hex_value' => '#FFFDF2'],
                    ['key' => 'bg-soft', 'hex_value' => '#FFF7D6'],
                    ['key' => 'surface', 'hex_value' => '#FFFFFF'],
                    ['key' => 'surface-soft', 'hex_value' => '#FFFAE8'],
                    ['key' => 'surface-elevated', 'hex_value' => '#FFFFFF'],
                    ['key' => 'border', 'hex_value' => '#F2E1A8'],
                    ['key' => 'border-strong', 'hex_value' => '#D9B84A'],
                    ['key' => 'text-main', 'hex_value' => '#121826'],
                    ['key' => 'text-soft', 'hex_value' => '#334155'],
                    ['key' => 'text-muted', 'hex_value' => '#7A6B42'],
                    ['key' => 'primary', 'hex_value' => '#FFB300'],
                    ['key' => 'primary-strong', 'hex_value' => '#E69F00'],
                    ['key' => 'secondary', 'hex_value' => '#CFA84A'],
                    ['key' => 'accent', 'hex_value' => '#FFC84D'],
                    ['key' => 'success', 'hex_value' => '#4FA866'],
                    ['key' => 'danger', 'hex_value' => '#E53935'],
                    ['key' => 'warning', 'hex_value' => '#FFB300'],
                    ['key' => 'ring', 'hex_value' => '#FFE08A'],
                    ['key' => 'shadow-soft', 'hex_value' => '#1E293B'],
                    ['key' => 'shadow-primary', 'hex_value' => '#FFB300'],
                ],
                'dark' => [
                    ['key' => 'bg', 'hex_value' => '#12100A'],
                    ['key' => 'bg-soft', 'hex_value' => '#1F1A0B'],
                    ['key' => 'surface', 'hex_value' => '#1A170E'],
                    ['key' => 'surface-soft', 'hex_value' => '#26200F'],
                    ['key' => 'surface-elevated', 'hex_value' => '#302813'],
                    ['key' => 'border', 'hex_value' => '#4A3B12'],
                    ['key' => 'border-strong', 'hex_value' => '#755C16'],
                    ['key' => 'text-main', 'hex_value' => '#FFF8DD'],
                    ['key' => 'text-soft', 'hex_value' => '#E8DDB8'],
                    ['key' => 'text-muted', 'hex_value' => '#B8A66A'],
                    ['key' => 'primary', 'hex_value' => '#FFB300'],
                    ['key' => 'primary-strong', 'hex_value' => '#FFD166'],
                    ['key' => 'secondary', 'hex_value' => '#D6B35A'],
                    ['key' => 'accent', 'hex_value' => '#FFC84D'],
                    ['key' => 'success', 'hex_value' => '#6BCB7A'],
                    ['key' => 'danger', 'hex_value' => '#FF6B6B'],
                    ['key' => 'warning', 'hex_value' => '#FFB300'],
                    ['key' => 'ring', 'hex_value' => '#FFCC4D'],
                    ['key' => 'shadow-soft', 'hex_value' => '#000000'],
                    ['key' => 'shadow-primary', 'hex_value' => '#FFB300'],
                ],
            ],
        ]);
    }

    public function getRuntimeCss(): string
    {
        $variables = $this->getRuntimeVariables();
        $blocks = [];

        if (! empty($variables['light'])) {
            $blocks[] = $this->buildCssBlock(':root', $variables['light']);
        }

        if (! empty($variables['dark'])) {
            $blocks[] = $this->buildCssBlock('.dark', $variables['dark']);
        }

        return implode("\n\n", array_filter($blocks));
    }

    public function getRuntimeVariables(): array
    {
        if (! Schema::hasTable('theme_settings')) {
            return [
                'light' => [],
                'dark' => [],
            ];
        }

        $groups = $this->themeSettingRepository->getGroupedByMode();

        return [
            'light' => $this->mapVariables($groups->get('light')),
            'dark' => $this->mapVariables($groups->get('dark')),
        ];
    }

    private function mapVariables($settings): array
    {
        return collect($settings ?? [])
            ->filter(fn ($setting) => filled($setting->key))
            ->mapWithKeys(function ($setting) {
                $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $setting->key);
                $rgbValue = $this->normalizeRgbValue($setting->hex_value ?: $setting->value);

                if ($key === '' || $rgbValue === '') {
                    return [];
                }

                return [$key => $rgbValue];
            })
            ->all();
    }

    private function normalizeRgbValue(?string $value): string
    {
        if (! filled($value)) {
            return '';
        }

        if (str_starts_with((string) $value, '#')) {
            return $this->convertHexToRgb((string) $value);
        }

        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));
        $normalized = preg_replace('/[^0-9 ]/', '', $normalized);

        return trim((string) $normalized);
    }

    private function buildCssBlock(string $selector, array $variables): string
    {
        $lines = array_map(
            fn (string $key, string $value) => "    --{$key}: {$value};",
            array_keys($variables),
            array_values($variables)
        );

        return $selector . " {\n" . implode("\n", $lines) . "\n}";
    }

    private function convertHexToRgb(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) !== 6) {
            return '';
        }

        return implode(' ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }
}
