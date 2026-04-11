<?php
// API extension for Datenstrom Yellow

class YellowApi {
    const VERSION = "1.2.1";
    public $yellow;

    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }

    public function onRequest($scheme, $address, $base, $location, $fileName) {
        if (substru($location, 0, 8) == "/api/v1/") {
            return $this->getApiResponse($location);
        }
        return null;
    }

    public function getApiResponse($location) {
        $slug = trim(substru($location, 8), "/");
        $path = "/" . $slug;
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $base = $protocol . $_SERVER['HTTP_HOST'];

        // 1. Taxonomy Endpoint: Tags
        if ($slug == "tags") {
            return $this->renderJson($this->getTaxonomyData("tag"));
        }

        // 2. Search Functionality (Fixed Regex Error)
        if ($slug == "search") {
            $query = $_REQUEST['q'] ?? '';
            $pages = $this->yellow->content->index(true);
            if (!empty($query)) {
                // We use a regex delimiter '#' to safely wrap the search query
                $pages->match("#".preg_quote($query)."#i"); 
            }
            return $this->renderJson($this->buildDataArray($pages, $base));
        }

        // 3. Individual Page & Related Content
        $page = $this->yellow->content->find($path);
        if ($page && $page->get("status") != "hidden" && !str_ends_with($page->get("layout"), "-start")) {
            $data = $this->formatPageData($page, $base);
            
            // Related Content: Find posts with similar tags
            $tags = preg_split("/\s*,\s*/", $page->get("tag"));
            if (!empty($tags[0])) {
                $related = $this->yellow->content->index(true)
                    ->filter("tag", $tags[0])
                    ->filter("location", $page->getLocation(), false) // Exclude self
                    ->limit(3);
                foreach ($related as $r) {
                    $data["related"][] = [
                        "title" => $r->get("title"), 
                        "url" => $base . $r->getLocation()
                    ];
                }
            }
            return $this->renderJson($data);
        }

        // 4. Folder Listing & Pagination
        $pages = $this->yellow->content->index(true);
        if ($slug != "" && $slug != "all") {
            // Folder matching logic from Feed extension
            $pages->match("#/[\d\-\_\.]*".$slug."/#i", false);
        }
        
        $pages->sort("published", false);

        // Pagination: Use system default or custom limit
        $limit = $_REQUEST['limit'] ?? $this->yellow->system->get("feedPaginationLimit");
        $pages->paginate($limit); 
        
        $response = [
            "pagination" => [
                "current" => $pages->getPaginationNumber(),
                "total" => $pages->getPaginationCount(),
                "next" => $pages->getPaginationNext(),
                "prev" => $pages->getPaginationPrevious(),
            ],
            "items" => $this->buildDataArray($pages, $base)
        ];

        return $this->renderJson($response);
    }

    private function getTaxonomyData($key) {
        $items = [];
        foreach ($this->yellow->content->index(true) as $p) {
            $values = preg_split("/\s*,\s*/", $p->get($key));
            foreach ($values as $v) {
                if (!empty($v)) $items[$v] = ($items[$v] ?? 0) + 1;
            }
        }
        ksort($items);
        return $items;
    }

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

    private function renderJson($data) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}