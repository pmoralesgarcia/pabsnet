<?php

namespace Selfauth;

class Rss
{
    /**
     * @param array{title:string, link:string, description:string} $channel
     * @param array<int, array{title:string, link:string, description:string, guid:string, pubDate?:?string}> $items
     */
    public static function build(array $channel, array $items): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"></rss>');
        $ch = $xml->addChild('channel');
        $ch->addChild('title', htmlspecialchars($channel['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $ch->addChild('link', htmlspecialchars($channel['link'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $ch->addChild('description', htmlspecialchars($channel['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $ch->addChild('lastBuildDate', date(DATE_RSS));

        foreach ($items as $item) {
            $node = $ch->addChild('item');
            $node->addChild('title', htmlspecialchars($item['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            $node->addChild('link', htmlspecialchars($item['link'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            $node->addChild('description', htmlspecialchars($item['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            $node->addChild('guid', htmlspecialchars($item['guid'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            if (!empty($item['pubDate'])) {
                $ts = strtotime($item['pubDate']);
                if ($ts !== false) {
                    $node->addChild('pubDate', date(DATE_RSS, $ts));
                }
            }
        }

        return $xml->asXML();
    }
}
