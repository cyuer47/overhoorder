<?php
require_once __DIR__ . '/php/db.php';
session_unset();
session_destroy();
header('Location: login.php');
exit;
