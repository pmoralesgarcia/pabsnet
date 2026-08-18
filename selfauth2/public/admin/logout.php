<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Session;

Session::logout();
header('Location: login.php');
exit;
