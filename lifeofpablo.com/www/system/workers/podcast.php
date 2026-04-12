<?php
// Podcast extension for Datenstrom Yellow
// Optimized for Apple Podcasts and HTML5 Audio

class YellowPodcast {
    const VERSION = "0.9.0";
    public $yellow;

    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("podcastLocation", "/podcast/");
        $this->yellow->system->setDefault("podcastFileXml", "podcast.xml");
        $this->yellow->system->setDefault("podcastPaginationLimit", "30");
        $this->yellow->system->setDefault("podcastMimeType", "audio/mpeg");
        $this->yellow->system->setDefault("podcastImageUrl", ""); // Recommended: 1400x1400px
        $this->yellow->system->setDefault("podcastExplicit", "no");
        $this->yellow->system->setDefault("podcastCategory", "Technology");
        $this->yellow->system->setDefault("podcastSubcategory", "");
    }

    public function onParsePageLayout($page, $name) {
        if ($name == "podcast") {
            $pages = $this->yellow->content->index(false, false);
            $pages->filter("mediafile", "", false); // Only pages with media files
            
            // Sort by publication date
            foreach ($pages as $pagePodcast) {
                $score = $pagePodcast->get($pagePodcast->isExisting("published") ? "published" : "modified");
                $pagePodcast->set("podcastScore", $score);
            }
            $pages->sort("podcastScore", false);

            if ($this->isRequestXml($page)) {
                $this->renderRssFeed($page, $pages);
            } else {
                $this->yellow->page->setPages("podcast", $pages);
            }
        }
    }

    protected function renderRssFeed($page, $pages) {
        $this->yellow->page->setHeader("Content-Type", "application/rss+xml; charset=utf-8");
        $sitename = $this->yellow->page->getHtml("sitename");
        
        $output = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n";
        $output .= "<rss version=\"2.0\" xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\">\r\n";
        $output .= "<channel>\r\n";
        $output .= "<title>".htmlspecialchars($sitename)."</title>\r\n";
        $output .= "<link>".$this->yellow->page->getUrl()."</link>\r\n";
        $output .= "<language>".$this->yellow->page->getHtml("language")."</language>\r\n";
        $output .= "<itunes:author>".$this->yellow->page->getHtml("author")."</itunes:author>\r\n";
        $output .= "<itunes:summary>".$this->yellow->page->getHtml("description")."</itunes:summary>\r\n";
        
        if ($img = $this->yellow->system->get("podcastImageUrl")) {
            $output .= "<itunes:image href=\"".htmlspecialchars($img)."\" />\r\n";
        }

        $output .= "<itunes:explicit>".$this->yellow->system->get("podcastExplicit")."</itunes:explicit>\r\n";
        $output .= "<itunes:category text=\"".htmlspecialchars($this->yellow->system->get("podcastCategory"))."\" />\r\n";

        foreach ($pages as $pagePodcast) {
            $output .= "<item>\r\n";
            $output .= "<title>".$pagePodcast->getHtml("title")."</title>\r\n";
            $output .= "<itunes:author>".$pagePodcast->getHtml("author")."</itunes:author>\r\n";
            $output .= "<pubDate>".date(DATE_RSS, strtotime($pagePodcast->get("published")))."</pubDate>\r\n";
            $output .= "<enclosure url=\"".$pagePodcast->get("mediafile")."\" type=\"".$this->yellow->system->get("podcastMimeType")."\" length=\"0\" />\r\n";
            $output .= "<guid>".$pagePodcast->getUrl()."</guid>\r\n";
            $output .= "<itunes:duration>".$pagePodcast->getHtml("duration")."</itunes:duration>\r\n";
            $output .= "</item>\r\n";
        }

        $output .= "</channel>\r\n</rss>";
        $this->yellow->page->setOutput($output);
    }

    public function isRequestXml($page) {
        return $page->getRequest("page") == $this->yellow->system->get("podcastFileXml");
    }
}