<?php
/* Déconnexion (POST + CSRF uniquement) */

require __DIR__ . '/../app/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
if ($admin = current_admin()) {
    log_event('event', 'Déconnexion : ' . $admin['email']);
}
logout();
flash('success', 'Vous êtes déconnecté.');
redirect('login.php');
