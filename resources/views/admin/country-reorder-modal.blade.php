@php
    $modalId = $config['modal_id'];
    $triggerId = $config['trigger_id'];
    $sortableId = $config['sortable_id'];
    $saveButtonId = $config['save_button_id'];
    $feedbackId = $config['feedback_id'];
@endphp

<div id="{{ $modalId }}" class="fixed inset-0 z-[70] hidden">
    <div class="absolute inset-0 bg-slate-950/60" data-reorder-close></div>

    <div class="relative mx-auto mt-8 flex max-h-[90vh] w-[94%] flex-col overflow-hidden rounded-[2rem] border shadow-2xl theme-border betguru-card">
        <div class="flex items-center justify-between border-b px-6 py-5 theme-border">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] theme-text-primary">
                    {{ $config['eyebrow'] ?? 'Reordering' }}
                </p>
                <h2 class="mt-2 text-2xl font-bold theme-text-main">
                    {{ $config['title'] }}
                </h2>
                <p class="mt-1 text-sm theme-text-muted">
                    {{ $config['description'] }}
                </p>
            </div>

            <button type="button" data-reorder-close class="inline-flex h-11 w-11 items-center justify-center rounded-2xl theme-panel-subtle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div id="{{ $feedbackId }}" class="mx-6 mt-5 hidden rounded-2xl px-4 py-3 text-sm font-semibold"></div>

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
            <div id="{{ $sortableId }}" class="grid grid-cols-5 gap-3">
                @foreach($items as $item)
                        <div data-reorder-id="{{ $item->id }}" draggable="true" class="flex items-center gap-4 rounded-2xl border px-4 py-4 transition hover:bg-slate-50 theme-border cursor-move">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-50 theme-text-muted cursor-grab">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h.008v.008H8.25V6.75zm0 5.25h.008v.008H8.25V12zm0 5.25h.008v.008H8.25V17.25zm7.5-10.5h.008v.008h-.008V6.75zm0 5.25h.008v.008h-.008V12zm0 5.25h.008v.008h-.008V17.25z" />
                            </svg>
                        </span>

                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                ID: {{ $item->id }}
                            </p>
                            <p class="mt-1 truncate text-base font-semibold theme-text-main">
                                {{ $item->name }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-3 border-t px-6 py-5 sm:flex-row sm:justify-end theme-border">
            <button type="button" data-reorder-close class="rounded-2xl px-5 py-3 text-sm font-semibold theme-panel-subtle">
                Close
            </button>

            <button type="button" id="{{ $saveButtonId }}" class="rounded-2xl px-5 py-3 text-sm font-semibold text-white betguru-gradient-cta-flat">
                {{ $config['save_text'] ?? 'Save Order' }}
            </button>
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById(@js($modalId));
        const openButton = document.getElementById(@js($triggerId));
        const sortableList = document.getElementById(@js($sortableId));
        const saveButton = document.getElementById(@js($saveButtonId));
        const feedback = document.getElementById(@js($feedbackId));

        if (!modal || !openButton || !sortableList || !saveButton || !feedback) {
            return;
        }

        const closeButtons = modal.querySelectorAll('[data-reorder-close]');
        const reorderUrl = @js($config['reorder_url']);
        const csrfToken = @js(csrf_token());
        const successMessage = @js($config['success_text'] ?? 'Order updated successfully.');
        const errorMessage = @js($config['error_text'] ?? 'Unable to save order.');

        let draggedItem = null;
        let isSaving = false;

        const openModal = () => {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };

        const showFeedback = (type, message) => {
            feedback.className = 'mx-6 mt-5 rounded-2xl px-4 py-3 text-sm font-semibold';

            if (type === 'success') {
                feedback.classList.add('theme-alert-success');
            } else {
                feedback.classList.add('theme-chip-danger');
            }

            feedback.textContent = message;
            feedback.classList.remove('hidden');
        };

        const getOrderedIds = () => Array.from(sortableList.querySelectorAll('[data-reorder-id]'))
            .map((item) => Number(item.dataset.reorderId))
            .filter(Boolean);

        const bindSortableItems = () => {
            sortableList.querySelectorAll('[data-reorder-id]').forEach((item) => {
                item.addEventListener('dragstart', () => {
                    draggedItem = item;
                    item.classList.add('opacity-50');
                });

                item.addEventListener('dragend', () => {
                    item.classList.remove('opacity-50');
                    draggedItem = null;
                });

                item.addEventListener('dragover', (event) => {
                    event.preventDefault();

                    if (!draggedItem || draggedItem === item) {
                        return;
                    }

                    const bounds = item.getBoundingClientRect();
                    const shouldInsertAfter = (event.clientY - bounds.top) > (bounds.height / 2);

                    if (shouldInsertAfter) {
                        item.after(draggedItem);
                    } else {
                        item.before(draggedItem);
                    }
                });
            });
        };

        const persistOrder = async () => {
            if (isSaving) {
                return;
            }

            isSaving = true;

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
                    throw new Error(result.message || errorMessage);
                }

                showFeedback('success', result.message || successMessage);
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } catch (error) {
                showFeedback('error', error.message || errorMessage);
            } finally {
                isSaving = false;
            }
        };

        openButton.addEventListener('click', openModal);

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });

        sortableList.addEventListener('drop', (event) => {
            event.preventDefault();
        });

        saveButton.addEventListener('click', persistOrder);

        bindSortableItems();
    })();
</script>
