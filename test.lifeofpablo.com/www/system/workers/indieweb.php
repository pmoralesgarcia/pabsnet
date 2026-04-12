<?php
// IndieWeb extension for Datenstrom Yellow
// Acts as an IndieAuth Provider, Webmention Receiver, and Micropub Server

class YellowIndieWeb {
    const VERSION = "0.2.5";
    public $yellow;

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("indieWebMentionLocation", "/webmention/");
        $this->yellow->system->setDefault("indieWebAuthLocation", "/login/");
        $this->yellow->system->setDefault("indieWebMicropubLocation", "/micropub/");
        $this->yellow->system->setDefault("indieWebFile", "webmentions.ini");
        $this->yellow->system->setDefault("indieWebAuthFile", "indieauth_codes.ini");
    }

    // Bulletproof way to get the base URL for localhost or live domains
    public function getBaseUrl() {
        $protocol = $this->yellow->lookup->getProtocol();
        if (empty($protocol)) $protocol = "http"; 
        
        $server = $this->yellow->lookup->getServer();
        $base = $this->yellow->lookup->getBase();
        
        return $protocol . "://" . $server . $base;
    }

    // Universal response helper to handle various Yellow versions
    public function sendResponse($statusCode, $content, $contentType = "text/html; charset=utf-8") {
        $this->yellow->page->statusCode = $statusCode;
        header("Content-Type: $contentType");
        echo $content;
        exit;
    }

    // Inject IndieWeb Discovery tags into the site head
    public function onParsePageExtra($page, $name) {
        $baseUrl = $this->getBaseUrl();
        
        if ($name == "header") {
            $auth = $baseUrl . $this->yellow->system->get("indieWebAuthLocation");
            $micro = $baseUrl . $this->yellow->system->get("indieWebMicropubLocation");
            $mention = $baseUrl . $this->yellow->system->get("indieWebMentionLocation");

            $output =  '<link rel="authorization_endpoint" href="'.$auth.'">' . "\n";
            $output .= '<link rel="token_endpoint" href="'.$auth.'">' . "\n";
            $output .= '<link rel="micropub" href="'.$micro.'">' . "\n";
            $output .= '<link rel="webmention" href="'.$mention.'">' . "\n";
            return $output;
        }

        if ($name == "footer") {
            return $this->renderWebmentions($page);
        }
        return null;
    }

    // Routing for the virtual IndieWeb URLs
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        $authLoc = $this->yellow->system->get("indieWebAuthLocation");
        $mentionLoc = $this->yellow->system->get("indieWebMentionLocation");
        $microLoc = $this->yellow->system->get("indieWebMicropubLocation");

        if ($location == $authLoc) return $this->handleIndieAuth();
        if ($location == $mentionLoc) return $this->handleWebmention();
        if ($location == $microLoc) return $this->handleMicropub();
        
        return null;
    }

    // --- SELF-HOSTED INDIEAUTH PROVIDER ---
    private function handleIndieAuth() {
        $method = $_SERVER["REQUEST_METHOD"];
        $authFile = $this->yellow->system->get("coreExtensionDirectory") . $this->yellow->system->get("indieWebAuthFile");

        if ($method == "GET") {
            if (!isset($_GET['response_type'])) {
                return $this->sendResponse(200, "IndieAuth Endpoint Active.");
            }

            // Verify user is logged in as Admin/Edit
            if (!$this->yellow->user->isUser("edit")) {
                $loginUrl = $this->yellow->lookup->getBase() . $this->yellow->system->get("editLocation") . "login/";
                header("Location: " . $loginUrl . "?return=" . urlencode($_SERVER['REQUEST_URI']));
                exit;
            }

            // Process Approval
            if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
                $code = bin2hex(random_bytes(16));
                $this->yellow->user->save($authFile, $code, [
                    "client_id" => $_GET['client_id'],
                    "redirect_uri" => $_GET['redirect_uri'],
                    "state" => $_GET['state'],
                    "me" => $this->getBaseUrl() . '/',
                    "expires" => (string)(time() + 300)
                ]);
                
                $connector = (strpos($_GET['redirect_uri'], '?') === false) ? '?' : '&';
                $redirect = $_GET['redirect_uri'] . $connector . http_build_query(['code' => $code, 'state' => $_GET['state']]);
                header("Location: " . $redirect);
                exit;
            }
            return $this->renderAuthScreen($_GET);
        }

        // Token Exchange
        if ($method == "POST") {
            $code = $_POST['code'] ?? '';
            $codes = $this->yellow->user->load($authFile);
            if (is_array($codes) && isset($codes[$code]) && $codes[$code]['redirect_uri'] == $_POST['redirect_uri']) {
                $this->yellow->user->save($authFile, $code, []); // Cleanup
                return $this->sendResponse(200, json_encode(['me' => $codes[$code]['me']]), "application/json");
            }
            return $this->sendResponse(400, "Invalid Code");
        }
    }

    // --- MICROPUB SERVER ---
    private function handleMicropub() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $content = $_POST['content'] ?? '';
            $title = $_POST['name'] ?? 'Post ' . date("Y-m-d H:i");
            $slug = $this->yellow->lookup->normaliseName($title);
            $fileName = "content/1-blog/" . date("Y-m-d-His") . "-$slug.md";
            $fileData = "---\nTitle: $title\n---\n$content";
            
            if ($this->yellow->toolbox->createFile($fileName, $fileData)) {
                return $this->sendResponse(201, "Created");
            }
        }
        return $this->sendResponse(405, "Method Not Allowed");
    }

    // --- WEBMENTION RECEIVER ---
    private function handleWebmention() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $source = $_POST['source'] ?? '';
            $target = $_POST['target'] ?? '';
            if (empty($source) || empty($target)) return $this->sendResponse(400, "Missing parameters");

            $path = $this->yellow->system->get("coreExtensionDirectory") . $this->yellow->system->get("indieWebFile");
            $id = substr(hash("sha256", $source.$target), 0, 8);
            
            $this->yellow->user->save($path, $id, ["source" => $source, "target" => $target, "date" => date("Y-m-d H:i")]);
            return $this->sendResponse(202, "Accepted");
        }
        return $this->sendResponse(405, "Method Not Allowed");
    }

    private function renderWebmentions($page) {
        $path = $this->yellow->system->get("coreExtensionDirectory") . $this->yellow->system->get("indieWebFile");
        $data = $this->yellow->user->load($path);
        if (!is_array($data)) return ""; 
        
        $items = "";
        foreach ($data as $m) {
            if (isset($m['target']) && strpos($m['target'], $page->getUrl()) !== false) {
                $items .= "<li><a href='".htmlspecialchars($m['source'])."'>".htmlspecialchars($m['source'])."</a> (".htmlspecialchars($m['date']).")</li>";
            }
        }
        return $items ? "<div class='webmentions' style='margin-top:2em; border-top:1px solid #eee;'><h3>Webmentions</h3><ul>$items</ul></div>" : "";
    }

    private function renderAuthScreen($params) {
        $client = htmlspecialchars($params['client_id'] ?? 'Unknown Application');
        $output = "<h2>Authorize Application</h2><p>The application <strong>$client</strong> wants to identify you.</p>";
        $output .= "<a href='?".$_SERVER['QUERY_STRING']."&confirm=yes' style='display:inline-block;background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>Authorize and Continue</a>";
        return $this->sendResponse(200, $output);
    }
}