<?php
// Scripts and styles are added in AdminSettingsController::index().
?>
<div id="pdf-editor" class="section section-pdf-editor">
    <h2>PDF Editor</h2>

    <div class="pdf-editor-setting">
        <label for="pdf-editor-max-size"><?php p($l->t('Maximum file size (MB):')) ?></label>
        <input
            id="pdf-editor-max-size"
            type="number"
            min="1"
            max="512"
            step="1"
            value="<?php p((string) $_['maxSizeMb']) ?>"
        >
        <p class="pdf-editor-hint">
            <?php p($l->t('Note: The largest file the editor will open, in megabytes.')) ?>
        </p>
    </div>

    <button type="button" id="pdf-editor-save" class="button primary">
        <?php p($l->t('Save')) ?>
    </button>
    <span id="pdf-editor-status" class="pdf-editor-status" role="status"></span>
</div>
