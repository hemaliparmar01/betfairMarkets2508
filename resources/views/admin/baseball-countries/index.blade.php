@extends('admin.layouts.main', ['active' => 'baseball_countries'])

@section('title', 'Baseball Countries | Admin')

@section('content')
    <div class="min-h-screen px-4 py-6 sm:px-6 lg:px-8 theme-bg-base">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                        Admin Panel
                    </p>
                    <h1 class="mt-2 text-3xl font-bold theme-text-main">
                        Baseball Countries
                    </h1>
                    <p class="mt-2 text-sm theme-text-muted">
                        Manage baseball country filters shown on the user side, including status and display order.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="button" id="{{ $reorderConfig['trigger_id'] }}" class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold transition theme-panel-subtle">
                        Reordering
                    </button>
                    <a href="{{ route('admin.baseball_countries.create') }}"
                        class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 betguru-gradient-cta">
                        + Add Country
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-chip-danger">
                    {{ session('error') }}
                </div>
            @endif

            <div id="baseball-country-reorder-feedback" class="mb-5 hidden rounded-2xl px-4 py-3 text-sm font-semibold"></div>

            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-3xl p-5 betguru-card-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] theme-text-muted">Total Countries</p>
                    <p class="mt-3 text-2xl font-bold theme-text-main">{{ $stats['total'] ?? 0 }}</p>
                </div>

                <div class="rounded-3xl p-5 betguru-card-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] theme-text-muted">Active Countries</p>
                    <p class="mt-3 text-2xl font-bold theme-text-success">{{ $stats['active'] ?? 0 }}</p>
                </div>

                <div class="rounded-3xl p-5 betguru-card-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] theme-text-muted">Inactive Countries</p>
                    <p class="mt-3 text-2xl font-bold theme-text-danger">{{ $stats['inactive'] ?? 0 }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-[2rem] betguru-card">
                <form method="GET" action="{{ route('admin.baseball_countries.index') }}"
                    class="flex flex-col gap-4 border-b p-5 lg:flex-row lg:items-center lg:justify-between theme-border">
                    <div class="relative w-full lg:max-w-md">
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 theme-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-4.35-4.35m1.85-5.65a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" />
                        </svg>

                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search countries..."
                            class="betguru-input betguru-pl-11">
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <select name="status" class="betguru-select min-w-[150px]">
                            <option value="">All Status</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>

                        <button type="submit" class="rounded-2xl px-5 py-3 text-sm font-semibold text-white betguru-gradient-cta-flat">
                            Filter
                        </button>

                        <a href="{{ route('admin.baseball_countries.index') }}" class="rounded-2xl px-5 py-3 text-sm font-semibold theme-panel-subtle">
                            Reset
                        </a>
                    </div>
                </form>

                <div class="overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="theme-bg-surface-soft">
                            <tr>
                                <th class="w-20 px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">Drag</th>
                                <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">Country</th>
                                <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">Slug</th>
                                <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">Key</th>
                                <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">Flag</th>
                                <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">Status</th>
                                <th class="px-5 py-4 text-right text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">Action</th>
                            </tr>
                        </thead>

                        <tbody id="baseball-country-sortable" class="divide-y divide-slate-100">
                            @forelse($countries as $country)
                                <tr
                                    class="transition hover:bg-slate-50 {{ $reorderingEnabled ? 'cursor-move' : '' }}"
                                    data-country-id="{{ $country->id }}"
                                    draggable="{{ $reorderingEnabled ? 'true' : 'false' }}"
                                >
                                    <td class="px-5 py-5">
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 theme-text-muted {{ $reorderingEnabled ? 'cursor-grab' : 'opacity-50' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h.008v.008H8.25V6.75zm0 5.25h.008v.008H8.25V12zm0 5.25h.008v.008H8.25V17.25zm7.5-10.5h.008v.008h-.008V6.75zm0 5.25h.008v.008h-.008V12zm0 5.25h.008v.008h-.008V17.25z" />
                                            </svg>
                                        </span>
                                    </td>

                                    <td class="px-5 py-5">
                                        <div class="flex items-center gap-3">
                                            @if($country->flag)
                                                @php $flagCode = strtolower($country->flag ?? ''); @endphp
                                                @if($flagCode && $flagCode !== 'international')
                                                    <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $country->name ?? 'Country' }} flag">
                                                @endif
                                            @endif

                                            <div>
                                                <p class="font-semibold theme-text-main">{{ $country->name }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-5 py-5 text-sm theme-text-muted">{{ $country->slug }}</td>
                                    <td class="px-5 py-5 text-sm theme-text-muted">{{ $country->key }}</td>
                                    <td class="px-5 py-5 text-sm theme-text-muted">{{ $country->flag ?: '-' }}</td>

                                    <td class="px-5 py-5">
                                        <form method="POST" action="{{ route('admin.baseball_countries.toggle_status', $country->id) }}">
                                            @csrf
                                            <button type="submit" class="rounded-full px-3 py-1 text-xs font-semibold {{ $country->is_active ? 'theme-chip-success' : 'theme-chip-danger-strong' }}">
                                                {{ $country->is_active ? 'Active' : 'Inactive' }}
                                            </button>
                                        </form>
                                    </td>

                                    <td class="px-5 py-5 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('admin.baseball_countries.edit', $country->id) }}"
                                                class="rounded-xl px-3 py-2 text-xs font-semibold transition theme-panel-subtle">
                                                Edit
                                            </a>

                                            <form method="POST" action="{{ route('admin.baseball_countries.destroy', $country->id) }}">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                    onclick="return confirm('Are you sure you want to delete this country?')"
                                                    class="rounded-xl px-3 py-2 text-xs font-semibold transition theme-chip-danger">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-sm theme-text-muted">
                                        No baseball countries found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @include('admin.components.datatable-pagination', [
                        'paginator' => $countries,
                        'perPageOptions' => [5, 10, 15, 25, 50],
                        'defaultPerPage' => 10,
                    ])
                </div>
            </div>
        </div>
    </div>

    @include('admin.country-reorder-modal', [
        'items' => $reorderItems,
        'config' => $reorderConfig,
    ])
    @if ($reorderingEnabled)
        <script>
            (() => {
                const sortableBody = document.getElementById('baseball-country-sortable');
                const feedback = document.getElementById('baseball-country-reorder-feedback');

                if (!sortableBody) {
                    return;
                }

                const reorderUrl = @js(route('admin.baseball_countries.reorder'));
                const csrfToken = @js(csrf_token());
                let draggedRow = null;
                let isSavingOrder = false;
                let initialOrder = [];

                const showFeedback = (type, message) => {
                    if (!feedback) {
                        return;
                    }

                    feedback.className = 'mb-5 rounded-2xl px-4 py-3 text-sm font-semibold';

                    if (type === 'success') {
                        feedback.classList.add('theme-alert-success');
                    } else {
                        feedback.classList.add('theme-chip-danger');
                    }

                    feedback.textContent = message;
                    feedback.classList.remove('hidden');
                };

                const getOrderedIds = () => Array.from(sortableBody.querySelectorAll('tr[data-country-id]'))
                    .map((row) => Number(row.dataset.countryId))
                    .filter(Boolean);

                const persistOrder = async () => {
                    if (isSavingOrder) {
                        return;
                    }

                    isSavingOrder = true;

                    try {
                        const response = await fetch(reorderUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                ids: getOrderedIds(),
                            }),
                        });

                        const result = await response.json();

                        if (!response.ok) {
                            throw new Error(result.message || 'Unable to save baseball country order.');
                        }

                        showFeedback('success', result.message || 'Baseball countries reordered successfully.');
                    } catch (error) {
                        showFeedback('error', error.message || 'Unable to save baseball country order.');
                    } finally {
                        isSavingOrder = false;
                    }
                };

                sortableBody.querySelectorAll('tr[data-country-id]').forEach((row) => {
                    row.addEventListener('dragstart', () => {
                        draggedRow = row;
                        initialOrder = getOrderedIds();
                        row.classList.add('opacity-50');
                    });

                    row.addEventListener('dragend', async () => {
                        row.classList.remove('opacity-50');

                        const currentOrder = getOrderedIds();

                        if (draggedRow && JSON.stringify(initialOrder) !== JSON.stringify(currentOrder)) {
                            await persistOrder();
                        }

                        draggedRow = null;
                    });

                    row.addEventListener('dragover', (event) => {
                        event.preventDefault();

                        if (!draggedRow || draggedRow === row) {
                            return;
                        }

                        const rowBounds = row.getBoundingClientRect();
                        const shouldInsertAfter = (event.clientY - rowBounds.top) > (rowBounds.height / 2);

                        if (shouldInsertAfter) {
                            row.after(draggedRow);
                        } else {
                            row.before(draggedRow);
                        }
                    });
                });

                sortableBody.addEventListener('drop', (event) => {
                    event.preventDefault();
                });
            })();
        </script>
    @endif
@endsection
