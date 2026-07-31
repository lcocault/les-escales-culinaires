<?php
// public/my-group-bookings.php – user's list of group booking requests (birthday parties)
require_once __DIR__ . '/init.php';
Auth::requireLogin();

$model    = new GroupBookingModel();
$requests = $model->getByUser(Auth::currentUserId());

$pageTitle  = 'Mes ateliers anniversaire';
$navContext = 'group-booking';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🎂 Mes ateliers anniversaire</h1>

    <?php if (empty($requests)): ?>
        <p>Vous n'avez pas encore fait de demande d'atelier anniversaire.</p>
        <p><a href="<?= APP_BASE_URL ?>/group-booking.php" class="btn btn--primary">🎂 Faire une demande</a></p>
    <?php else: ?>
        <p style="margin-bottom:1rem">
            <a href="<?= APP_BASE_URL ?>/group-booking.php" class="btn btn--primary btn--sm">+ Nouvelle demande</a>
        </p>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date souhaitée</th>
                        <th>Enfants</th>
                        <th>Lieu</th>
                        <th>Tarif estimé</th>
                        <th>Statut</th>
                        <th>Demandé le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <?php
                            $estimatedPrice = GroupBookingModel::estimatePrice(
                                (int) $r['nb_children'],
                                $r['location_type']
                            );
                            $locationLabel  = $r['location_type'] === 'home' ? '🏠 Domicile' : '📍 Escales Culinaires';
                            $statusConfig   = [
                                'pending'   => ['label' => 'En attente',  'class' => 'badge--warning'],
                                'confirmed' => ['label' => 'Confirmée',   'class' => 'badge--success'],
                                'cancelled' => ['label' => 'Annulée',     'class' => 'badge--error'],
                            ];
                            $sc = $statusConfig[$r['status']] ?? ['label' => $r['status'], 'class' => ''];
                        ?>
                        <tr>
                            <td><?= e(formatDate($r['preferred_date'])) ?></td>
                            <td><?= (int) $r['nb_children'] ?></td>
                            <td><?= $locationLabel ?></td>
                            <td><?= e(formatPrice($estimatedPrice)) ?></td>
                            <td><span class="badge <?= $sc['class'] ?>"><?= e($sc['label']) ?></span></td>
                            <td><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></td>
                        </tr>
                        <?php if ($r['admin_notes']): ?>
                            <tr>
                                <td colspan="6" style="font-size:.9rem;color:var(--color-muted);padding-top:0">
                                    💬 Message de notre équipe : <?= e($r['admin_notes']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
