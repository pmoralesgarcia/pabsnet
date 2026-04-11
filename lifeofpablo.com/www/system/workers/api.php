<?php
// API extension for Datenstrom Yellow

class YellowApi {
    const VERSION = "1.6.2";
    public $yellow;

    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("apiCacheTimeout", "3600");
    }

    public function onRequest($scheme, $address, $base, $location, $fileName) {
        if (substru($location, 0, 8) == "/api/v1/") {
            return $this->getApiResponse($location);
        }
        return null;
    }

    public function getApiResponse($location) {
        $slug = trim(substru($location, 8), "/");
        $isRandom = ($slug == "random");
        
        // 1. PERFORMANCE: Isolated Caching in Temp Folder
        $tempDir = $this->yellow->system->get("coreTmpDir") . "api-cache/";
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true); // Ensure the folder exists
        
        $cacheFile = $tempDir . md5($location . serialize($_REQUEST)) . ".json";
        
        if (!$isRandom && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->yellow->system->get("apiCacheTimeout")) {
            header("Content-Type: application/json; charset=utf-8");
            header("X-Cache: HIT");
            echo file_get_contents($cacheFile);
            exit;
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $base = $protocol . $_SERVER['HTTP_HOST'];
        $pages = $this->yellow->content->index(true);
        $data = null;

        // 2. SPECIAL ROUTES: Random and Post of the Day
        if ($slug == "random") {
            $pagesArray = $this->getCleanPagesArray($pages);
            $randomPage = $pagesArray[array_rand($pagesArray)];
            $data = $this->formatPageDataWithRelated($randomPage, $base);
        } 
        elseif ($slug == "post-of-the-day") {
            $pagesArray = $this->getCleanPagesArray($pages);
            mt_srand((int)date("Ymd"));
            $randomIndex = mt_rand(0, count($pagesArray) - 1);
            $potd = $pagesArray[$randomIndex];
            mt_srand(); 
            $data = $this->formatPageDataWithRelated($potd, $base);
        }

        // 3. TAXONOMY: Pretty Tags and Authors
        elseif (substru($slug, 0, 4) == "tags") {
            $tagName = trim(substru($slug, 4), "/");
            if (empty($tagName)) {
                $data = $this->getTaxonomyData("tag");
            } else {
                $pages->filter("tag", $tagName);
                $data = $this->wrapPagination($pages, $base);
            }
        } 
        elseif ($slug == "authors") {
            $data = $this->getTaxonomyData("author");
        } 

        // 4. SEARCH ENDPOINT
        elseif ($slug == "search") {
            if (!empty($_REQUEST['q'])) $pages->match("#".preg_quote($_REQUEST['q'])."#i");
            $this->applyGlobalFilters($pages);
            $data = $this->wrapPagination($pages, $base);
        } 

        // 5. INDIVIDUAL PAGE OR FOLDER/ALL
        else {
            $page = $this->yellow->content->find("/" . $slug);
            if ($page && $page->get("status") != "hidden" && !str_ends_with($page->get("layout"), "-start")) {
                $data = $this->formatPageDataWithRelated($page, $base);
            } else {
                if ($slug != "" && $slug != "all") {
                    $pages->match("#/[\d\-\_\.]*".$slug."/#i", false);
                }
                $this->applyGlobalFilters($pages);
                $data = $this->wrapPagination($pages, $base);
            }
        }

        // 6. CACHE MANAGEMENT
        if (!$isRandom) {
            $json = json_encode($data, JSON_PRETTY_PRINT);
            file_put_contents($cacheFile, $json);
            header("X-Cache: MISS");
        } else {
            header("X-Cache: BYPASS");
        }

        return $this->renderJson($data);
    }

    private function applyGlobalFilters($pages) {
        if (!empty($_REQUEST['tag'])) $pages->filter("tag", $_REQUEST['tag']);
        if (!empty($_REQUEST['author'])) $pages->filter("author", $_REQUEST['author']);
        $pages->sort("published", false);
    }

    private function wrapPagination($pages, $base) {
        $limit = $_REQUEST['limit'] ?? $this->yellow->system->get("feedPaginationLimit");
        $pages->paginate($limit); 
        return [
            "pagination" => [
                "current" => $pages->getPaginationNumber(),
                "total" => $pages->getPaginationCount(),
                "next" => $pages->getPaginationNext(),
                "prev" => $pages->getPaginationPrevious(),
            ],
            "items" => $this->buildDataArray($pages, $base)
        ];
    }

    private function getTaxonomyData($key) {
        $items = [];
        foreach ($this->yellow->content->index(true) as $p) {
            $values = preg_split("/\s*,\s*/", $p->get($key));
            foreach ($values as $v) if (!empty($v)) $items[$v] = ($items[$v] ?? 0) + 1;
        }
        ksort($items);
        return $items;
    }

    private function getCleanPagesArray($pages) {
        $clean = [];
        foreach ($pages as $p) {
            if ($p->get("status") != "hidden" && !str_ends_with($p->get("layout"), "-start") && $p->get("layout") != "system") {
                $clean[] = $p;
            }
        }
        return $clean;
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
            "title" => $page->get("title"),
            "author" => $page->get("author"),
            "published" => $page->get("published"),
            "tag" => $page->get("tag"),
            "page_image" => $page->get("image"),
            "content" => $page->getContentHtml(),
            "url_full" => $base . $page->getLocation(),
            "url_path" => $page->getLocation()
        ];
    }

    private function formatPageDataWithRelated($page, $base) {
        $data = $this->formatPageData($page, $base);
        $data["related"] = [];
        $tags = preg_split("/\s*,\s*/", $page->get("tag"));
        if (!empty($tags[0])) {
            $related = $this->yellow->content->index(true)->filter("tag", $tags[0])->filter("location", $page->getLocation(), false)->limit(3);
            foreach ($related as $r) $data["related"][] = ["title" => $r->get("title"), "url" => $base . $r->getLocation()];
        }
        return $data;
    }

    private function renderJson($data) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}