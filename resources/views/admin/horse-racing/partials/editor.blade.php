<div class="policy-editor-card">
    <div class="policy-editor-header">
        <span class="policy-editor-header-text">
            Visual Editor and HTML Source use the same content.
        </span>

        <div class="policy-editor-tabs">
            <button type="button" class="policy-editor-tab is-active"
                data-mode-button="visual" data-editor-id="{{ $editorId }}">
                Text Editor
            </button>

            <button type="button" class="policy-editor-tab"
                data-mode-button="html" data-editor-id="{{ $editorId }}">
                &lt;/&gt; HTML
            </button>
        </div>
    </div>

    <div class="policy-editor-panel is-active" data-mode-panel="visual">
        <div class="visual-editor-toolbar" data-toolbar-for="{{ $editorId }}">
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
                <button type="button" class="visual-toolbar-button" data-command="insertUnorderedList" title="Bullet List">List</button>
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
            <iframe id="{{ $editorId }}_visual"
                class="visual-editor-frame"
                title="{{ $editorTitle }} Visual Editor"
                sandbox="allow-same-origin"></iframe>
        </div>
    </div>

    <div class="policy-editor-panel" data-mode-panel="html">
        <textarea id="{{ $editorId }}_source"
            class="html-source-textarea"
            spellcheck="false"
            placeholder="Paste complete HTML code here..."></textarea>
    </div>
</div>
