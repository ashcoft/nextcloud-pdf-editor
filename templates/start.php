<?php

declare(strict_types=1);

/** @var array $_ */
$recentDocuments = $_['recentDocuments'] ?? [];
$recentJson = (string) json_encode(
    $recentDocuments,
    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
);
?>
<div id="pdf-editor-start"
     data-recent-documents="<?php echo htmlspecialchars($recentJson, ENT_QUOTES, 'UTF-8'); ?>">
    <noscript>
        <p><?php echo $l->t('JavaScript is required to use the PDF Editor.'); ?></p>
    </noscript>
</div>
