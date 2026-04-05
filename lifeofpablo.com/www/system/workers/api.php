<?php
// API extension for Datenstrom Yellow

class YellowApi {
    const VERSION = "1.1.0";
    public $yellow;

    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }

    public function onRequest($scheme, $address, $base, $location, $fileName) {
        if (substru($location, 0, 5) == "/api/") {
            return $this->getApiResponse($location);
        }
        return null;
    }

    public function getApiResponse($location) {
        // Normalize path: /api/blog/ -> /blog
        $slug = trim(substru($location, 5), "/");
        $path = "/" . $slug;
        
        // Dynamic Domain detection
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST'];
        $base = $protocol . $domain;

        // 1. Handle "All" or Root Discovery
        if ($slug == "" || $slug == "all") {
            $pages = $this->yellow->content->index(true); 
            $pages->sort("published", false);
            $data = $this->buildDataArray($pages, $base);
        } else {
            // 2. Try to find a specific page (e.g., /api/blog/post-title)
            $page = $this->yellow->content->find($path);

            // Only return as a single object if it's NOT a folder-start page (layout-start)
            if ($page && $page->get("status") != "hidden" && !str_ends_with($page->get("layout"), "-start")) {
                $data = $this->formatPageData($page, $base);
            } else {
                // 3. Fallback to Folder Filtering (e.g., /api/blog)
                $pages = $this->yellow->content->index(true);
                // Regex matches folder names regardless of numeric prefixes (e.g., 3-blog)
                $pages->match("#/[\d\-\_\.]*".$slug."/#i", false);
                $pages->sort("published", false);
                
                $data = $this->buildDataArray($pages, $base);
            }
        }

        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    // Helper to process a collection of pages
    private function buildDataArray($pages, $base) {
        $data = [];
        foreach ($pages as $p) {
            if ($p->get("status") == "hidden" || $p->get("layout") == "system") continue;
            $data[] = $this->formatPageData($p, $base);
        }
        return $data;
    }

    private function formatPageData($page, $base) {
        return [
            "title"      => $page->get("title"),
            "author"     => $page->get("author"),
            "published"  => $page->get("published"),
            "tag"        => $page->get("tag"),
            "page_image" => $page->get("image"),
            "content"    => $page->getContentHtml(),
            "url_full"   => $base . $page->getLocation(),
            "url_path"   => $page->getLocation()
        ];
    }
}