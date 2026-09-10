<?php
/** AlmancaPro - Yonetici cikisi. */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot(false);

admin_logout_session();
flash('success', 'Yönetici oturumu kapatıldı.');
redirect('/admin-login.php');
