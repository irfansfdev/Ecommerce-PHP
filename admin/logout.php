<?php
require_once __DIR__ . '/../core/Auth.php';

Auth::logout();
header('Location: /');
exit;
