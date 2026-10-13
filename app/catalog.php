<?php
/* ============================================================
   Catalogue : lecture des articles, prix, images envoyées
   ============================================================ */

const UPLOAD_DIR = ROOT . '/assets/products/uploads';
const UPLOAD_MAX_BYTES = 2 * 1024 * 1024; // 2 Mo
const UPLOAD_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** Tous les articles, du plus récent au plus ancien (ou les $limit premiers) */
function products(?int $limit = null): array
{
    $sql = 'SELECT * FROM products ORDER BY id DESC' . ($limit ? ' LIMIT ' . (int) $limit : '');
    return db()->query($sql)->fetchAll();
}

function product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** 1299.9 → « 1 299,90 € » */
function format_price(string|float $price): string
{
    return number_format((float) $price, 2, ',', "\u{202F}") . "\u{00A0}€";
}

/**
 * Enregistre l'image envoyée ($_FILES['image']).
 * Renvoie [chemin relatif ou null si aucun fichier, message d'erreur ou null].
 */
function save_uploaded_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        return [null, 'L\'image dépasse 2 Mo.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'L\'envoi de l\'image a échoué.'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return [null, 'L\'image dépasse 2 Mo.'];
    }
    // Type réel lu dans le contenu (le nom et le type annoncés sont ignorés)
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(UPLOAD_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return [null, 'Format refusé : JPEG, PNG ou WebP uniquement.'];
    }
    $name = bin2hex(random_bytes(12)) . '.' . UPLOAD_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        return [null, 'Impossible d\'enregistrer l\'image (droits d\'écriture ?).'];
    }
    return ['assets/products/uploads/' . $name, null];
}

/** Supprime une image envoyée ; les images d'origine (hors uploads/) ne sont jamais touchées */
function delete_uploaded_image(?string $path): void
{
    if ($path && preg_match('#^assets/products/uploads/[a-f0-9]{24}\.(jpg|png|webp)$#', $path)) {
        @unlink(ROOT . '/' . $path);
    }
}
