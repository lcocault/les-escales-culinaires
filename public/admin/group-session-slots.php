<?php
// public/admin/group-session-slots.php – admin list of group session slots
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$slotModel = new GroupSessionSlotModel();
$slots     = $slotModel->getAll();

$pageTitle = 'Créneaux de groupe';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🎂 Créneaux de groupe</h1>

    <p>
        <a href="<?= APP_BASE_URL ?>/admin/group-session-slot-edit.php" class="btn btn--primary">
            + Nouveau créneau
        </a>
        <a href="<?= APP_BASE_URL ?>/admin/" class="btn btn--secondary" style="margin-left:.5rem">← Administration</a>
    </p>

    <?php if (empty($slots)): ?>
        <p style="margin-top:1.5rem;color:var(--color-muted)">Aucun créneau de groupe défini pour le moment.</p>
    <?php else: ?>
        <div class="table-wrapper" style="margin-top:1.5rem">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Horaires</th>
                        <th>Créneaux</th>
                        <th>Tarif domicile/enfant</th>
                        <th>Tarif Escales/enfant</th>
                        <th>Réservations</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slots as $s): ?>
                        <?php
                            $statusConfig = [
                                'open'      => ['label' => 'Disponible',  'class' => 'badge--success'],
                                'full'      => ['label' => 'Complet',     'class' => 'badge--warning'],
                                'cancelled' => ['label' => 'Annulé',      'class' => 'badge--error'],
                            ];
                            $sc = $statusConfig[$s['status']] ?? ['label' => $s['status'], 'class' => ''];
                            $isPast = strtotime($s['slot_date']) < strtotime('today');
                        ?>
                        <tr<?= $isPast ? ' style="opacity:.65"' : '' ?>>
                            <td><?= (int) $s['id'] ?></td>
                            <td><strong><?= e($s['title']) ?></strong></td>
                            <td><?= e(date('d/m/Y', strtotime($s['slot_date']))) ?></td>
                            <td><?= e(substr($s['start_time'], 0, 5)) ?> – <?= e(substr($s['end_time'], 0, 5)) ?></td>
                            <td><?= (int) $s['remaining_groups'] ?> / <?= (int) $s['max_groups'] ?></td>
                            <td><?= e(formatPrice((int) $s['price_per_child_home_cents'])) ?></td>
                            <td><?= e(formatPrice((int) $s['price_per_child_escales_cents'])) ?></td>
                            <td><?= (int) $s['booking_count'] ?></td>
                            <td><span class="badge <?= $sc['class'] ?>"><?= e($sc['label']) ?></span></td>
                            <td>
                                <a href="<?= APP_BASE_URL ?>/admin/group-session-slot-edit.php?id=<?= (int) $s['id'] ?>"
                                   class="btn btn--secondary btn--sm">Modifier</a>
                                <form method="post"
                                      action="<?= APP_BASE_URL ?>/admin/group-session-slot-delete.php"
                                      style="display:inline"
                                      onsubmit="return confirm('Supprimer ce créneau ?')">
                                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="btn btn--danger btn--sm">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
