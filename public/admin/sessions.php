<?php
// public/admin/sessions.php – list all sessions
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$sessionModel = new SessionModel();
$sessions = $sessionModel->getAll();
$archivedSessions = $sessionModel->getArchived();

$pageTitle = 'Gérer les séances';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:.5rem">
        <h1 class="page-title" style="margin:0">📅 Séances</h1>
        <a href="<?= APP_BASE_URL ?>/admin/session-edit.php" class="btn btn--primary">+ Nouvelle séance</a>
    </div>

    <?php if (empty($sessions)): ?>
        <p>Aucune séance. <a href="<?= APP_BASE_URL ?>/admin/session-edit.php">Créer la première séance</a>.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Titre</th>
                        <th>Tranche d'âge</th>
                        <th>Inscrits</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td><?= e(formatSessionDateTime($s['session_date'], $s['start_time'])) ?></td>
                            <td><?= e($s['title']) ?><?php if (!empty($s['is_private'])): ?> <span class="badge" style="background:#7c3aed;color:#fff;font-size:.7rem">🔒 Privée</span><?php endif; ?></td>
                            <td><?php
                                echo e(ageCategoryLabel($s['age_category'] ?? '6-12'));
                            ?></td>
                            <td><?= (int) $s['registered_count'] ?></td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'confirmed' => '✅ Confirmée',
                                    'cancelled' => '❌ Annulée',
                                ];
                                echo e($statusLabels[$s['status']] ?? $s['status']);
                                ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="<?= APP_BASE_URL ?>/admin/session-edit.php?id=<?= (int) $s['id'] ?>" class="btn btn--warning btn--icon" title="Modifier" aria-label="Modifier">✏️</a>
                                    <a href="<?= APP_BASE_URL ?>/admin/session-edit.php?duplicate_from=<?= (int) $s['id'] ?>" class="btn btn--secondary btn--icon" title="Dupliquer" aria-label="Dupliquer">📋</a>
                                    <a href="<?= APP_BASE_URL ?>/admin/attendees.php?session_id=<?= (int) $s['id'] ?>" class="btn btn--secondary btn--icon" title="Participants" aria-label="Participants">👥</a>
                                    <a href="<?= APP_BASE_URL ?>/admin/session-photos.php?session_id=<?= (int) $s['id'] ?>" class="btn btn--secondary btn--icon" title="Photos" aria-label="Photos">📸</a>
                                    <?php if (!empty($s['is_private'])): ?>
                                    <a href="<?= APP_BASE_URL ?>/admin/session-allowances.php?session_id=<?= (int) $s['id'] ?>" class="btn btn--secondary btn--icon" title="Gérer les accès" aria-label="Gérer les accès">🔒</a>
                                    <?php endif; ?>
                                    <?php if (!in_array($s['status'] ?? 'pending', ['cancelled'], true)): ?>
                                    <form method="post" action="<?= APP_BASE_URL ?>/admin/session-cancel.php" onsubmit="return confirm('Annuler cette séance et rembourser tous les participants ?')">
                                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn--warning btn--icon" title="Annuler la séance" aria-label="Annuler la séance">🚫</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (($s['status'] ?? 'pending') === 'pending'): ?>
                                    <form method="post" action="<?= APP_BASE_URL ?>/admin/session-close.php" onsubmit="return confirm('Clôturer cette séance ?')">
                                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn--success btn--icon" title="Clôturer la séance" aria-label="Clôturer la séance">✅</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= APP_BASE_URL ?>/admin/session-delete.php" onsubmit="return confirm('Supprimer cette séance ?')">
                                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn--danger btn--icon" title="Supprimer" aria-label="Supprimer">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:2rem">🗄️ Séances archivées</h2>
    <?php if (empty($archivedSessions)): ?>
        <p>Aucune séance archivée.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Titre</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archivedSessions as $s): ?>
                        <tr>
                            <td><?= e(formatSessionDateTime($s['session_date'], $s['start_time'])) ?></td>
                            <td><?= e($s['title']) ?></td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'pending'   => '⏳ En attente',
                                    'confirmed' => '✅ Confirmée',
                                    'cancelled' => '❌ Annulée',
                                ];
                                echo e($statusLabels[$s['status'] ?? 'pending'] ?? $s['status']);
                                ?>
                            </td>
                            <td>
                                <a href="<?= APP_BASE_URL ?>/admin/session-edit.php?duplicate_from=<?= (int) $s['id'] ?>" class="btn btn--secondary btn--icon" title="Dupliquer" aria-label="Dupliquer">📋</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
