<?php
/**
 * AlmancaPro - Cikis.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
app_boot(false);

auth_logout();
flash('success', 'Çıkış yapıldı. İlerlemen kayıtlı, istediğin zaman devam edebilirsin.');
redirect('/login.php');
