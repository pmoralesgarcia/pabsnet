<?php

require_once __DIR__ . '/../src/Auth.php';

use Selfauth\Auth;

?><html>
<head>
<meta charset="utf-8">
<title>Setup Selfauth</title>
<style>
h1{text-align:center;margin-top:5%;}
h2{text-align:center;}
.instructions{text-align:center;font-family:sans-serif;}
.message{margin-top:20px;text-align:center;font-size:1.2em;font-weight:bold;}
pre {width:400px; margin-left:auto; margin-right:auto;margin-bottom:50px; overflow-wrap:anywhere; white-space:pre-wrap;}
form{
margin-left:auto;
width:300px;
margin-right:auto;
text-align:center;
margin-top:20px;
border:solid 1px black;
padding:20px;
}
.form-line{ margin-top:5px;}
.submit{width:100%}
</style>
</head>
<body>
<h1>Setup Selfauth</h1>
<div>
<?php
// Note: if you are running Selfauth via the provided Docker image, you do
// not need this page at all -- configure everything with environment
// variables instead (SELFAUTH_APP_URL, SELFAUTH_USER_URL,
// SELFAUTH_ADMIN_PASSWORD). This file is only for classic shared-hosting
// style installs without Docker.

$app_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']
  . str_replace('setup.php', '', $_SERVER['REQUEST_URI']);

$bytes = random_bytes(32);
$app_key = bin2hex($bytes);

$configfile = __DIR__ . '/../config.php';

$configured = true;

if (file_exists($configfile)) {
    include_once $configfile;

    if ((!defined('APP_URL') || APP_URL == '')
        || (!defined('APP_KEY') || APP_KEY == '')
        || (!defined('USER_HASH') || USER_HASH == '')
        || (!defined('USER_URL') || USER_URL == '')
    ) {
        $configured = false;
    }
} else {
    $configured = false;
}

if ($configured) : ?>
    <h2>System already configured</h2>
    <div class="instructions">
        If you wish to reconfigure, please remove config.php and reload this page.
    </div>

<?php else : ?>

    <div class="instructions">In order to configure Selfauth, you need to fill in a few values, this page helps generate those options. Passwords are hashed with Argon2id.</div>
    <?php if (isset($_POST['username'])) : ?>
    <div>
    <?php
    $app_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . str_replace('setup.php', '', $_SERVER['REQUEST_URI']);

    $user = filter_var($_POST['username'], FILTER_VALIDATE_URL) ? $_POST['username'] : '';
    $password = (string) $_POST['password'];

    if ($user === '' || $password === '') {
        echo '<div class="message">Please provide a valid URL and a non-empty password.</div>';
    } else {
        $hash = Auth::hashPassword($password);

        $config_file_contents = "<?php
define('APP_URL', '$app_url');
define('APP_KEY', '$app_key');
define('USER_HASH', '$hash');
define('USER_URL', '$user');
";

        $file_written = false;

        if (is_writeable(dirname($configfile)) && !$configured) {
            $handle = fopen($configfile, 'w');
            if ($handle) {
                $result = fwrite($handle, $config_file_contents);
                if ($result !== false) {
                    $file_written = true;
                }
                fclose($handle);
            }
        }

        if ($file_written) {
            echo '<div class="message">config.php was successfully written to disk</div>';
        } else {
            echo '<div class="message">Fill in the file config.php (in the application root, one level up from public/) with the following content</div>';
            echo '<pre>' . htmlentities($config_file_contents) . '</pre>';
        }
    }
    ?>
    </div>
    <?php endif ?>
    <form method="POST" action="">
    <div class="form-line"><label>Login Url:</label> <input name='username' placeholder="https://example.com" /></div>
    <div class="form-line"><label>Password:</label> <input type='password' name='password' /></div>
    <div class="form-line"><input class="submit" type="submit" name="submit" value="Generate Config"/></div>
    </form>
<?php endif; ?>
</body>
</html>
