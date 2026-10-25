{{-- @extends('admin.layouts.main', ['active' => 'policy_settings'])

@section('title', 'Policy Settings | Admin')

@push('css')
    <style>
        .html-preview-frame {
            width: 100%;
            min-height: 420px;
            border: 0;
            display: block;
            background: #f9fafb;
        }

        .html-preview-wrapper {
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 0 0 24px 24px;
            background: #f9fafb;
        }

        .html-editor-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-bottom: 0;
            border-radius: 12px 12px 0 0;
            background: #ffffff;
        }

        .html-editor-toolbar-text {
            font-size: 13px;
            color: #64748b;
        }

        .html-editor-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            padding: 8px 12px;
            color: #374151;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .html-editor-button:hover {
            border-color: #ffb300;
            color: #111827;
        }

        .html-editor-modal {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.72);
        }

        .html-editor-modal.is-open {
            display: flex;
        }

        .html-editor-dialog {
            width: min(1100px, 100%);
            max-height: calc(100vh - 48px);
            overflow: hidden;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.28);
        }

        .html-editor-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .html-editor-modal-title {
            margin: 0;
            color: #111827;
            font-size: 18px;
            font-weight: 700;
        }

        .html-editor-close {
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 10px;
            background: #f9fafb;
            color: #374151;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .html-editor-modal-body {
            padding: 20px 22px;
        }

        .html-source-textarea {
            width: 100%;
            height: min(62vh, 620px);
            resize: vertical;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            outline: none;
            background: #111827;
            padding: 16px;
            color: #e5e7eb;
            font-family: Consolas, Monaco, 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            tab-size: 4;
        }

        .html-source-textarea:focus {
            border-color: #ffb300;
            box-shadow: 0 0 0 3px rgba(255, 179, 0, 0.16);
        }

        .html-editor-modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 22px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .html-editor-cancel,
        .html-editor-apply {
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .html-editor-cancel {
            background: #e5e7eb;
            color: #374151;
        }

        .html-editor-apply {
            background: #ffb300;
            color: #111827;
        }

        body.html-modal-open {
            overflow: hidden;
        }

        @media (max-width: 640px) {
            .html-editor-modal {
                padding: 12px;
            }

            .html-editor-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .html-editor-button {
                width: 100%;
            }

            .html-source-textarea {
                height: 56vh;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $policyFields = [
            'privacy_policy' => [
                'label' => 'Privacy Policy',
                'translation_key' => 'privacy_policy_translations',
            ],
            'refund_policy' => [
                'label' => 'Refund Policy',
                'translation_key' => 'refund_policy_translations',
            ],
            'terms_and_conditions' => [
                'label' => 'Terms and Conditions',
                'translation_key' => 'terms_and_conditions_translations',
            ],
            'disclaimer' => [
                'label' => 'Disclaimer',
                'translation_key' => 'disclaimer_translations',
            ],
            'bf_markets_manifesto' => [
                'label' => 'BF Markets Manifesto',
                'translation_key' => 'bf_markets_manifesto_translations',
            ],
            'filter_guide' => [
                'label' => 'Filter Guide',
                'translation_key' => 'filter_guide_translations',
            ],
        ];
    @endphp

    <div class="min-h-screen px-4 py-6 sm:px-6 lg:px-8 theme-bg-base">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                    Settings
                </p>
                <h1 class="mt-2 text-3xl font-bold theme-text-main">
                    Policy Settings
                </h1>
                <p class="mt-2 text-sm theme-text-muted">
                    Paste complete HTML code and preview the exact policy page design before saving.
                </p>
            </div>

            @if (session('success'))
                <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-chip-success">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.policy_settings.update') }}"
                class="rounded-[2rem] p-6 space-y-6 betguru-card-elevated">
                @csrf

                @foreach ($policyFields as $field => $config)
                    @php
                        $fieldLabel = $config['label'];
                        $translationKey = $config['translation_key'];
                        $fieldValue = old($field, $settings[$field] ?? '');
                        $translations = json_decode($settings[$translationKey] ?? '{}', true) ?: [];
                    @endphp

                    <div data-html-editor="{{ $field }}">
                        <label class="betguru-label">{{ $fieldLabel }}</label>

                        <div class="html-editor-toolbar">
                            <span class="html-editor-toolbar-text">
                                Full HTML preview
                            </span>
                            <button type="button" class="html-editor-button"
                                data-open-html-editor="{{ $field }}"
                                data-editor-title="{{ $fieldLabel }}">
                                <span>&lt;/&gt;</span>
                                Edit HTML
                            </button>
                        </div>

                        <div class="html-preview-wrapper">
                            <iframe id="{{ $field }}_preview" class="html-preview-frame"
                                title="{{ $fieldLabel }} Preview"
                                sandbox="allow-same-origin"></iframe>
                        </div>

                        <textarea id="{{ $field }}" name="{{ $field }}" class="hidden">{{ $fieldValue }}</textarea>

                        @error($field)
                            <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    @foreach (translation_form_locales() as $locale => $localeLabel)
                        @php
                            $localizedField = $field . '_' . $locale;
                            $localizedName = $translationKey . '[' . $locale . ']';
                            $localizedValue = old($translationKey . '.' . $locale, data_get($translations, $locale, ''));
                        @endphp

                        <div data-html-editor="{{ $localizedField }}">
                            <label class="betguru-label">
                                {{ $fieldLabel }} ({{ $localeLabel }})
                            </label>

                            <div class="html-editor-toolbar">
                                <span class="html-editor-toolbar-text">
                                    Full HTML preview
                                </span>
                                <button type="button" class="html-editor-button"
                                    data-open-html-editor="{{ $localizedField }}"
                                    data-editor-title="{{ $fieldLabel }} ({{ $localeLabel }})">
                                    <span>&lt;/&gt;</span>
                                    Edit HTML
                                </button>
                            </div>

                            <div class="html-preview-wrapper">
                                <iframe id="{{ $localizedField }}_preview" class="html-preview-frame"
                                    title="{{ $fieldLabel }} {{ $localeLabel }} Preview"
                                    sandbox="allow-same-origin"></iframe>
                            </div>

                            <textarea id="{{ $localizedField }}" name="{{ $localizedName }}" class="hidden">{{ $localizedValue }}</textarea>

                            @error($translationKey . '.' . $locale)
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                @endforeach

                <div class="flex items-center justify-end">
                    <button type="submit"
                        class="rounded-2xl px-6 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 betguru-gradient-cta">
                        Save Policies
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="htmlEditorModal" class="html-editor-modal" aria-hidden="true">
        <div class="html-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="htmlEditorModalTitle">
            <div class="html-editor-modal-header">
                <h2 id="htmlEditorModalTitle" class="html-editor-modal-title">Edit HTML</h2>
                <button type="button" id="closeHtmlEditorModal" class="html-editor-close" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="html-editor-modal-body">
                <textarea id="htmlSourceTextarea" class="html-source-textarea"
                    placeholder="Paste complete HTML code here..."></textarea>
            </div>

            <div class="html-editor-modal-footer">
                <button type="button" id="cancelHtmlEditor" class="html-editor-cancel">
                    Cancel
                </button>
                <button type="button" id="applyHtmlEditor" class="html-editor-apply">
                    Apply & Preview
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('htmlEditorModal');
            const modalTitle = document.getElementById('htmlEditorModalTitle');
            const sourceTextarea = document.getElementById('htmlSourceTextarea');
            const closeButton = document.getElementById('closeHtmlEditorModal');
            const cancelButton = document.getElementById('cancelHtmlEditor');
            const applyButton = document.getElementById('applyHtmlEditor');

            let activeEditorId = null;
            const resizeObservers = new Map();

            function emptyPreviewHtml() {
                return `
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <style>
                            * { box-sizing: border-box; }
                            body {
                                margin: 0;
                                padding: 48px 20px;
                                background: #f9fafb;
                                color: #64748b;
                                font-family: Arial, sans-serif;
                                text-align: center;
                            }
                        </style>
                    </head>
                    <body>No HTML content added yet.</body>
                    </html>
                `;
            }

            function resizePreview(iframe) {
                try {
                    const iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
                    const body = iframeDocument.body;
                    const html = iframeDocument.documentElement;

                    if (!body || !html) {
                        return;
                    }

                    const height = Math.max(
                        body.scrollHeight,
                        body.offsetHeight,
                        html.clientHeight,
                        html.scrollHeight,
                        html.offsetHeight,
                        420
                    );

                    iframe.style.height = height + 'px';
                } catch (error) {
                    iframe.style.height = '700px';
                }
            }

            function attachResizeObserver(iframe) {
                try {
                    const iframeDocument = iframe.contentDocument || iframe.contentWindow.document;

                    if (!iframeDocument || !iframeDocument.documentElement) {
                        return;
                    }

                    if (resizeObservers.has(iframe.id)) {
                        resizeObservers.get(iframe.id).disconnect();
                    }

                    const observer = new ResizeObserver(function() {
                        resizePreview(iframe);
                    });

                    observer.observe(iframeDocument.documentElement);

                    if (iframeDocument.body) {
                        observer.observe(iframeDocument.body);
                    }

                    resizeObservers.set(iframe.id, observer);
                } catch (error) {
                    // Keep the default iframe height when observer access is unavailable.
                }
            }

            function renderPreview(editorId) {
                const hiddenTextarea = document.getElementById(editorId);
                const previewFrame = document.getElementById(editorId + '_preview');

                if (!hiddenTextarea || !previewFrame) {
                    return;
                }

                const html = hiddenTextarea.value.trim();

                previewFrame.onload = function() {
                    resizePreview(previewFrame);
                    attachResizeObserver(previewFrame);

                    setTimeout(function() {
                        resizePreview(previewFrame);
                    }, 300);

                    setTimeout(function() {
                        resizePreview(previewFrame);
                    }, 1000);
                };

                previewFrame.srcdoc = html || emptyPreviewHtml();
            }

            function openModal(editorId, title) {
                const hiddenTextarea = document.getElementById(editorId);

                if (!hiddenTextarea) {
                    return;
                }

                activeEditorId = editorId;
                modalTitle.textContent = title || 'Edit HTML';
                sourceTextarea.value = hiddenTextarea.value;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('html-modal-open');

                setTimeout(function() {
                    sourceTextarea.focus();
                }, 50);
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('html-modal-open');
                activeEditorId = null;
            }

            document.querySelectorAll('[data-open-html-editor]').forEach(function(button) {
                button.addEventListener('click', function() {
                    openModal(
                        button.getAttribute('data-open-html-editor'),
                        button.getAttribute('data-editor-title')
                    );
                });
            });

            applyButton.addEventListener('click', function() {
                if (!activeEditorId) {
                    return;
                }

                const hiddenTextarea = document.getElementById(activeEditorId);
                hiddenTextarea.value = sourceTextarea.value;
                renderPreview(activeEditorId);
                closeModal();
            });

            closeButton.addEventListener('click', closeModal);
            cancelButton.addEventListener('click', closeModal);

            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            document.querySelectorAll('[data-html-editor]').forEach(function(editor) {
                renderPreview(editor.getAttribute('data-html-editor'));
            });
        });
    </script>
@endpush --}}

@extends('admin.layouts.main', ['active' => 'policy_settings'])

@section('title', 'Policy Settings | Admin')

@push('css')
    <style>
        .policy-editor-card {
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #ffffff;
        }

        .policy-editor-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }

        .policy-editor-header-text {
            color: #64748b;
            font-size: 13px;
        }

        .policy-editor-tabs {
            display: inline-flex;
            gap: 4px;
            padding: 4px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f9fafb;
        }

        .policy-editor-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            padding: 7px 12px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .policy-editor-tab:hover {
            color: #111827;
        }

        .policy-editor-tab.is-active {
            background: #ffb300;
            color: #111827;
            box-shadow: 0 2px 8px rgba(255, 179, 0, 0.22);
        }

        .policy-editor-panel {
            display: none;
        }

        .policy-editor-panel.is-active {
            display: block;
        }

        .visual-editor-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .visual-toolbar-group {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding-right: 8px;
            margin-right: 2px;
            border-right: 1px solid #e5e7eb;
        }

        .visual-toolbar-group:last-child {
            padding-right: 0;
            border-right: 0;
        }

        .visual-toolbar-button,
        .visual-toolbar-select {
            height: 34px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #374151;
            font-size: 13px;
            cursor: pointer;
        }

        .visual-toolbar-button {
            min-width: 34px;
            padding: 0 9px;
            font-weight: 700;
        }

        .visual-toolbar-select {
            min-width: 110px;
            padding: 0 9px;
        }

        .visual-toolbar-button:hover,
        .visual-toolbar-select:hover {
            border-color: #ffb300;
            color: #111827;
        }

        .visual-editor-wrapper {
            position: relative;
            height: 620px;
            background: #f9fafb;
        }

        .visual-editor-frame {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            background: #f9fafb;
        }

        .html-source-textarea {
            display: block;
            width: 100%;
            height: 620px;
            resize: vertical;
            border: 0;
            outline: none;
            background: #111827;
            padding: 18px;
            color: #e5e7eb;
            font-family: Consolas, Monaco, 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            tab-size: 4;
        }

        .html-source-textarea:focus {
            box-shadow: inset 0 0 0 2px rgba(255, 179, 0, 0.75);
        }

        @media (max-width: 768px) {
            .policy-editor-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .policy-editor-tabs {
                width: 100%;
            }

            .policy-editor-tab {
                flex: 1;
            }

            .visual-editor-wrapper,
            .html-source-textarea {
                height: 520px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $policyFields = [
            'privacy_policy' => [
                'label' => 'Privacy Policy',
                'translation_key' => 'privacy_policy_translations',
            ],
            'refund_policy' => [
                'label' => 'Refund Policy',
                'translation_key' => 'refund_policy_translations',
            ],
            'terms_and_conditions' => [
                'label' => 'Terms and Conditions',
                'translation_key' => 'terms_and_conditions_translations',
            ],
            'disclaimer' => [
                'label' => 'Disclaimer',
                'translation_key' => 'disclaimer_translations',
            ],
            'bf_markets_manifesto' => [
                'label' => 'BF Markets Manifesto',
                'translation_key' => 'bf_markets_manifesto_translations',
            ],
            'filter_guide' => [
                'label' => 'Filter Guide',
                'translation_key' => 'filter_guide_translations',
            ],
        ];
    @endphp

    <div class="min-h-screen px-4 py-6 sm:px-6 lg:px-8 theme-bg-base">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                    Settings
                </p>

                <h1 class="mt-2 text-3xl font-bold theme-text-main">
                    Policy Settings
                </h1>

                <p class="mt-2 text-sm theme-text-muted">
                    Edit the same content using Visual Editor or HTML Source mode.
                </p>
            </div>

            @if (session('success'))
                <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-chip-success">
                    {{ session('success') }}
                </div>
            @endif

            <form id="policySettingsForm" method="POST" action="{{ route('admin.policy_settings.update') }}"
                class="space-y-6 rounded-[2rem] p-6 betguru-card-elevated">
                @csrf

                @foreach ($policyFields as $field => $config)
                    @php
                        $fieldLabel = $config['label'];
                        $translationKey = $config['translation_key'];
                        $fieldValue = old($field, $settings[$field] ?? '');
                        $translations = json_decode($settings[$translationKey] ?? '{}', true) ?: [];
                    @endphp

                    <div data-policy-editor="{{ $field }}" data-editor-title="{{ $fieldLabel }}">
                        <label class="betguru-label">{{ $fieldLabel }}</label>

                        <div class="policy-editor-card">
                            <div class="policy-editor-header">
                                <span class="policy-editor-header-text">
                                    Visual Editor and HTML Source use the same content.
                                </span>

                                <div class="policy-editor-tabs">
                                    <button type="button" class="policy-editor-tab is-active"
                                        data-mode-button="visual" data-editor-id="{{ $field }}">
                                        Text Editor
                                    </button>

                                    <button type="button" class="policy-editor-tab"
                                        data-mode-button="html" data-editor-id="{{ $field }}">
                                        &lt;/&gt; HTML
                                    </button>
                                </div>
                            </div>

                            <div class="policy-editor-panel is-active" data-mode-panel="visual">
                                <div class="visual-editor-toolbar" data-toolbar-for="{{ $field }}">
                                    <div class="visual-toolbar-group">
                                        <select class="visual-toolbar-select" data-format-block>
                                            <option value="p">Paragraph</option>
                                            <option value="h1">Heading 1</option>
                                            <option value="h2">Heading 2</option>
                                            <option value="h3">Heading 3</option>
                                            <option value="h4">Heading 4</option>
                                        </select>
                                    </div>

                                    <div class="visual-toolbar-group">
                                        <button type="button" class="visual-toolbar-button" data-command="bold" title="Bold">B</button>
                                        <button type="button" class="visual-toolbar-button" data-command="italic" title="Italic"><em>I</em></button>
                                        <button type="button" class="visual-toolbar-button" data-command="underline" title="Underline"><u>U</u></button>
                                        <button type="button" class="visual-toolbar-button" data-command="strikeThrough" title="Strike"><s>S</s></button>
                                    </div>

                                    <div class="visual-toolbar-group">
                                        <button type="button" class="visual-toolbar-button" data-command="insertUnorderedList" title="Bullet List">• List</button>
                                        <button type="button" class="visual-toolbar-button" data-command="insertOrderedList" title="Ordered List">1. List</button>
                                    </div>

                                    <div class="visual-toolbar-group">
                                        <button type="button" class="visual-toolbar-button" data-command="justifyLeft" title="Align Left">Left</button>
                                        <button type="button" class="visual-toolbar-button" data-command="justifyCenter" title="Align Center">Center</button>
                                        <button type="button" class="visual-toolbar-button" data-command="justifyRight" title="Align Right">Right</button>
                                    </div>

                                    <div class="visual-toolbar-group">
                                        <button type="button" class="visual-toolbar-button" data-create-link title="Add Link">Link</button>
                                        <button type="button" class="visual-toolbar-button" data-command="unlink" title="Remove Link">Unlink</button>
                                        <button type="button" class="visual-toolbar-button" data-command="removeFormat" title="Clear Formatting">Clear</button>
                                    </div>
                                </div>

                                <div class="visual-editor-wrapper">
                                    <iframe id="{{ $field }}_visual"
                                        class="visual-editor-frame"
                                        title="{{ $fieldLabel }} Visual Editor"
                                        sandbox="allow-same-origin"></iframe>
                                </div>
                            </div>

                            <div class="policy-editor-panel" data-mode-panel="html">
                                <textarea id="{{ $field }}_source"
                                    class="html-source-textarea"
                                    spellcheck="false"
                                    placeholder="Paste complete HTML code here..."></textarea>
                            </div>
                        </div>

                        <textarea id="{{ $field }}" name="{{ $field }}" class="hidden">{{ $fieldValue }}</textarea>

                        @error($field)
                            <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    @foreach (translation_form_locales() as $locale => $localeLabel)
                        @php
                            $localizedField = $field . '_' . $locale;
                            $localizedName = $translationKey . '[' . $locale . ']';
                            $localizedValue = old(
                                $translationKey . '.' . $locale,
                                data_get($translations, $locale, '')
                            );
                        @endphp

                        <div data-policy-editor="{{ $localizedField }}"
                            data-editor-title="{{ $fieldLabel }} ({{ $localeLabel }})">
                            <label class="betguru-label">
                                {{ $fieldLabel }} ({{ $localeLabel }})
                            </label>

                            <div class="policy-editor-card">
                                <div class="policy-editor-header">
                                    <span class="policy-editor-header-text">
                                        Visual Editor and HTML Source use the same content.
                                    </span>

                                    <div class="policy-editor-tabs">
                                        <button type="button" class="policy-editor-tab is-active"
                                            data-mode-button="visual" data-editor-id="{{ $localizedField }}">
                                            Text Editor
                                        </button>

                                        <button type="button" class="policy-editor-tab"
                                            data-mode-button="html" data-editor-id="{{ $localizedField }}">
                                            &lt;/&gt; HTML
                                        </button>
                                    </div>
                                </div>

                                <div class="policy-editor-panel is-active" data-mode-panel="visual">
                                    <div class="visual-editor-toolbar" data-toolbar-for="{{ $localizedField }}">
                                        <div class="visual-toolbar-group">
                                            <select class="visual-toolbar-select" data-format-block>
                                                <option value="p">Paragraph</option>
                                                <option value="h1">Heading 1</option>
                                                <option value="h2">Heading 2</option>
                                                <option value="h3">Heading 3</option>
                                                <option value="h4">Heading 4</option>
                                            </select>
                                        </div>

                                        <div class="visual-toolbar-group">
                                            <button type="button" class="visual-toolbar-button" data-command="bold" title="Bold">B</button>
                                            <button type="button" class="visual-toolbar-button" data-command="italic" title="Italic"><em>I</em></button>
                                            <button type="button" class="visual-toolbar-button" data-command="underline" title="Underline"><u>U</u></button>
                                            <button type="button" class="visual-toolbar-button" data-command="strikeThrough" title="Strike"><s>S</s></button>
                                        </div>

                                        <div class="visual-toolbar-group">
                                            <button type="button" class="visual-toolbar-button" data-command="insertUnorderedList" title="Bullet List">• List</button>
                                            <button type="button" class="visual-toolbar-button" data-command="insertOrderedList" title="Ordered List">1. List</button>
                                        </div>

                                        <div class="visual-toolbar-group">
                                            <button type="button" class="visual-toolbar-button" data-command="justifyLeft" title="Align Left">Left</button>
                                            <button type="button" class="visual-toolbar-button" data-command="justifyCenter" title="Align Center">Center</button>
                                            <button type="button" class="visual-toolbar-button" data-command="justifyRight" title="Align Right">Right</button>
                                        </div>

                                        <div class="visual-toolbar-group">
                                            <button type="button" class="visual-toolbar-button" data-create-link title="Add Link">Link</button>
                                            <button type="button" class="visual-toolbar-button" data-command="unlink" title="Remove Link">Unlink</button>
                                            <button type="button" class="visual-toolbar-button" data-command="removeFormat" title="Clear Formatting">Clear</button>
                                        </div>
                                    </div>

                                    <div class="visual-editor-wrapper">
                                        <iframe id="{{ $localizedField }}_visual"
                                            class="visual-editor-frame"
                                            title="{{ $fieldLabel }} {{ $localeLabel }} Visual Editor"
                                            sandbox="allow-same-origin"></iframe>
                                    </div>
                                </div>

                                <div class="policy-editor-panel" data-mode-panel="html">
                                    <textarea id="{{ $localizedField }}_source"
                                        class="html-source-textarea"
                                        spellcheck="false"
                                        placeholder="Paste complete HTML code here..."></textarea>
                                </div>
                            </div>

                            <textarea id="{{ $localizedField }}" name="{{ $localizedName }}" class="hidden">{{ $localizedValue }}</textarea>

                            @error($translationKey . '.' . $locale)
                                <p class="mt-1 text-xs theme-text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                @endforeach

                <div class="flex items-center justify-end">
                    <button type="submit"
                        class="rounded-2xl px-6 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 betguru-gradient-cta">
                        Save Policies
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editors = new Map();

            function containsFullDocumentMarkup(html) {
                return /<!doctype\s+html|<html[\s>]|<head[\s>]|<body[\s>]/i.test(html || '');
            }

            function emptyDocument(title) {
                const safeTitle = String(title || 'Policy').replace(/[<>&"']/g, '');

                return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${safeTitle}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 40px 20px;
            background: #f9fafb;
            color: #374151;
            font-family: Arial, sans-serif;
            font-size: 15px;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <p>Start writing here...</p>
</body>
</html>`;
            }

            function sourceForVisual(editor) {
                const source = editor.sourceTextarea.value.trim();

                if (!source) {
                    editor.isFullDocument = true;
                    return emptyDocument(editor.title);
                }

                editor.isFullDocument = containsFullDocumentMarkup(source);

                if (editor.isFullDocument) {
                    return source;
                }

                return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #ffffff;
            color: #374151;
            font-family: Arial, sans-serif;
            font-size: 15px;
            line-height: 1.7;
        }
    </style>
</head>
<body>${source}</body>
</html>`;
            }

            function serializeVisual(editor) {
                const frameDocument = editor.iframe.contentDocument || editor.iframe.contentWindow.document;

                if (!frameDocument || !frameDocument.documentElement || !frameDocument.body) {
                    return editor.sourceTextarea.value;
                }

                if (!editor.isFullDocument) {
                    return frameDocument.body.innerHTML;
                }

                return '<!DOCTYPE html>\n' + frameDocument.documentElement.outerHTML;
            }

            function syncFromVisual(editor) {
                const html = serializeVisual(editor);
                editor.sourceTextarea.value = html;
                editor.hiddenTextarea.value = html;
            }

            function bindVisualChanges(editor) {
                const frameDocument = editor.iframe.contentDocument || editor.iframe.contentWindow.document;

                if (!frameDocument || !frameDocument.body) {
                    return;
                }

                frameDocument.designMode = 'on';

                const sync = function() {
                    window.clearTimeout(editor.syncTimer);
                    editor.syncTimer = window.setTimeout(function() {
                        syncFromVisual(editor);
                    }, 150);
                };

                frameDocument.addEventListener('input', sync);
                frameDocument.addEventListener('keyup', sync);
                frameDocument.addEventListener('paste', function() {
                    window.setTimeout(sync, 0);
                });

                const observer = new MutationObserver(sync);
                observer.observe(frameDocument.body, {
                    childList: true,
                    subtree: true,
                    characterData: true,
                    attributes: true,
                });

                editor.mutationObserver = observer;
            }

            function loadVisual(editor) {
                if (editor.mutationObserver) {
                    editor.mutationObserver.disconnect();
                    editor.mutationObserver = null;
                }

                editor.iframe.onload = function() {
                    bindVisualChanges(editor);
                };

                editor.iframe.srcdoc = sourceForVisual(editor);
            }

            function showMode(editor, mode) {
                if (mode === 'visual') {
                    editor.hiddenTextarea.value = editor.sourceTextarea.value;
                    loadVisual(editor);
                } else {
                    syncFromVisual(editor);
                }

                editor.container.querySelectorAll('[data-mode-button]').forEach(function(button) {
                    button.classList.toggle('is-active', button.getAttribute('data-mode-button') === mode);
                });

                editor.container.querySelectorAll('[data-mode-panel]').forEach(function(panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-mode-panel') === mode);
                });

                editor.activeMode = mode;
            }

            function executeCommand(editor, command, value = null) {
                const frameDocument = editor.iframe.contentDocument || editor.iframe.contentWindow.document;

                if (!frameDocument) {
                    return;
                }

                editor.iframe.contentWindow.focus();
                frameDocument.execCommand(command, false, value);
                syncFromVisual(editor);
            }

            document.querySelectorAll('[data-policy-editor]').forEach(function(container) {
                const editorId = container.getAttribute('data-policy-editor');
                const hiddenTextarea = document.getElementById(editorId);
                const sourceTextarea = document.getElementById(editorId + '_source');
                const iframe = document.getElementById(editorId + '_visual');

                if (!hiddenTextarea || !sourceTextarea || !iframe) {
                    return;
                }

                const editor = {
                    id: editorId,
                    title: container.getAttribute('data-editor-title') || 'Policy',
                    container: container,
                    hiddenTextarea: hiddenTextarea,
                    sourceTextarea: sourceTextarea,
                    iframe: iframe,
                    activeMode: 'visual',
                    isFullDocument: false,
                    syncTimer: null,
                    mutationObserver: null,
                };

                sourceTextarea.value = hiddenTextarea.value;
                editors.set(editorId, editor);
                loadVisual(editor);

                sourceTextarea.addEventListener('input', function() {
                    hiddenTextarea.value = sourceTextarea.value;
                    editor.isFullDocument = containsFullDocumentMarkup(sourceTextarea.value);
                });

                container.querySelectorAll('[data-mode-button]').forEach(function(button) {
                    button.addEventListener('click', function() {
                        const mode = button.getAttribute('data-mode-button');

                        if (mode !== editor.activeMode) {
                            showMode(editor, mode);
                        }
                    });
                });

                const toolbar = container.querySelector('[data-toolbar-for="' + editorId + '"]');

                if (toolbar) {
                    toolbar.querySelectorAll('[data-command]').forEach(function(button) {
                        button.addEventListener('mousedown', function(event) {
                            event.preventDefault();
                        });

                        button.addEventListener('click', function() {
                            executeCommand(editor, button.getAttribute('data-command'));
                        });
                    });

                    const formatBlock = toolbar.querySelector('[data-format-block]');

                    if (formatBlock) {
                        formatBlock.addEventListener('change', function() {
                            executeCommand(editor, 'formatBlock', formatBlock.value);
                        });
                    }

                    const createLinkButton = toolbar.querySelector('[data-create-link]');

                    if (createLinkButton) {
                        createLinkButton.addEventListener('mousedown', function(event) {
                            event.preventDefault();
                        });

                        createLinkButton.addEventListener('click', function() {
                            const url = window.prompt('Enter link URL:');

                            if (url) {
                                executeCommand(editor, 'createLink', url);
                            }
                        });
                    }
                }
            });

            document.getElementById('policySettingsForm').addEventListener('submit', function() {
                editors.forEach(function(editor) {
                    if (editor.activeMode === 'visual') {
                        syncFromVisual(editor);
                    } else {
                        editor.hiddenTextarea.value = editor.sourceTextarea.value;
                    }
                });
            });
        });
    </script>
@endpush
