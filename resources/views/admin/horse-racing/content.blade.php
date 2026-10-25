@extends('admin.layouts.main', ['active' => 'horse_racing'])

@section('title', 'Horse Racing Content | Admin')

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
        $horseRacingFields = [
            'horse_racing_analysis' => [
                'label' => 'Race Analysis & Selections',
                'translation_key' => 'horse_racing_analysis_translations',
            ],
            'horse_racing_consensus' => [
                'label' => 'Market Consensus & Expert Tips',
                'translation_key' => 'horse_racing_consensus_translations',
            ],
        ];
    @endphp

    <div class="min-h-screen px-4 py-6 sm:px-6 lg:px-8 theme-bg-base">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                    Horse Racing
                </p>

                <h1 class="mt-2 text-3xl font-bold theme-text-main">
                    Horse Racing Content
                </h1>

                <p class="mt-2 text-sm theme-text-muted">
                    Publish the daily HTML content shown on the Horse Racing pages.
                </p>
            </div>

            @if (session('success'))
                <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-chip-success">
                    {{ session('success') }}
                </div>
            @endif

            <form id="horseRacingContentForm" method="POST" action="{{ route('admin.horse_racing.update') }}"
                class="space-y-6 rounded-[2rem] p-6 betguru-card-elevated">
                @csrf

                @foreach ($horseRacingFields as $field => $config)
                    @php
                        $fieldLabel = $config['label'];
                        $translationKey = $config['translation_key'];
                        $fieldValue = old($field, $settings[$field] ?? '');
                        $translations = json_decode($settings[$translationKey] ?? '{}', true) ?: [];
                    @endphp

                    <div data-policy-editor="{{ $field }}" data-editor-title="{{ $fieldLabel }}">
                        <label class="betguru-label">{{ $fieldLabel }} (English)</label>

                        @include('admin.horse-racing.partials.editor', [
                            'editorId' => $field,
                            'editorTitle' => $fieldLabel . ' English',
                        ])

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

                            @include('admin.horse-racing.partials.editor', [
                                'editorId' => $localizedField,
                                'editorTitle' => $fieldLabel . ' ' . $localeLabel,
                            ])

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
                        Save Horse Racing Content
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
                const safeTitle = String(title || 'Horse Racing').replace(/[<>&"']/g, '');

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
                    title: container.getAttribute('data-editor-title') || 'Horse Racing',
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

            document.getElementById('horseRacingContentForm').addEventListener('submit', function() {
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
