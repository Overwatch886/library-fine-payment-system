<?php
$pageTitle = $pageTitle ?? 'University Library';
$navLinks  = $navLinks ?? [];
?>
<header class="page-header">
    <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (!empty($navLinks)): ?>
        <nav aria-label="Primary navigation">
            <?php foreach ($navLinks as $link): ?>
                <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</header>
