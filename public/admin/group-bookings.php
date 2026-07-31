<?php
// public/admin/group-bookings.php – admin list of group booking requests (birthday parties)
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$model    = new GroupBookingModel();
$requests = $model->getAll();

$pageTitle = 'Ateliers anniversaire';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🎂 Ateliers anniversaire</h1>

    <?php if (empty($requests)): ?>
        <p>Aucune demande d'atelier anniversaire pour le moment.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Contact</th>
                        <th>Date souhaitée</th>
                        <th>Enfants</th>
                        <th>Lieu</th>
                        <th>Tarif estimé</th>
                        <th>Statut</th>
                        <th>Demandé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <?php
                            $estimatedPrice = GroupBookingModel::estimatePrice(
                                (int) $r['nb_children'],
                                $r['location_type']
                            );
                            $locationLabel  = $r['location_type'] === 'home' ? '🏠 Domicile' : '📍 Escales';
                            $statusConfig   = [
                                'pending'   => ['label' => 'En attente',  'class' => 'badge--warning'],
                                'confirmed' => ['label' => 'Confirmée',   'class' => 'badge--success'],
                                'cancelled' => ['label' => 'Annulée',     'class' => 'badge--error'],
                            ];
                            $sc = $statusConfig[$r['status']] ?? ['label' => $r['status'], 'class' => ''];
                        ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td>
                                <strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong><br>
                                <a href="mailto:<?= e($r['email']) ?>" style="font-size:.85rem"><?= e($r['email']) ?></a>
                                <?php if ($r['contact_phone']): ?>
                                    <br><span style="font-size:.85rem"><?= e($r['contact_phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(date('d/m/Y', strtotime($r['preferred_date']))) ?>
                                <?php if (!empty($r['group_session_slot_id'])): ?>
                                    <br><span style="font-size:.8rem;color:var(--color-muted)">🗓️ Créneau #<?= (int) $r['group_session_slot_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $r['nb_children'] ?></td>
                            <td><?= $locationLabel ?></td>
                            <td><?= e(formatPrice($estimatedPrice)) ?></td>
                            <td><span class="badge <?= $sc['class'] ?>"><?= e($sc['label']) ?></span></td>
                            <td><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></td>
                            <td>
                                <a href="<?= APP_BASE_URL ?>/admin/group-booking-view.php?id=<?= (int) $r['id'] ?>" class="btn btn--secondary btn--sm">Voir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
