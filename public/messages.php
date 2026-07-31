<?php
// public/messages.php – full list of general messages
require_once __DIR__ . '/init.php';

$pageTitle    = 'Actualités';
$navContext   = 'sessions';
$messageModel = new GeneralMessageModel();
$messages     = $messageModel->getAll();

$typeIcons = [
    'info'    => '💬',
    'warning' => '⚠️',
    'danger'  => '🚨',
    'success' => '✅',
];

include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <section class="hero">
        <h1>📣 Actualités</h1>
        <p>Retrouvez ici tous nos messages et informations récentes.</p>
    </section>

    <?php if (empty($messages)): ?>
        <p class="text-center mt-3" style="color:var(--color-muted)">
            Aucun message pour le moment.
        </p>
    <?php else: ?>
        <section class="news-thread" aria-label="Actualités">
            <?php foreach ($messages as $gm): ?>
                <div class="news-item news-item--<?= e($gm['type']) ?>">
                    <span class="news-item__icon" aria-hidden="true"><?= $typeIcons[$gm['type']] ?? '📢' ?></span>
                    <div class="news-item__body">
                        <p class="news-item__date"><?= e(date('d/m/Y', strtotime($gm['created_at']))) ?></p>
                        <p class="news-item__text"><?= e($gm['body']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <p class="mt-3 text-center">
        <a href="<?= APP_BASE_URL ?>/" class="btn btn--secondary">← Retour aux séances</a>
    </p>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
