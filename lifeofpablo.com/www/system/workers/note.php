<?php
// Note extension for Datenstrom Yellow

class YellowNote {
    const VERSION = "1.0.0";
    public $yellow;

    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("notePaginationLimit", "20");
        $this->yellow->system->setDefault("noteStartLocation", "auto");
    }

    public function onParseMetaData($page) {
        if ($page->get("layout") == "note") {
            $page->set("editNewLocation", $this->yellow->system->get("blogNewLocation"));
            if ($this->yellow->system->get("blogFilePrefix")) {
                $page->set("editNewPrefix", $page->get("published"));
            }
        }
    }

    public function onParsePageLayout($page, $name) {
        if ($name == "note-start") {
            $pages = $this->getNotePages($page);
            
            // Tag and Author filtering logic
            if ($page->isRequest("tag")) {
                $pages->filter("tag", $page->getRequest("tag"));
            }
            if ($page->isRequest("author")) {
                $pages->filter("author", $page->getRequest("author"));
            }

            $pages->sort("published", false);
            $page->setPages("notes", $pages);
            $page->setLastModified($pages->getModified());
        }
        
        if ($name == "note") {
            $page->setPage("noteStart", $this->getNoteStart($page));
        }
    }

    public function getNoteStart($page) {
        foreach ($this->yellow->content->top(true, false) as $pageTop) {
            if ($pageTop->get("layout") == "note-start") return $pageTop;
        }
        return $page->getParent();
    }

    public function getNotePages($page) {
        $pages = $page->getChildren();
        $pages->filter("layout", "note");
        return $pages;
    }
}