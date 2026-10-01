<?php
require_once __DIR__ . '/../core/Auth.php';

Auth::logoutAdmin();
header('Location: /');
exit;
