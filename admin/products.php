<?php
/* ============================================================
   Module « Articles » : ajouter, modifier, supprimer
   les articles affichés sur la page « Nos Produits »
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';
require ROOT . '/app/catalog.php';

$admin = require_login();

$editing = isset($_GET['edit']) ? product((int) $_GET['edit']) : null;
$form = $editing ?? ['id' => 0, 'name' => '', 'price' => '', 'description' => '', 'image' => null];

if (is_post()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $item = product((int) ($_POST['id'] ?? 0));
        if ($item) {
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$item['id']]);
            delete_uploaded_image($item['image']);
            log_event('event', "Article supprimé : {$item['name']} par {$admin['email']}");
            flash('success', "« {$item['name']} » a été supprimé.");
        }
        redirect('products.php');
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id ? product($id) : null;
        $form = [
            'id' => $existing ? $id : 0,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'price' => str_replace([',', ' ', "\u{202F}", "\u{00A0}"], ['.', '', '', ''], trim((string) ($_POST['price'] ?? ''))),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'image' => $existing['image'] ?? null,
        ];

        $errors = [];
        if ($form['name'] === '' || mb_strlen($form['name']) > 120) {
            $errors[] = 'Le nom est requis (120 caractères maximum).';
        }
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $form['price'])) {
            $errors[] = 'Prix invalide : un nombre positif, ex. 129,99.';
        }
        if (mb_strlen($form['description']) > 500) {
            $errors[] = 'La description ne doit pas dépasser 500 caractères.';
        }
        if ($id && !$existing) {
            $errors[] = 'Cet article n\'existe plus.';
        }

        if (!$errors) {
            [$newImage, $uploadError] = save_uploaded_image($_FILES['image'] ?? []);
            if ($uploadError) {
                $errors[] = $uploadError;
            }
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('error', $error);
            }
            $editing = $existing;
        } else {
            $removeImage = !empty($_POST['remove_image']);
            $image = $newImage ?? ($removeImage ? null : $form['image']);
            if ($image !== $form['image']) {
                delete_uploaded_image($form['image']); // ancienne image remplacée ou retirée
            }

            if ($existing) {
                db()->prepare('UPDATE products SET name = ?, price = ?, description = ?, image = ? WHERE id = ?')
                    ->execute([$form['name'], $form['price'], $form['description'], $image, $id]);
                log_event('event', "Article modifié : {$form['name']} par {$admin['email']}");
                flash('success', "« {$form['name']} » a été mis à jour.");
            } else {
                db()->prepare('INSERT INTO products (name, price, description, image) VALUES (?, ?, ?, ?)')
                    ->execute([$form['name'], $form['price'], $form['description'], $image]);
                log_event('event', "Article ajouté : {$form['name']} par {$admin['email']}");
                flash('success', "« {$form['name']} » a été ajouté au catalogue.");
            }
            redirect('products.php');
        }
    }
}

$items = products();
admin_header('Articles', $admin, 'Les articles affichés sur la page « Nos Produits ».');
?>
<div class="split">
    <section class="panel split__main">
        <h2><?= count($items) ?> article(s) en ligne</h2>
        <?php if (!$items): ?>
            <p class="muted">Le catalogue est vide. Ajoutez un premier article avec le formulaire.</p>
        <?php endif; ?>
        <ul class="item-list">
            <?php foreach ($items as $item): ?>
                <li class="item<?= $editing && (int) $editing['id'] === (int) $item['id'] ? ' is-editing' : '' ?>">
                    <span class="item__thumb">
                        <?php if ($item['image']): ?>
                            <img src="../<?= e($item['image']) ?>" alt="">
                        <?php endif; ?>
                    </span>
                    <span class="item__text">
                        <strong><?= e($item['name']) ?></strong>
                        <span class="item__price"><?= e(format_price($item['price'])) ?></span>
                    </span>
                    <span class="item__actions">
                        <a class="btn btn--light btn--small" href="products.php?edit=<?= (int) $item['id'] ?>#article-form">Modifier</a>
                        <form action="products.php" method="post" data-confirm="Supprimer « <?= e($item['name']) ?> » du catalogue ?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="btn btn--danger btn--small">Supprimer</button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <form action="products.php<?= $editing ? '?edit=' . (int) $editing['id'] : '' ?>" method="post" enctype="multipart/form-data"
          class="contact-form split__side" id="article-form">
        <h2><?= $editing ? 'Modifier l\'article' : 'Ajouter un article' ?></h2>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

        <div class="form-control">
            <label for="name">Nom</label>
            <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="120" required>
        </div>
        <div class="form-control">
            <label for="price">Prix (€)</label>
            <input type="text" id="price" name="price" value="<?= e(str_replace('.', ',', (string) $form['price'])) ?>" inputmode="decimal" placeholder="129,99" required>
        </div>
        <div class="form-control">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" maxlength="500"><?= e($form['description']) ?></textarea>
        </div>
        <div class="form-control">
            <label for="image">Image <?= $form['image'] ? '(remplacer)' : '' ?></label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
            <small class="form-hint">JPEG, PNG ou WebP, 2 Mo maximum.</small>
        </div>
        <?php if ($form['image']): ?>
            <div class="form-check">
                <img class="form-preview" src="../<?= e($form['image']) ?>" alt="Image actuelle">
                <label><input type="checkbox" name="remove_image" value="1"> Retirer l'image</label>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-submit"><?= $editing ? 'Enregistrer les modifications' : 'Ajouter l\'article' ?></button>
        <?php if ($editing): ?>
            <p class="form-link"><a href="products.php">Annuler et ajouter un nouvel article</a></p>
        <?php endif; ?>
    </form>
</div>
<?php admin_footer();
