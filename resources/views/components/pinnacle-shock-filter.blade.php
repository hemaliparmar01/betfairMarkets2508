@props(['helpId' => 'pinnacle-shock-help', 'checked' => false])

<div class="pinnacle-higher-filter pinnacle-shock-filter">
    <label class="flex cursor-pointer items-center gap-2 font-semibold text-red-600 dark:text-red-400">
        <input type="checkbox" data-pinnacle-shock @checked($checked)>
        <span>{{ __('Market Shock') }}</span>
    </label>
    <details class="pinnacle-filter-info">
        <summary aria-label="{{ __('About Market Shock') }}" aria-describedby="{{ $helpId }}">i</summary>
    </details>
    <span id="{{ $helpId }}" role="tooltip" class="pinnacle-filter-help">{{ __('Shows only massive, sudden odds drops of 8% or more. Use this to instantly spot big market movements (Whale alerts) without scrolling through minor 1-2% changes.') }}</span>
</div>
