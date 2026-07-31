<?php
// public/admin/group-booking-view.php – view and update a single group booking request
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$model = new GroupBookingModel();
$request = $model->findById($id);

if (!$request) {
    flash('error', 'Demande introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/group-bookings.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $newStatus  = trim($_POST['status']      ?? '');
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (!in_array($newStatus, ['pending', 'confirmed', 'cancelled'], true)) {
        $errors[] = 'Statut invalide.';
    }

    if (empty($errors)) {
        $model->updateStatus($id, $newStatus, $adminNotes ?: null);

        // Notify the user if status changed
        if ($newStatus !== $request['status'] && in_array($newStatus, ['confirmed', 'cancelled'], true)) {
            $userModel = new UserModel();
            $user      = $userModel->findById((int) $request['user_id']);
            if ($user) {
                $updatedRequest = array_merge($request, [
                    'status'      => $newStatus,
                    'admin_notes' => $adminNotes ?: null,
                ]);
                Mailer::sendGroupBookingStatusUpdate($user, $updatedRequest);
            }
        }

        flash('success', 'Demande mise à jour avec succès.');
        header('Location: ' . APP_BASE_URL . '/admin/group-booking-view.php?id=' . $id);
        exit;
    }

    // Re-fetch the request to show updated values
    $request = $model->findById($id);
}

$estimatedPrice = GroupBookingModel::estimatePrice(
    (int) $request['nb_children'],
    $request['location_type']
);
$locationLabel  = $request['location_type'] === 'home'
    ? '🏠 Domicile'
    : '📍 Escales Culinaires (36 rue Boieldieu, 31300 Toulouse)';

$pageTitle = 'Demande anniversaire #' . $id;
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🎂 Demande anniversaire #<?= $id ?></h1>
    <p>
        <a href="<?= APP_BASE_URL ?>/admin/group-bookings.php" class="btn btn--secondary btn--sm">← Retour à la liste</a>
    </p>

    <?php if ($errors): ?>
        <div class="flash flash--error">
            <ul style="margin:0;padding-left:1.2rem">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="section-block" style="max-width:700px;margin-top:1.5rem">
        <h2 style="font-size:1.1rem;margin-bottom:1rem">📋 Détails de la demande</h2>
        <table style="width:100%;border-collapse:collapse">
            <?php if (!empty($request['group_session_slot_id'])): ?>
            <?php
                $slotModel = new GroupSessionSlotModel();
                $linkedSlot = $slotModel->findById((int) $request['group_session_slot_id']);
            ?>
            <?php if ($linkedSlot): ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;width:35%;color:var(--color-muted)">Créneau lié</th>
                <td style="padding:.4rem .6rem">
                    <a href="<?= APP_BASE_URL ?>/admin/group-session-slot-edit.php?id=<?= (int) $linkedSlot['id'] ?>">
                        <?= e($linkedSlot['title']) ?>
                    </a>
                    – <?= e(formatDate($linkedSlot['slot_date'])) ?>
                    <?= e(substr($linkedSlot['start_time'], 0, 5)) ?> – <?= e(substr($linkedSlot['end_time'], 0, 5)) ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;width:35%;color:var(--color-muted)">Contact</th>
                <td style="padding:.4rem .6rem">
                    <?= e($request['first_name'] . ' ' . $request['last_name']) ?><br>
                    <a href="mailto:<?= e($request['email']) ?>"><?= e($request['email']) ?></a>
                    <?php if ($request['contact_phone']): ?>
                        <br><?= e($request['contact_phone']) ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Date souhaitée</th>
                <td style="padding:.4rem .6rem"><strong><?= e(formatDate($request['preferred_date'])) ?></strong></td>
            </tr>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Nombre d'enfants</th>
                <td style="padding:.4rem .6rem"><?= (int) $request['nb_children'] ?></td>
            </tr>
            <?php if ($request['children_ages']): ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Âges des enfants</th>
                <td style="padding:.4rem .6rem"><?= e($request['children_ages']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Lieu</th>
                <td style="padding:.4rem .6rem"><?= $locationLabel ?></td>
            </tr>
            <?php if ($request['location_type'] === 'home' && $request['location_address']): ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Adresse</th>
                <td style="padding:.4rem .6rem"><?= nl2br(e($request['location_address'])) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Tarif estimé</th>
                <td style="padding:.4rem .6rem">
                    <?= e(formatPrice($request['location_type'] === 'home' ? GroupBookingModel::PRICE_HOME_CENTS : GroupBookingModel::PRICE_ESCALES_CENTS)) ?>/enfant
                    × <?= (int) $request['nb_children'] ?> = <strong><?= e(formatPrice($estimatedPrice)) ?></strong>
                </td>
            </tr>
            <?php if ($request['allergies']): ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Allergies</th>
                <td style="padding:.4rem .6rem"><?= nl2br(e($request['allergies'])) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($request['additional_info']): ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Informations comp.</th>
                <td style="padding:.4rem .6rem"><?= nl2br(e($request['additional_info'])) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th style="text-align:left;padding:.4rem .6rem;color:var(--color-muted)">Demande reçue le</th>
                <td style="padding:.4rem .6rem"><?= e(date('d/m/Y à H:i', strtotime($request['created_at']))) ?></td>
            </tr>
        </table>
    </div>

    <div class="form-card" style="max-width:700px;margin-top:1.5rem">
        <h2 style="font-size:1.1rem;margin-bottom:1rem">⚙️ Traitement de la demande</h2>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

            <div class="form-group">
                <label for="status">Statut</label>
                <select id="status" name="status" required>
                    <option value="pending"   <?= $request['status'] === 'pending'   ? 'selected' : '' ?>>⏳ En attente</option>
                    <option value="confirmed" <?= $request['status'] === 'confirmed' ? 'selected' : '' ?>>✅ Confirmée</option>
                    <option value="cancelled" <?= $request['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Annulée</option>
                </select>
            </div>

            <div class="form-group mt-1">
                <label for="admin_notes">Message pour le demandeur <span class="optional">(optionnel)</span></label>
                <textarea id="admin_notes" name="admin_notes" rows="4"
                          placeholder="Détails de confirmation, instructions, raison d'annulation…"><?= e($request['admin_notes'] ?? '') ?></textarea>
                <p class="form-hint">Ce message sera envoyé par e-mail au demandeur lors d'un changement de statut vers "Confirmée" ou "Annulée".</p>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn--primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
