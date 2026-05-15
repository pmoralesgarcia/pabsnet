<?php
define('RANDOM_BYTE_COUNT', 32);

// Check for existing config
$configfile = __DIR__ . '/config.php';
$configured = file_exists($configfile);

if (isset($_POST['username']) && isset($_POST['password']) && !$configured) {
    $app_key = bin2hex(random_bytes(RANDOM_BYTE_COUNT));
    $user_url = $_POST['username'];
    // Hash using BCrypt
    $user_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $app_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . str_replace('setup.php', '', $_SERVER['REQUEST_URI']);

    $contents = "<?php\n";
    $contents .= "define('APP_URL', '" . addslashes($app_url) . "');\n";
    $contents .= "define('APP_KEY', '$app_key');\n";
    $contents .= "define('USER_HASH', '" . addslashes($user_hash) . "');\n";
    $contents .= "define('USER_URL', '" . addslashes($user_url) . "');\n";

    if (is_writable(__DIR__)) {
        file_put_contents($configfile, $contents);
        $success = "Config saved successfully! Please delete setup.php now.";
    } else {
        $manual_config = $contents;
    }
}
?>
<!doctype html>
<html>
<head>
    <title>Setup Selfauth</title>
    <link rel="stylesheet" href="https://cdn.simplecss.org/simple.min.css">
    <style>body{max-width:600px;margin:50px auto; text-align:center;}</style>
</head>
<body>
    <h1>Setup Selfauth</h1>
    <?php if ($configured): ?>
        <p>System already configured. Delete <code>config.php</code> to reset.</p>
    <?php elseif (isset($success)): ?>
        <p style="color:green"><?php echo $success; ?></p>
    <?php elseif (isset($manual_config)): ?>
        <p>Could not write file. Create <code>config.php</code> manually with:</p>
        <pre><?php echo htmlspecialchars($manual_config); ?></pre>
    <?php else: ?>
        <form method="POST">
            <label>Your Personal URL (e.g. https://example.com)</label>
            <input name="username" type="url" required placeholder="https://..." />
            <label>Set Password</label>
            <input name="password" type="password" required />
            <br><br>
            <button type="submit" style="width:100%">Generate Configuration</button>
        </form>
    <?php endif; ?>
</body>
</html>