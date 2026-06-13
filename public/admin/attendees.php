<?php
// public/admin/attendees.php – list attendees for a session
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$sessionModel = new SessionModel();
$session = $sessionModel->findById($sessionId);

if (!$session) {
    flash('error', 'Séance introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
    exit;
}

$bookingModel = new BookingModel();
$bookings = $bookingModel->getBySession($sessionId);

// Load extra children for all bookings in one query
$bookingIds    = array_column($bookings, 'id');
$extraChildren = $bookingModel->getExtraChildrenMapped(array_map('intval', $bookingIds));

$pageTitle = 'Participants – ' . $session['title'];
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">👥 Participants : <?= e($session['title']) ?></h1>
    <p style="color:var(--color-muted);margin-bottom:1.5rem">
        📅 <?= e(formatDate($session['session_date'])) ?>
        &nbsp;|&nbsp; <?= (int) $session['remaining_seats'] ?> / <?= (int) $session['max_attendees'] ?> places restantes
    </p>

    <?php if (empty($bookings)): ?>
        <p>Aucun participant pour cette séance.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Parent</th>
                        <th>Enfant</th>
                        <th>Âge</th>
                        <th>Allergies</th>
                        <th>E-mail</th>
                        <th>Téléphone 1</th>
                        <th>Téléphone 2</th>
                        <th>Photos</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><?= e($b['first_name'] . ' ' . $b['last_name']) ?></td>
                            <td>
                                <?php
                                    $allChildren = [];
                                    if (!empty($b['child_first_name'])) {
                                        $allChildren[] = [
                                            'name'      => trim(($b['child_first_name'] ?? '') . ' ' . ($b['child_last_name'] ?? '')),
                                            'age'       => $b['child_age'] !== null ? (int) $b['child_age'] : null,
                                            'allergies' => $b['child_allergies'] ?? null,
                                        ];
                                    }
                                    foreach ($extraChildren[(int) $b['id']] ?? [] as $ec) {
                                        $allChildren[] = [
                                            'name'      => trim($ec['first_name'] . ' ' . $ec['last_name']),
                                            'age'       => $ec['age'] !== null ? (int) $ec['age'] : null,
                                            'allergies' => $ec['allergies'] ?? null,
                                        ];
                                    }
                                ?>
                                <?php if (empty($allChildren)): ?>
                                    –
                                <?php else: ?>
                                    <?php foreach ($allChildren as $ci => $ch): ?>
                                        <?php if ($ci > 0): ?><br><?php endif; ?>
                                        <?= e($ch['name']) ?>
                                        <?php if ($ch['age'] !== null): ?><small>(<?= $ch['age'] ?> ans)</small><?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($allChildren)): ?>
                                    –
                                <?php else: ?>
                                    <?php foreach ($allChildren as $ci => $ch): ?>
                                        <?php if ($ci > 0): ?><br><?php endif; ?>
                                        <?= $ch['age'] !== null ? $ch['age'] . ' ans' : '–' ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($allChildren)): ?>
                                    –
                                <?php else: ?>
                                    <?php foreach ($allChildren as $ci => $ch): ?>
                                        <?php if ($ci > 0): ?><br><?php endif; ?>
                                        <?= e($ch['allergies'] ?? '–') ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e($b['email']) ?></td>
                            <td><?= e($b['phone'] ?? '–') ?></td>
                            <td><?= e($b['phone2'] ?? '–') ?></td>
                            <td><?= $b['photo_consent'] ? '✅' : '❌' ?></td>
                            <td><?= e($b['status']) ?></td>
                            <td>
                                <div class="actions">
                                    <?php if (in_array($b['status'], ['confirmed'])): ?>
                                        <form method="post" action="<?= APP_BASE_URL ?>/admin/confirm-attendance.php">
                                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                            <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                            <input type="hidden" name="action" value="attended">
                                            <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                                            <button type="submit" class="btn btn--success btn--sm">✅ Présent</button>
                                        </form>
                                        <form method="post" action="<?= APP_BASE_URL ?>/admin/confirm-attendance.php">
                                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                            <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                            <input type="hidden" name="action" value="absent">
                                            <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                                            <button type="submit" class="btn btn--danger btn--sm">❌ Absent</button>
                                        </form>
                                    <?php elseif ($b['status'] === 'absent'): ?>
                                        <form method="post" action="<?= APP_BASE_URL ?>/admin/confirm-attendance.php">
                                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                            <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                            <input type="hidden" name="action" value="credited">
                                            <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                                            <button type="submit" class="btn btn--warning btn--sm">🎁 Crédit</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--color-muted);font-size:.85rem"><?= e($b['status']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="mt-2"><a href="<?= APP_BASE_URL ?>/admin/sessions.php">← Retour aux séances</a></p>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
