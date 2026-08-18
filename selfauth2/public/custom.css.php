<?php

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: private, max-age=60');
echo SELFAUTH_CUSTOM_CSS;
