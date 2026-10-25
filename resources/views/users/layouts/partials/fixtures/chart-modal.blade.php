<div id="chartModal" class="chart-modal" role="dialog" aria-modal="true" aria-labelledby="chartModalTitle">
    <div class="chart-modal-content">
        <div class="chart-modal-header">
            <button type="button" data-chart-modal-reload class="chart-action-btn" aria-label="{{ __('Reload chart') }}">&#8635;</button>
            <h2 id="chartModalTitle">{{ __('Market') }}</h2>
            <button type="button" data-chart-modal-close class="chart-close-btn" aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <div id="chartModalGrid" class="chart-grid"></div>
    </div>
</div>
