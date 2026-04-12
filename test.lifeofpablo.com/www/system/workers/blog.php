<?php
// Blog extension, https://github.com/annaesvensson/yellow-blog

class YellowBlog {
    const VERSION = "0.9.5";
    public $yellow;

    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("blogStartLocation", "auto");
        $this->yellow->system->setDefault("blogNewLocation", "@title");
        $this->yellow->system->setDefault("blogFilePrefix", "1");
        $this->yellow->system->setDefault("blogShortcutEntries", "0");
        $this->yellow->system->setDefault("blogPaginationLimit", "5");
    }

    public function onParseMetaData($page) {
        if ($page->get("layout") == "blog") {
            $page->set("editNewLocation", $this->yellow->system->get("blogNewLocation"));
            if ($this->yellow->system->get("blogFilePrefix")) {
                $page->set("editNewPrefix", $page->get("published"));
            }
        }
    }

    public function onParseContentElement($page, $name, $text, $attributes, $type) {
        $output = null;
        if (substru($name, 0, 4) == "blog" && ($type == "block" || $type == "inline")) {
            switch($name) {
                case "blogauthors": $output = $this->getShortcutBlogauthors($page, $name, $text); break;
                case "blogtags":    $output = $this->getShortcutBlogtags($page, $name, $text); break;
                case "blogyears":   $output = $this->getShortcutBlogyears($page, $name, $text); break;
                case "blogmonths":  $output = $this->getShortcutBlogmonths($page, $name, $text); break;
                case "blogpages":   $output = $this->getShortcutBlogpages($page, $name, $text); break;
            }
        }
        return $output;
    }

    public function getShortcutBlogauthors($page, $name, $text) {
        $output = null;
        list($startLocation) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        $blogStart = $this->getBlogStart($page, $startLocation);
        if (!is_null($blogStart)) {
            $pages = $this->getBlogPages($blogStart);
            $authors = $pages->group("author", false, "count");
            uksort($authors, "strnatcasecmp");
            $output = "<div class=\"".htmlspecialchars($name)."\"><ul>\n";
            foreach ($authors as $author => $collection) {
                $location = $blogStart->getLocation(true) . "author/" . $this->yellow->lookup->normaliseClass($author);
                $output .= "<li><a href=\"$location\">".htmlspecialchars($author)."</a></li>\n";
            }
            $output .= "</ul></div>\n";
        }
        return $output;
    }

    public function getShortcutBlogtags($page, $name, $text) {
        $output = null;
        list($startLocation) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        $blogStart = $this->getBlogStart($page, $startLocation);
        if (!is_null($blogStart)) {
            $pages = $this->getBlogPages($blogStart);
            $tags = $pages->group("tag", false, "count");
            uksort($tags, "strnatcasecmp");
            $output = "<div class=\"".htmlspecialchars($name)."\"><ul>\n";
            foreach ($tags as $tag => $collection) {
                $location = $blogStart->getLocation(true) . "tag/" . $this->yellow->lookup->normaliseClass($tag);
                $output .= "<li><a href=\"$location\">".htmlspecialchars($tag)."</a></li>\n";
            }
            $output .= "</ul></div>\n";
        }
        return $output;
    }

    public function onParsePageLayout($page, $name) {
        if (in_array($name, ["blog-start", "blog-archive"])) {
            $pages = $this->getBlogPages($page);
            $pagesFilter = array();
            
            $location = $this->yellow->page->getLocation(false);
            $segments = explode("/", trim($location, "/"));
            $key = $segments[count($segments)-2] ?? "";
            $value = $segments[count($segments)-1] ?? "";

            if ($key == "tag") {
                $pages->filter("tag", $value);
                array_push($pagesFilter, $pages->getFilter());
            } elseif ($key == "author") {
                $pages->filter("author", $value);
                array_push($pagesFilter, $pages->getFilter());
            }

            $pages->sort("published", false);
            if (!is_array_empty($pagesFilter)) {
                $text = implode(" ", $pagesFilter);
                $page->set("titleContent", $page->get("title").": ".$text);
                $page->set("blogWithFilter", true);
            }
            $page->setPages("blog", $pages);
        }
        if ($name == "blog") {
            $blogStartLocation = $this->yellow->system->get("blogStartLocation");
            $blogStart = ($blogStartLocation == "auto") ? $page->getParent() : $this->yellow->content->find($blogStartLocation);
            $page->setPage("blogStart", $blogStart);
        }
    }

    public function getBlogStart($page, $blogStartLocation) {
        if ($blogStartLocation == "auto") {
            foreach ($this->yellow->content->top(true, false) as $pageTop) {
                if ($pageTop->get("layout") == "blog-start") return $pageTop;
            }
            return ($page->get("layout") == "blog-start") ? $page : null;
        }
        return $this->yellow->content->find($blogStartLocation);
    }

    public function getBlogPages($page) {
        $pages = ($this->yellow->system->get("blogStartLocation") == "auto") ? $page->getChildren() : $this->yellow->content->index();
        $pages->filter("layout", "blog");
        return $pages;
    }
}