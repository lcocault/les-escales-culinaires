<?php
// public/admin/shop-product-edit.php – create or edit a shop product
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$productModel = new ShopProductModel();
$id      = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = $id ? $productModel->findById($id) : null;
$isEdit  = (bool) $product;
$errors  = [];

$defaults = [
    'name'         => '',
    'description'  => '',
    'price_cents'  => 0,
    'portion_count' => 1,
    'min_order_portions' => 1,
    'is_available' => true,
    'external_photo_url' => '',
];
$values = $isEdit ? array_merge($defaults, $product) : $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $values['name']         = trim($_POST['name']        ?? '');
    $values['description']  = trim($_POST['description'] ?? '');
    $values['price_cents']  = (int) round((float) str_replace(',', '.', $_POST['price_euros'] ?? '0') * 100);
    $values['portion_count'] = (int) ($_POST['portion_count'] ?? 1);
    $values['min_order_portions'] = (int) ($_POST['min_order_portions'] ?? 1);
    $values['is_available'] = isset($_POST['is_available']);
    $values['external_photo_url'] = trim($_POST['external_photo_url'] ?? '');

    if ($values['name'] === '') {
        $errors[] = 'Le nom du produit est obligatoire.';
    }
    if ($values['price_cents'] < 0) {
        $errors[] = 'Le prix ne peut pas être négatif.';
    }
    if ($values['portion_count'] < 1) {
        $errors[] = 'Le nombre de portions doit être au minimum de 1.';
    }
    if ($values['min_order_portions'] < 1) {
        $errors[] = 'Le minimum de portions par commande doit être au minimum de 1.';
    }

    $newPhotoFilename = null;
    $newExternalPhotoUrl = null;
    $replacePhotoSource = false;

    // Handle photo source (uploaded file or external URL)
    $hasPhotoUpload = !empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($hasPhotoUpload) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erreur lors du téléchargement de la photo (code ' . $_FILES['photo']['error'] . ').';
        } else {
            $file    = $_FILES['photo'];
            $maxSize = 8 * 1024 * 1024;

            if ($file['size'] > $maxSize) {
                $errors[] = 'La photo est trop volumineuse (8 Mo maximum).';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
                $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                if ($mime === false || !array_key_exists($mime, $allowedMimes)) {
                    $errors[] = 'Type de fichier non autorisé. Seuls JPEG, PNG et WebP sont acceptés.';
                } else {
                    $uploadDir = ROOT_DIR . '/public/uploads/shop/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $ext = $allowedMimes[$mime];
                    do {
                        $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
                        $destination = $uploadDir . $filename;
                    } while (file_exists($destination));

                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        $errors[] = 'Impossible de sauvegarder la photo. Vérifiez les permissions.';
                    } else {
                        $newPhotoFilename = $filename;
                        $newExternalPhotoUrl = null;
                        $replacePhotoSource = true;
                    }
                }
            }
        }
    } else {
        $externalPhotoUrl = $values['external_photo_url'];
        if ($externalPhotoUrl !== '') {
            if (!filter_var($externalPhotoUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'L\'URL de la photo n\'est pas valide.';
            } elseif (!preg_match('#^https?://#i', $externalPhotoUrl)) {
                $errors[] = 'L\'URL de la photo doit commencer par http:// ou https://.';
            } else {
                $newExternalPhotoUrl = $externalPhotoUrl;
                $replacePhotoSource = true;
            }
        }
    }

    if (empty($errors)) {
        if ($isEdit) {
            $productModel->update($id, $values);
            if ($replacePhotoSource) {
                // Delete old photo file if any
                $oldPhoto = $product['photo_filename'] ?? null;
                $shouldDeleteOldPhoto = $oldPhoto && $newPhotoFilename !== $oldPhoto;
                if ($shouldDeleteOldPhoto) {
                    $oldPath = ROOT_DIR . '/public/uploads/shop/' . $oldPhoto;
                    if (is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }
                $productModel->updatePhoto($id, $newPhotoFilename, $newExternalPhotoUrl);
            }
            flash('success', 'Produit modifié avec succès.');
        } else {
            $newId = $productModel->create(array_merge($values, [
                'photo_filename' => $newPhotoFilename,
                'external_photo_url' => $newExternalPhotoUrl,
            ]));
            flash('success', 'Produit créé avec succès.');
        }
        header('Location: ' . APP_BASE_URL . '/admin/shop-products.php');
        exit;
    }
}

$pageTitle = $isEdit ? 'Modifier le produit' : 'Nouveau produit';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title"><?= $isEdit ? '✏️ Modifier le produit' : '➕ Nouveau produit' ?></h1>

    <?php if ($errors): ?>
        <div class="flash flash--error">
            <ul style="margin:0;padding-left:1.2rem">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" enctype="multipart/form-data" style="max-width:600px">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

        <div class="form-group">
            <label for="name">Nom du produit *</label>
            <input type="text" id="name" name="name" required value="<?= e($values['name']) ?>">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= e($values['description']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="price_euros">Prix (€) *</label>
            <input type="number" id="price_euros" name="price_euros" required min="0" step="0.01"
                   value="<?= number_format((int) $values['price_cents'] / 100, 2, '.', '') ?>">
        </div>

        <div class="form-group">
            <label for="portion_count">Nombre de portions *</label>
            <input type="number" id="portion_count" name="portion_count" required min="1" step="1"
                   value="<?= max(1, (int) $values['portion_count']) ?>">
        </div>

        <div class="form-group">
            <label for="min_order_portions">Minimum de portions par commande *</label>
            <input type="number" id="min_order_portions" name="min_order_portions" required min="1" step="1"
                   value="<?= max(1, (int) $values['min_order_portions']) ?>">
        </div>

        <div class="form-group form-group--checkbox">
            <input type="checkbox" id="is_available" name="is_available" value="1"
                   <?php
                   $avail = $values['is_available'];
                   if ($avail === true || $avail === 't' || $avail === 1 || $avail === '1') echo 'checked';
                   ?>>
            <label for="is_available">✅ Produit disponible à la vente</label>
        </div>

        <div class="form-group">
            <label>Photo du produit</label>
            <?php
            $currentImgSrc = $isEdit ? shopProductImageSrc($product) : null;
            ?>
            <?php if ($isEdit && $currentImgSrc !== null): ?>
                <div style="margin-bottom:.5rem">
                    <img src="<?= e($currentImgSrc) ?>"
                         alt="<?= e($product['name']) ?>"
                         style="width:120px;height:120px;object-fit:cover;border-radius:8px">
                    <p style="font-size:.85rem;color:var(--color-muted);margin-top:.25rem">
                        Photo actuelle. Sélectionnez une nouvelle source pour la remplacer.
                    </p>
                </div>
            <?php endif; ?>
            <div style="display:flex;gap:.5rem;margin-bottom:1rem">
                <button type="button" id="tab-upload" class="btn btn--secondary btn--sm"
                        onclick="showPhotoTab('upload')" style="font-weight:bold">📁 Fichier local</button>
                <button type="button" id="tab-url" class="btn btn--secondary btn--sm"
                        onclick="showPhotoTab('url')">🔗 URL externe</button>
            </div>
            <div id="panel-upload">
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                <p style="font-size:.85rem;color:var(--color-muted);margin-top:.35rem">
                    JPEG, PNG ou WebP (8 Mo max).
                </p>
            </div>
            <div id="panel-url" style="display:none">
                <input type="url" id="external_photo_url" name="external_photo_url"
                       value="<?= e($values['external_photo_url']) ?>"
                       placeholder="https://example.com/photo.jpg"
                       style="width:100%;box-sizing:border-box">
            </div>
        </div>

        <div style="display:flex;gap:1rem;margin-top:1.5rem">
            <button type="submit" class="btn btn--primary"><?= $isEdit ? 'Enregistrer' : 'Créer le produit' ?></button>
            <a href="<?= APP_BASE_URL ?>/admin/shop-products.php" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>
<script>
function showPhotoTab(tab) {
    document.getElementById('panel-upload').style.display = tab === 'upload' ? '' : 'none';
    document.getElementById('panel-url').style.display = tab === 'url' ? '' : 'none';
    document.getElementById('tab-upload').style.fontWeight = tab === 'upload' ? 'bold' : '';
    document.getElementById('tab-url').style.fontWeight = tab === 'url' ? 'bold' : '';
    document.getElementById('photo').disabled = tab !== 'upload';
    document.getElementById('external_photo_url').disabled = tab !== 'url';
}
<?php if (!empty($values['external_photo_url'])): ?>
showPhotoTab('url');
<?php else: ?>
showPhotoTab('upload');
<?php endif; ?>
</script>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
