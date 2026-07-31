<?php
// public/session-content.php – detailed post-session content for confirmed attendees
require_once __DIR__ . '/init.php';
Auth::requireLogin();

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$sessionModel = new SessionModel();
$session = $sessionModel->findById($sessionId);

if (!$session) {
    flash('error', 'Séance introuvable.');
    header('Location: ' . APP_BASE_URL . '/');
    exit;
}

$bookingModel = new BookingModel();
if (!$bookingModel->hasAccessToContent(Auth::currentUserId(), $sessionId) && !Auth::isAdmin()) {
    flash('error', 'Accès refusé. Vous devez avoir participé à cette séance.');
    header('Location: ' . APP_BASE_URL . '/ateliers/seance.php?id=' . $sessionId);
    exit;
}

// Load user to check photo_consent
$userModel = new UserModel();
$user = $userModel->findById(Auth::currentUserId());

// Load session media
$db = Database::getInstance();
$stmt = $db->prepare(
    'SELECT * FROM session_media WHERE session_id = :sid ORDER BY created_at ASC'
);
$stmt->execute([':sid' => $sessionId]);
$mediaList = $stmt->fetchAll();

$pageTitle = 'Contenu – ' . $session['title'];
$navContext = 'sessions';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <article class="session-detail">
        <header class="session-detail__header">
            <h1 class="session-detail__title">📚 <?= e($session['title']) ?></h1>
            <p class="session-detail__meta">📅 <?= e(formatDate($session['session_date'])) ?></p>
        </header>

        <?php if ($session['objectives']): ?>
            <section class="section-block">
                <h2>🎯 Objectifs pédagogiques</h2>
                <p><?= nl2br(e($session['objectives'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if ($session['theoretical_content']): ?>
            <section class="section-block">
                <h2>📖 Contenu théorique</h2>
                <p><?= nl2br(e($session['theoretical_content'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if ($session['recipe']): ?>
            <section class="section-block">
                <h2>👨‍🍳 La recette</h2>
                <p><?= nl2br(e($session['recipe'])) ?></p>
            </section>
        <?php endif; ?>

        <!-- Photos -->
        <?php
        $publicMedia  = array_filter($mediaList, fn($m) => !$m['is_private']);
        $privateMedia = array_filter($mediaList, fn($m) => (bool) $m['is_private']);
        ?>
        <?php if ($publicMedia): ?>
            <section class="section-block">
                <h2>📸 Photos de la séance</h2>
                <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:.75rem">
                    <?php foreach ($publicMedia as $media): ?>
                        <?php
                        $imgSrc = !empty($media['external_url'])
                            ? $media['external_url']
                            : APP_BASE_URL . '/uploads/' . e($session['id']) . '/' . e($media['filename']);
                        ?>
                        <a href="<?= e($imgSrc) ?>" class="photo-thumb" aria-label="Agrandir la photo">
                            <img src="<?= e($imgSrc) ?>"
                                 alt="Photo de la séance"
                                 style="width:200px;height:150px;object-fit:cover;border-radius:8px">
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($privateMedia): ?>
            <section class="section-block">
                <h2>🔒 Photos privées (enfants)</h2>
                <?php if ($user['photo_consent']): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:.75rem">
                        <?php foreach ($privateMedia as $media): ?>
                            <?php
                            $imgSrc = !empty($media['external_url'])
                                ? $media['external_url']
                                : APP_BASE_URL . '/uploads/' . e($session['id']) . '/' . e($media['filename']);
                            ?>
                            <a href="<?= e($imgSrc) ?>" class="photo-thumb" aria-label="Agrandir la photo">
                                <img src="<?= e($imgSrc) ?>"
                                     alt="Photo privée de la séance"
                                     style="width:200px;height:150px;object-fit:cover;border-radius:8px">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="locked-content">
                        <p>📸 Ces photos incluent des enfants. Vous n'avez pas autorisé la publication de photos de votre enfant lors de votre inscription.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </article>

    <p class="mt-2"><a href="<?= APP_BASE_URL ?>/my-sessions.php">← Retour à mes réservations</a></p>
</div>

<!-- Lightbox overlay -->
<div id="lightbox" role="dialog" aria-modal="true" aria-label="Visionneuse de photo" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:1000;align-items:center;justify-content:center;padding:1rem">
    <button id="lightbox-close" aria-label="Fermer" style="position:absolute;top:1rem;right:1.25rem;background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;line-height:1">&#x2715;</button>
    <img id="lightbox-img" src="" alt="Photo agrandie" style="max-width:100%;max-height:90vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.5)">
</div>

<script>
(function () {
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const closeBtn = document.getElementById('lightbox-close');

    function openLightbox(src, alt) {
        lightboxImg.src = src;
        lightboxImg.alt = alt || '';
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
    }

    function closeLightbox() {
        lightbox.style.display = 'none';
        lightboxImg.src = '';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('a.photo-thumb').forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const img = link.querySelector('img');
            openLightbox(link.href, img ? img.alt : '');
        });
    });

    closeBtn.addEventListener('click', closeLightbox);

    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox.style.display === 'flex') {
            closeLightbox();
        }
    });
}());
</script>

<?php include ROOT_DIR . '/templates/footer.php'; ?>
