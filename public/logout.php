<?php
require_once __DIR__ . '/../core/Auth.php';

Auth::logoutCustomer();
header('Location: index.php');
exit;
