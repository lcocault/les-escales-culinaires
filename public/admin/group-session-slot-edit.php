<?php
// public/admin/group-session-slot-edit.php – create or edit a group session slot
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$slotModel = new GroupSessionSlotModel();
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$slot   = $id ? $slotModel->findById($id) : null;
$isEdit = (bool) $slot;
$errors = [];

$defaults = [
    'title'                          => 'Atelier anniversaire',
    'description'                    => '',
    'slot_date'                      => '',
    'start_time'                     => '',
    'end_time'                       => '',
    'max_groups'                     => 1,
    'price_per_child_home_cents'     => GroupBookingModel::PRICE_HOME_CENTS,
    'price_per_child_escales_cents'  => GroupBookingModel::PRICE_ESCALES_CENTS,
    'status'                         => 'open',
];

$values = $isEdit ? array_merge($defaults, $slot) : $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $values['title']                         = trim($_POST['title']       ?? '');
    $values['description']                   = trim($_POST['description'] ?? '');
    $values['slot_date']                     = trim($_POST['slot_date']   ?? '');
    $values['start_time']                    = trim($_POST['start_time']  ?? '');
    $values['end_time']                      = trim($_POST['end_time']    ?? '');
    $values['max_groups']                    = max(1, (int) ($_POST['max_groups'] ?? 1));
    $values['price_per_child_home_cents']    = (int) round((float) str_replace(',', '.', $_POST['price_per_child_home_euros']    ?? '0') * 100);
    $values['price_per_child_escales_cents'] = (int) round((float) str_replace(',', '.', $_POST['price_per_child_escales_euros'] ?? '0') * 100);
    $values['status']                        = trim($_POST['status'] ?? 'open');

    if ($values['title'] === '')     $errors[] = 'Le titre est obligatoire.';
    if ($values['slot_date'] === '') $errors[] = 'La date est obligatoire.';
    if ($values['start_time'] === '') $errors[] = 'L\'heure de début est obligatoire.';
    if ($values['end_time'] === '')  $errors[] = 'L\'heure de fin est obligatoire.';
    if ($values['price_per_child_home_cents'] < 0)    $errors[] = 'Le tarif domicile ne peut pas être négatif.';
    if ($values['price_per_child_escales_cents'] < 0) $errors[] = 'Le tarif Escales Culinaires ne peut pas être négatif.';
    if (!in_array($values['status'], ['open', 'full', 'cancelled'], true)) $errors[] = 'Statut invalide.';

    if (empty($errors)) {
        if ($isEdit) {
            $slotModel->update($id, $values);
            flash('success', 'Créneau modifié avec succès.');
        } else {
            $slotModel->create($values);
            flash('success', 'Créneau créé avec succès.');
        }
        header('Location: ' . APP_BASE_URL . '/admin/group-session-slots.php');
        exit;
    }
}

$priceHomeEuros    = number_format($values['price_per_child_home_cents']    / 100, 2, ',', '');
$priceEscalesEuros = number_format($values['price_per_child_escales_cents'] / 100, 2, ',', '');

$pageTitle = $isEdit ? 'Modifier le créneau #' . $id : 'Nouveau créneau de groupe';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🎂 <?= e($pageTitle) ?></h1>

    <p>
        <a href="<?= APP_BASE_URL ?>/admin/group-session-slots.php" class="btn btn--secondary btn--sm">← Retour à la liste</a>
    </p>

    <?php if ($errors): ?>
        <div class="flash flash--error" style="max-width:700px">
            <ul style="margin:0;padding-left:1.2rem">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-card" style="max-width:700px;margin-top:1.5rem">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

            <div class="form-group">
                <label for="title">Titre <span class="required">*</span></label>
                <input type="text" id="title" name="title"
                       value="<?= e($values['title']) ?>" required>
            </div>

            <div class="form-group mt-1">
                <label for="description">Description <span class="optional">(optionnel)</span></label>
                <textarea id="description" name="description" rows="3"
                          placeholder="Courte présentation du créneau…"><?= e($values['description']) ?></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-top:1rem">
                <div class="form-group">
                    <label for="slot_date">Date <span class="required">*</span></label>
                    <input type="date" id="slot_date" name="slot_date"
                           value="<?= e($values['slot_date']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="start_time">Heure de début <span class="required">*</span></label>
                    <input type="time" id="start_time" name="start_time"
                           value="<?= e(substr($values['start_time'], 0, 5)) ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_time">Heure de fin <span class="required">*</span></label>
                    <input type="time" id="end_time" name="end_time"
                           value="<?= e(substr($values['end_time'], 0, 5)) ?>" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem">
                <div class="form-group">
                    <label for="max_groups">Nombre de créneaux disponibles <span class="required">*</span></label>
                    <input type="number" id="max_groups" name="max_groups"
                           value="<?= (int) $values['max_groups'] ?>" min="1" required>
                    <p class="form-hint">Nombre de groupes pouvant réserver ce créneau</p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem">
                <div class="form-group">
                    <label for="price_per_child_home_euros">🏠 Tarif à domicile (€/enfant) <span class="required">*</span></label>
                    <input type="text" id="price_per_child_home_euros" name="price_per_child_home_euros"
                           value="<?= e($priceHomeEuros) ?>" required
                           placeholder="30,00">
                </div>
                <div class="form-group">
                    <label for="price_per_child_escales_euros">📍 Tarif aux Escales (€/enfant) <span class="required">*</span></label>
                    <input type="text" id="price_per_child_escales_euros" name="price_per_child_escales_euros"
                           value="<?= e($priceEscalesEuros) ?>" required
                           placeholder="35,00">
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="form-group mt-1">
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="open"      <?= $values['status'] === 'open'      ? 'selected' : '' ?>>✅ Disponible</option>
                        <option value="full"      <?= $values['status'] === 'full'      ? 'selected' : '' ?>>🔴 Complet</option>
                        <option value="cancelled" <?= $values['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Annulé</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="mt-3">
                <button type="submit" class="btn btn--primary">
                    <?= $isEdit ? '💾 Enregistrer les modifications' : '✅ Créer le créneau' ?>
                </button>
                <a href="<?= APP_BASE_URL ?>/admin/group-session-slots.php" class="btn btn--secondary" style="margin-left:.5rem">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
