<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('info', 'You have been logged out securely. See you soon!');
header('Location: login.php');
exit;
