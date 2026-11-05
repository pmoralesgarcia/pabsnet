<?php
// For end-to-end checks against a throwaway container, never production:
//   FAKE_GITHUB_STATE=/tmp/state.json php -S 127.0.0.1:9999 tests/fake-github-server.php
require __DIR__ . '/FakeGitHub.php';
require __DIR__ . '/FakeB2.php';

$stateFile = getenv('FAKE_GITHUB_STATE') ?: sys_get_temp_dir() . '/fake-github.json';
$state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : ['files' => [], 'commits' => []];

$github = new FakeGitHub(array_map('base64_decode', $state['files']));
$github->commits = $state['commits'];
$b2 = new FakeB2('http://' . $_SERVER['HTTP_HOST']);
foreach ($state['b2'] ?? [] as $name => $file) {
    $b2->files[$name] = ['type' => $file['type'], 'bytes' => (string) base64_decode($file['bytes'])];
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if ($key === 'HTTP_AUTHORIZATION') {
        $headers[] = 'Authorization: ' . $value;
    } elseif ($key === 'HTTP_X_BZ_FILE_NAME') {
        $headers[] = 'X-Bz-File-Name: ' . $value;
    } elseif ($key === 'HTTP_X_BZ_CONTENT_SHA1') {
        $headers[] = 'X-Bz-Content-Sha1: ' . $value;
    } elseif ($key === 'CONTENT_TYPE') {
        $headers[] = 'Content-Type: ' . $value;
    }
}
$isB2 = preg_match('#^/(b2api|upload)/#', $_SERVER['REQUEST_URI']) === 1;
[$status, $body] = ($isB2 ? $b2 : $github)->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $headers, (string) file_get_contents('php://input'));

file_put_contents($stateFile, json_encode([
    'files' => array_map('base64_encode', $github->files),
    'commits' => $github->commits,
    'b2' => array_map(fn($f) => ['type' => $f['type'], 'bytes' => base64_encode($f['bytes'])], $b2->files),
]));
http_response_code($status);
header('Content-Type: application/json');
echo $body;
