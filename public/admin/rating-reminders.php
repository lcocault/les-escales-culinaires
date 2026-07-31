<?php
// public/admin/rating-reminders.php – list attended bookings without a rating
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$bookingModel = new BookingModel();
$rows = $bookingModel->getAttendedWithoutRating();

$pageTitle = 'Rappels d\'avis';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">⭐ Rappels d'avis</h1>
    <p style="color:var(--color-muted);margin-bottom:1.5rem">
        Participations pour lesquelles aucun avis n'a encore été déposé.
    </p>

    <p style="margin-bottom:1rem">
        <a href="<?= APP_BASE_URL ?>/admin/index.php" class="btn btn--secondary">← Retour</a>
    </p>

    <?php if (empty($rows)): ?>
        <p style="color:var(--color-muted)">🎉 Tous les participants ont déjà laissé un avis, ou ont été masqués.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Séance</th>
                    <th>Date</th>
                    <th>Participant</th>
                    <th>E-mail</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['session_title']) ?></td>
                        <td><?= e($row['session_date']) ?></td>
                        <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td style="white-space:nowrap">
                            <form method="post" action="<?= APP_BASE_URL ?>/admin/rating-reminder-action.php"
                                  style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                <input type="hidden" name="booking_id" value="<?= (int) $row['booking_id'] ?>">
                                <input type="hidden" name="action" value="remind">
                                <button type="submit" class="btn btn--primary btn--sm">📧 Rappel</button>
                            </form>
                            <form method="post" action="<?= APP_BASE_URL ?>/admin/rating-reminder-action.php"
                                  style="display:inline;margin-left:.5rem"
                                  onsubmit="return confirm('Masquer cette participation de la liste ?')">
                                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                <input type="hidden" name="booking_id" value="<?= (int) $row['booking_id'] ?>">
                                <input type="hidden" name="action" value="dismiss">
                                <button type="submit" class="btn btn--secondary btn--sm">🙈 Masquer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
