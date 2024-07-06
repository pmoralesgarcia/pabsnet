<?php
// Note extension, https://github.com/annaesvensson/yellow-note

class YellowNote {
    const VERSION = "0.9.4";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("noteStartLocation", "auto");
        $this->yellow->system->setDefault("noteNewLocation", "@title");
        $this->yellow->system->setDefault("noteFilePrefix", "1");
        $this->yellow->system->setDefault("noteShortcutEntries", "0");
        $this->yellow->system->setDefault("notePaginationLimit", "5");
    }
    
    // Handle page meta data
    public function onParseMetaData($page) {
        if ($page->get("layout")=="note") {
            $page->set("editNewLocation", $this->yellow->system->get("noteNewLocation"));
            if ($this->yellow->system->get("noteFilePrefix")) $page->set("editNewPrefix", $page->get("published"));
        }
    }
    
    // Handle page content element
    public function onParseContentElement($page, $name, $text, $attributes, $type) {
        $output = null;
        if (substru($name, 0, 4)=="note" && ($type=="block" || $type=="inline")) {
            switch($name) {
                case "noteauthors": $output = $this->getShortcutNoteauthors($page, $name, $text); break;
                case "notetags":    $output = $this->getShortcutNotetags($page, $name, $text); break;
                case "noteyears":   $output = $this->getShortcutNoteyears($page, $name, $text); break;
                case "notemonths":  $output = $this->getShortcutNotemonths($page, $name, $text); break;
                case "notepages":   $output = $this->getShortcutNotepages($page, $name, $text); break;
            }
        }
        return $output;
    }
        
    // Return noteauthors shortcut
    public function getShortcutNoteauthors($page, $name, $text) {
        $output = null;
        list($startLocation, $shortcutEntries) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("noteStartLocation");
        if (is_string_empty($shortcutEntries)) $shortcutEntries = $this->yellow->system->get("noteShortcutEntries");
        $noteStart = $this->getNoteStart($page, $startLocation);
        if (!is_null($noteStart)) {
            $pages = $this->getNotePages($noteStart);
            $page->setLastModified($pages->getModified());
            $authors = $pages->group("author", false, "count");
            if ($shortcutEntries!=0) $authors = array_slice($authors, 0, $shortcutEntries, true);
            uksort($authors, "strnatcasecmp");
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($authors as $author=>$collection) {
                $output .= "<li><a href=\"".$noteStart->getLocation(true).$this->yellow->lookup->normaliseArguments("author:$author")."\">";
                $output .= htmlspecialchars($author)."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Noteauthors '$startLocation' does not exist!");
        }
        return $output;
    }
    
    // Return notetags shortcut
    public function getShortcutNotetags($page, $name, $text) {
        $output = null;
        list($startLocation, $shortcutEntries) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("noteStartLocation");
        if (is_string_empty($shortcutEntries)) $shortcutEntries = $this->yellow->system->get("noteShortcutEntries");
        $noteStart = $this->getNoteStart($page, $startLocation);
        if (!is_null($noteStart)) {
            $pages = $this->getNotePages($noteStart);
            $page->setLastModified($pages->getModified());
            $tags = $pages->group("tag", false, "count");
            if ($shortcutEntries!=0) $tags = array_slice($tags, 0, $shortcutEntries, true);
            uksort($tags, "strnatcasecmp");
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($tags as $tag=>$collection) {
                $output .= "<li><a href=\"".$noteStart->getLocation(true).$this->yellow->lookup->normaliseArguments("tag:$tag")."\">";
                $output .= htmlspecialchars($tag)."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Notetags '$startLocation' does not exist!");
        }
        return $output;
    }

    // Return noteyears shortcut
    public function getShortcutNoteyears($page, $name, $text) {
        $output = null;
        list($startLocation, $shortcutEntries) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("noteStartLocation");
        if (is_string_empty($shortcutEntries)) $shortcutEntries = $this->yellow->system->get("noteShortcutEntries");
        $noteStart = $this->getNoteStart($page, $startLocation);
        if (!is_null($noteStart)) {
            $pages = $this->getNotePages($noteStart);
            $page->setLastModified($pages->getModified());
            $years = $pages->group("published", false, "Y");
            if ($shortcutEntries!=0) $years = array_slice($years, 0, $shortcutEntries, true);
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($years as $year=>$collection) {
                $output .= "<li><a href=\"".$noteStart->getLocation(true).$this->yellow->lookup->normaliseArguments("published:$year")."\">";
                $output .= htmlspecialchars($this->yellow->language->getDateStandard($year))."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Noteyears '$startLocation' does not exist!");
        }
        return $output;
    }
    
    // Return notemonths shortcut
    public function getShortcutNotemonths($page, $name, $text) {
        $output = null;
        list($startLocation, $shortcutEntries) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("noteStartLocation");
        if (is_string_empty($shortcutEntries)) $shortcutEntries = $this->yellow->system->get("noteShortcutEntries");
        $noteStart = $this->getNoteStart($page, $startLocation);
        if (!is_null($noteStart)) {
            $pages = $this->getNotePages($noteStart);
            $page->setLastModified($pages->getModified());
            $months = $pages->group("published", false, "Y-m");
            if ($shortcutEntries!=0) $months = array_slice($months, 0, $shortcutEntries, true);
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($months as $month=>$collection) {
                $output .= "<li><a href=\"".$noteStart->getLocation(true).$this->yellow->lookup->normaliseArguments("published:$month")."\">";
                $output .= htmlspecialchars($this->yellow->language->getDateStandard($month))."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Notemonths '$startLocation' does not exist!");
        }
        return $output;
    }
    
    // Return notepages shortcut
    public function getShortcutNotepages($page, $name, $text) {
        $output = null;
        list($startLocation, $shortcutEntries, $filterTag) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("noteStartLocation");
        if (is_string_empty($shortcutEntries)) $shortcutEntries = $this->yellow->system->get("noteShortcutEntries");
        $noteStart = $this->getNoteStart($page, $startLocation);
        if (!is_null($noteStart)) {
            $pages = $this->getNotePages($noteStart)->remove($page);
            $page->setLastModified($pages->getModified());
            if (!is_string_empty($filterTag)) $pages->filter("tag", $filterTag);
            $pages->sort("published", false);
            if ($shortcutEntries!=0) $pages->limit($shortcutEntries);
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($pages as $pageNote) {
                $output .= "<li><a".($pageNote->isExisting("tag") ? " class=\"".$this->getClass($pageNote)."\"" : "");
                $output .=" href=\"".$pageNote->getLocation(true)."\">".$pageNote->getHtml("title")."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Notepages '$startLocation' does not exist!");
        }
        return $output;
    }
    
    // Handle page layout
    public function onParsePageLayout($page, $name) {
        if ($name=="note-start") {
            $pages = $this->getNotePages($page);
            $pagesFilter = array();
            if ($page->isRequest("tag")) {
                $pages->filter("tag", $page->getRequest("tag"));
                array_push($pagesFilter, $pages->getFilter());
            }
            if ($page->isRequest("author")) {
                $pages->filter("author", $page->getRequest("author"));
                array_push($pagesFilter, $pages->getFilter());
            }
            if ($page->isRequest("published")) {
                $pages->filter("published", $page->getRequest("published"), false);
                array_push($pagesFilter, $this->yellow->language->getDateStandard($pages->getFilter()));
            }
            $pages->sort("published", false);
            if (!is_array_empty($pagesFilter)) {
                $text = implode(" ", $pagesFilter);
                $page->set("titleHeader", $text." - ".$page->get("sitename"));
                $page->set("titleContent", $page->get("title").": ".$text);
                $page->set("title", $page->get("title").": ".$text);
                $page->set("noteWithFilter", true);
            }
            $page->setPages("note", $pages);
            $page->setLastModified($pages->getModified());
            $page->setHeader("Cache-Control", "max-age=60");
        }
        if ($name=="note") {
            $noteStartLocation = $this->yellow->system->get("noteStartLocation");
            if ($noteStartLocation=="auto") {
                $noteStart = $page->getParent();
            } else {
                $noteStart = $this->yellow->content->find($noteStartLocation);
            }
            $page->setPage("noteStart", $noteStart);
        }
    }
    
    // Return note start page, null if not found
    public function getNoteStart($page, $noteStartLocation) {
        if ($noteStartLocation=="auto") {
            $noteStart = null;
            foreach ($this->yellow->content->top(true, false) as $pageTop) {
                if ($pageTop->get("layout")=="note-start") {
                    $noteStart = $pageTop;
                    break;
                }
            }
            if ($page->get("layout")=="note-start") $noteStart = $page;
        } else {
            $noteStart = $this->yellow->content->find($noteStartLocation);
        }
        return $noteStart;
    }

    // Return note pages for page
    public function getNotePages($page) {
        if ($this->yellow->system->get("noteStartLocation")=="auto") {
            $pages = $page->getChildren();
        } else {
            $pages = $this->yellow->content->index();
        }
        $pages->filter("layout", "note");
        return $pages;
    }
    
    // Return class for page
    public function getClass($page) {
        $class = "";
        if ($page->isExisting("tag")) {
            foreach (preg_split("/\s*,\s*/", $page->get("tag")) as $tag) {
                $class .= " tag-".$this->yellow->lookup->normaliseClass($tag);
            }
        }
        return trim($class);
    }
}