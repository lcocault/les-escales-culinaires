<?php
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$userModel    = new UserModel();
$sessionModel = new SessionModel();
$users        = $userModel->getAnnouncementRecipients();
$errors       = [];

$today = date('Y-m-d');
$values = [
    'from_date'     => $today,
    'to_date'       => date('Y-m-d', strtotime('+30 days')),
    'recipient_ids' => array_map(static fn (array $user): int => (int) $user['id'], $users),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $values['from_date'] = trim((string) ($_POST['from_date'] ?? $today));
    $values['to_date']   = trim((string) ($_POST['to_date'] ?? $values['to_date']));

    $postedIds = $_POST['recipient_ids'] ?? [];
    $postedIds = is_array($postedIds) ? $postedIds : [];
    $values['recipient_ids'] = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $postedIds)));

    $from = DateTime::createFromFormat('Y-m-d', $values['from_date']);
    $to   = DateTime::createFromFormat('Y-m-d', $values['to_date']);

    if (!$from || $from->format('Y-m-d') !== $values['from_date']) {
        $errors[] = 'La date de début est invalide.';
    }
    if (!$to || $to->format('Y-m-d') !== $values['to_date']) {
        $errors[] = 'La date de fin est invalide.';
    }
    if (!$errors && $values['from_date'] > $values['to_date']) {
        $errors[] = 'La date de début doit être antérieure ou égale à la date de fin.';
    }
    if ($values['recipient_ids'] === []) {
        $errors[] = 'Sélectionnez au moins un destinataire.';
    }

    $usersById = [];
    foreach ($users as $user) {
        $usersById[(int) $user['id']] = $user;
    }

    $selectedUsers = [];
    foreach ($values['recipient_ids'] as $id) {
        if (!isset($usersById[$id])) {
            continue;
        }
        $email = trim((string) ($usersById[$id]['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            continue;
        }
        $selectedUsers[] = $usersById[$id];
    }

    if (!$errors && $selectedUsers === []) {
        $errors[] = 'Aucun destinataire valide n\'a été trouvé.';
    }

    $sessions = [];
    if (!$errors) {
        $sessions = $sessionModel->getUpcomingInPeriod($values['from_date'], $values['to_date']);
        if ($sessions === []) {
            $errors[] = 'Aucune séance n\'est programmée sur cette période.';
        }
    }

    if ($errors === []) {
        $sentCount   = 0;
        $failedCount = 0;

        foreach ($selectedUsers as $user) {
            try {
                Mailer::sendUpcomingSessionsAnnouncement($user, $sessions, $values['from_date'], $values['to_date']);
                $sentCount++;
            } catch (Throwable $e) {
                $failedCount++;
                error_log(
                    'Session announcement send failed for user #' . (int) $user['id']
                    . ' (' . (string) ($user['email'] ?? '') . '): '
                    . $e->getMessage()
                );
            }
        }

        if ($failedCount > 0) {
            flash(
                'warning',
                $sentCount . ' e-mail(s) d\'annonce envoyés, '
                . $failedCount . ' échec(s). Consultez les logs pour le détail.'
            );
        } else {
            flash('success', $sentCount . ' e-mail(s) d\'annonce envoyés.');
        }
        header('Location: ' . APP_BASE_URL . '/admin/session-announcement.php');
        exit;
    }
}

$pageTitle = 'Annonce des séances';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">📧 Annonce des séances à venir</h1>

    <?php if ($errors): ?>
        <div class="flash flash--error">
            <ul style="margin:0;padding-left:1.2rem">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" style="max-width:860px">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

        <div class="form-group">
            <label for="from_date">Période – début *</label>
            <input type="date" id="from_date" name="from_date" required value="<?= e($values['from_date']) ?>">
        </div>

        <div class="form-group">
            <label for="to_date">Période – fin *</label>
            <input type="date" id="to_date" name="to_date" required value="<?= e($values['to_date']) ?>">
        </div>

        <div class="form-group">
            <label>Destinataires *</label>
            <p style="margin:.25rem 0 1rem 0;color:var(--color-muted)">Chaque destinataire recevra un e-mail individuel.</p>
            <div style="max-height:320px;overflow:auto;border:1px solid #e5e7eb;border-radius:10px;padding:.75rem 1rem">
                <?php foreach ($users as $user): ?>
                    <?php $id = (int) $user['id']; ?>
                    <label style="display:block;margin:.25rem 0">
                        <input
                            type="checkbox"
                            name="recipient_ids[]"
                            value="<?= $id ?>"
                            <?= in_array($id, $values['recipient_ids'], true) ? 'checked' : '' ?>
                        >
                        <?= e($user['first_name'] . ' ' . $user['last_name']) ?> — <?= e($user['email']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:flex;gap:1rem;margin-top:1.5rem">
            <button type="submit" class="btn btn--primary">Envoyer l'annonce</button>
            <a href="<?= APP_BASE_URL ?>/admin/index.php" class="btn btn--secondary">Retour</a>
        </div>
    </form>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
