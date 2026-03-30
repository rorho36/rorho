<?php
require_once __DIR__ . '/auth.php';

auth_logout_user();
header('Location: login.php');
exit;

