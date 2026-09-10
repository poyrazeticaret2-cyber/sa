<?php
/** AlmancaPro - /path.php adresi ogrenme yoluna yonlendirir. */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
redirect('/course.php' . (isset($_GET['locked']) ? '?locked=' . (int)$_GET['locked'] . '#lock' : ''));
