<?php
// public/my-sessions.php – attendee's list of booked sessions
require_once __DIR__ . '/init.php';
Auth::requireLogin();

$bookingModel = new BookingModel();
$bookings = $bookingModel->getByUser(Auth::currentUserId());

$pageTitle = 'Mes réservations';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">📋 Mes réservations</h1>

    <?php if (empty($bookings)): ?>
        <p>Aucune réservation pour l'instant. <a href="<?= APP_BASE_URL ?>/">Voir les séances</a></p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Séance</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Contenu</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <?php
                        $statusLabels = [
                            'pending'   => '⏳ En attente de paiement',
                            'confirmed' => '✅ Confirmé',
                            'attended'  => '🎉 Participé',
                            'absent'    => '😔 Absent',
                            'credited'  => '🎁 Crédit accordé',
                            'cancelled' => '❌ Annulé',
                        ];
                        $label = $statusLabels[$b['status']] ?? e($b['status']);
                        $sessionStart = strtotime($b['session_date'] . ' ' . $b['start_time']);
                        $cancellable  = $b['status'] === 'confirmed'
                                     && ($sessionStart - time()) >= 48 * 3600;
                        ?>
                        <tr>
                            <td><a href="<?= APP_BASE_URL ?>/session.php?id=<?= (int) $b['session_id'] ?>"><?= e($b['title']) ?></a></td>
                            <td><?= e(formatDate($b['session_date'])) ?></td>
                            <td><?= $label ?></td>
                            <td>
                                <?php if ($b['status'] === 'attended'): ?>
                                    <a href="<?= APP_BASE_URL ?>/session-content.php?session_id=<?= (int) $b['session_id'] ?>" class="btn btn--success btn--sm">Voir le contenu</a>
                                <?php else: ?>
                                    –
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($b['status'] === 'pending' && strtotime($b['session_date'] . ' ' . $b['end_time']) >= time()): ?>
                                    <a href="<?= APP_BASE_URL ?>/book.php?session_id=<?= (int) $b['session_id'] ?>" class="btn btn--warning btn--sm">
                                        💳 Reprendre le paiement
                                    </a>
                                <?php elseif ($cancellable): ?>
                                    <form method="post" action="<?= APP_BASE_URL ?>/cancel-booking.php"
                                          onsubmit="return confirm('Confirmer l\'annulation de cette réservation ?')">
                                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                        <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="btn btn--danger btn--sm">Annuler</button>
                                    </form>
                                <?php else: ?>
                                    –
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
