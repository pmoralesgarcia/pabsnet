<?php
class YellowIndieWeb {
    const VERSION = "1.3.1";
    public $yellow;

    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("indieWebAuthLocation", "/login/");
    }

    public function onRequest($scheme, $address, $base, $location, $fileName) {
        $authLocation = $this->yellow->system->get("indieWebAuthLocation");

        // Metadata discovery for IndieAuth
        if ($location == "/.well-known/oauth-authorization-server") {
            return $this->renderMetadata();
        }

        // Main login and server endpoint
        if ($location == $authLocation) {
            return $this->processLogin();
        }
        return null;
    }

    private function renderMetadata() {
        $url = $this->getSiteUrl() . $this->yellow->system->get("indieWebAuthLocation");
        $metadata = [
            "issuer" => $this->getSiteUrl() . "/",
            "authorization_endpoint" => $url,
            "token_endpoint" => $url,
            "code_challenge_methods_supported" => ["S256"]
        ];
        header("Content-Type: application/json");
        echo json_encode($metadata);
        exit;
    }

    private function processLogin() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $method = $_SERVER["REQUEST_METHOD"];
        $action = $_REQUEST["action"] ?? "";

        // Server-side: External site asking to identify you
        if (isset($_GET['response_type']) && $_GET['response_type'] == 'code') {
            return $this->renderAuthorizationPrompt();
        }

        // Standard Actions
        if ($action == "logout") {
            unset($_SESSION["indieauth_me"], $_SESSION["indieauth_name"], $_SESSION["indieauth_pic"], $_SESSION["indieauth_note"]);
            header("Location: " . $this->getSiteUrl() . "/");
            exit;
        }

        if ($action == "callback") return $this->handleCallback();
        if ($method == "POST" && $action == "start") return $this->startIndieAuth();

        return $this->renderSimpleForm();
    }

    private function startIndieAuth() {
        $me = filter_var($_POST["me"], FILTER_SANITIZE_URL);
        if (empty($me)) return $this->yellow->page->error(400, "Domain required");
        if (!preg_match('/^https?:\/\//', $me)) $me = "https://" . $me;

        $state = bin2hex(random_bytes(16));
        $_SESSION["indieweb_state"] = $state;

        $params = http_build_query([
            "me"            => $me,
            "client_id"     => $this->getSiteUrl() . "/",
            "redirect_uri"  => $this->getSiteUrl() . $this->yellow->system->get("indieWebAuthLocation") . "?action=callback",
            "state"         => $state,
            "response_type" => "code"
        ]);

        header("Location: https://indieauth.com/auth?" . $params);
        exit;
    }

    private function handleCallback() {
        $state = $_GET["state"] ?? "";
        $sessionState = $_SESSION["indieweb_state"] ?? "";
        if (empty($state) || $state !== $sessionState) die("Invalid state.");

        $postData = http_build_query([
            "code"         => $_GET["code"],
            "client_id"    => $this->getSiteUrl() . "/",
            "redirect_uri" => $this->getSiteUrl() . $this->yellow->system->get("indieWebAuthLocation") . "?action=callback",
        ]);

        $opts = ["http" => [
            "method" => "POST",
            "header" => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            "content" => $postData
        ]];

        $context = stream_context_create($opts);
        $response = @file_get_contents("https://indieauth.com/auth", false, $context);
        $data = json_decode($response, true) ?: parse_str($response, $output);
        $me = $data["me"] ?? $output["me"] ?? null;

        if ($me) {
            $_SESSION["indieauth_me"] = $me;
            $homepage = @file_get_contents($me);
            if ($homepage) {
                if (preg_match('/<img[^>]+class=["\'][^"\']*u-photo[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/', $homepage, $match)) {
                    $_SESSION["indieauth_pic"] = $this->absoluteUrl($match[1], $me);
                } elseif (preg_match('/<link[^>]+rel=["\'](?:icon|apple-touch-icon|shortcut icon)["\'][^>]+href=["\']([^"\']+)["\']/', $homepage, $match)) {
                    $_SESSION["indieauth_pic"] = $this->absoluteUrl($match[1], $me);
                }
                if (preg_match('/<[^>]+class=["\'][^"\']*p-name[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/s', $homepage, $match)) {
                    $_SESSION["indieauth_name"] = trim(strip_tags($match[1]));
                }
                if (preg_match('/<[^>]+class=["\'][^"\']*p-note[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/s', $homepage, $match)) {
                    $_SESSION["indieauth_note"] = trim(strip_tags($match[1]));
                }
            }
            header("Location: " . $this->getSiteUrl() . "/");
            exit;
        }
        die("Auth Failed");
    }

    private function renderAuthorizationPrompt() {
        $client_id = $_GET['client_id'] ?? 'Unknown Site';
        $redirect_uri = $_GET['redirect_uri'] ?? '';
        $state = $_GET['state'] ?? '';

        echo "<html><head><title>Authorize Login</title></head><body style='font-family:sans-serif; background:#f4f4f4; padding:2em;'>";
        echo "<div style='max-width:450px; margin:auto; background:#fff; padding:2em; border-radius:15px; border:1px solid #ccc; text-align:center;'>";
        echo "<h2>Authorize Login</h2>";
        echo "<p>The site <strong>" . htmlspecialchars($client_id) . "</strong> wants to identify you as <strong>" . htmlspecialchars($this->getSiteUrl()) . "</strong>.</p>";
        
        $loggedInMe = rtrim($_SESSION['indieauth_me'] ?? '', '/');
        $siteMe = rtrim($this->getSiteUrl(), '/');

        if ($loggedInMe == $siteMe) {
            $approveUrl = $redirect_uri . (strpos($redirect_uri, '?') !== false ? '&' : '?') . "code=VALID_CODE&state=" . $state;
            echo "<a href='".htmlspecialchars($approveUrl)."' style='display:block; background:black; color:white; padding:12px; text-decoration:none; border-radius:5px;'>Approve & Log In</a>";
        } else {
            echo "<p style='color:red;'>You must be signed into your site locally first.</p>";
            echo "<a href='?'>Sign in to " . htmlspecialchars($siteMe) . "</a>";
        }
        echo "<br><br><a href='" . htmlspecialchars($redirect_uri) . "' style='color:#666;'>Cancel</a>";
        echo "</div></body></html>";
        exit;
    }

    private function renderSimpleForm() {
        $user = $_SESSION["indieauth_me"] ?? "";
        $pic = $_SESSION["indieauth_pic"] ?? "";
        $name = $_SESSION["indieauth_name"] ?? "";
        $note = $_SESSION["indieauth_note"] ?? "";
        
        echo "<html><head><title>User Profile</title></head><body style='font-family:sans-serif; background:#f4f4f4; padding:2em; line-height: 1.6;'>";
        if ($user) {
            echo "<div style='max-width: 450px; margin: 40px auto; background:#fff; text-align: center; border: 1px solid #ccc; padding: 2.5em; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>";
            if ($pic) echo "<img src='".htmlspecialchars($pic)."' style='width:120px; height:120px; border-radius:50%; margin-bottom:1em; object-fit:cover; border: 3px solid #eee;'>";
            $displayName = $name ?: parse_url($user, PHP_URL_HOST);
            echo "<h2 style='margin:0;'>" . htmlspecialchars($displayName) . "</h2>";
            echo "<p style='color:#666; font-size:0.9em;'>" . htmlspecialchars($user) . "</p>";
            if ($note) echo "<p style='font-style:italic; background:#f9f9f9; padding:10px; border-radius:5px;'>" . htmlspecialchars($note) . "</p>";
            echo "<hr style='border:0; border-top:1px solid #eee; margin: 2em 0;'>";
            echo "<a href='?action=logout' style='color: #d33; text-decoration: none;'>Logout</a> | <a href='" . $this->getSiteUrl() . "/' style='text-decoration: none; color:#333;'>Return Home</a>";
            echo "</div>";
        } else {
            echo "<div style='max-width: 400px; margin: 40px auto; background:#fff; padding: 2.5em; border-radius: 15px; border: 1px solid #ccc;'>";
            echo "<h2>Sign in</h2><form method='POST' action='?action=start'><input type='url' name='me' placeholder='https://yourdomain.com' required style='padding:12px; width: 100%; box-sizing: border-box; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 5px;'><button type='submit' style='padding:12px; width: 100%; background: #333; color: #fff; border: none; border-radius: 5px; cursor: pointer;'>Sign In</button></form></div>";
        }
        echo "</body></html>";
        exit;
    }

    private function getSiteUrl() {
        $protocol = $this->yellow->system->get("serverScheme") ?: "http";
        $address = $this->yellow->system->get("serverAddress") ?: $_SERVER['HTTP_HOST'];
        return rtrim($protocol . "://" . $address . $this->yellow->system->get("serverBase"), '/');
    }

    private function absoluteUrl($url, $base) {
        if (parse_url($url, PHP_URL_SCHEME) != '') return $url;
        $baseParts = parse_url($base);
        if (strpos($url, '/') === 0) return $baseParts['scheme'] . "://" . $baseParts['host'] . $url;
        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }
}