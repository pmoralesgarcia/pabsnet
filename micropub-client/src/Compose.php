<?php

namespace MicropubClient;

/** Turns the form into an h-entry, and an h-entry into what goes on the wire. */
class Compose
{
    public const RSVP_CHOICES = ['yes' => 'Yes', 'no' => 'No', 'maybe' => 'Maybe', 'interested' => 'Interested'];

    /** Each type's fields, in order. True marks a required field. */
    public const TYPES = [
        'note' => ['label' => 'Note', 'fields' => ['content' => true, 'photo' => false, 'alt' => false]],
        'article' => ['label' => 'Article', 'fields' => ['name' => true, 'content' => true, 'summary' => false, 'photo' => false, 'alt' => false]],
        'photo' => ['label' => 'Photo', 'fields' => ['photo' => false, 'alt' => false, 'content' => false]],
        'reply' => ['label' => 'Reply', 'fields' => ['url' => true, 'content' => true]],
        'like' => ['label' => 'Like', 'fields' => ['url' => true, 'content' => false]],
        'repost' => ['label' => 'Repost', 'fields' => ['url' => true, 'content' => false]],
        'bookmark' => ['label' => 'Bookmark', 'fields' => ['url' => true, 'name' => false, 'content' => false]],
        'rsvp' => ['label' => 'RSVP', 'fields' => ['url' => true, 'event' => false, 'rsvp' => true, 'content' => false]],
        'checkin' => ['label' => 'Check-in', 'fields' => ['venue' => true, 'url' => false, 'locality' => false, 'region' => false, 'latitude' => false, 'longitude' => false, 'content' => false, 'photo' => false, 'alt' => false]],
        'listen' => ['label' => 'Listen', 'fields' => ['song' => true, 'artist' => false, 'url' => false, 'content' => false]],
    ];

    private const URL_PROPERTY = ['reply' => 'in-reply-to', 'like' => 'like-of', 'repost' => 'repost-of', 'bookmark' => 'bookmark-of'];

    public static function takesPhoto(string $type): bool
    {
        return isset(self::TYPES[$type]['fields']['photo']);
    }

    /**
     * @param string[] $photos URLs of photos already stored.
     * @param string[] $allowedSyndication Targets the server offered.
     * @param bool $hasFiles Files are coming that have no URL yet.
     */
    public static function toEntry(string $type, array $post, array $photos = [], array $allowedSyndication = [], bool $hasFiles = false): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new ClientError('Unknown post type.');
        }
        $get = fn(string $key) => is_string($post[$key] ?? null) ? trim(str_replace("\r\n", "\n", $post[$key])) : '';
        $props = [];
        $content = $get('content');
        if ($content !== '') {
            $props['content'] = [$content];
        }
        $name = $get('name');
        $url = self::optionalUrl($get('url'));
        $photoUrl = self::takesPhoto($type) ? self::optionalUrl($get('photo_url')) : null;
        $allPhotos = $photoUrl === null ? $photos : [...$photos, $photoUrl];
        $hasPhoto = $allPhotos !== [] || $hasFiles;

        switch ($type) {
            case 'note':
                if ($content === '' && !$hasPhoto) {
                    throw new ClientError('Write something, or add a photo.');
                }
                break;
            case 'article':
                if ($name === '' || $content === '') {
                    throw new ClientError('An article needs a title and text.');
                }
                $props['name'] = [$name];
                if ($get('summary') !== '') {
                    $props['summary'] = [$get('summary')];
                }
                break;
            case 'photo':
                if (!$hasPhoto) {
                    throw new ClientError('Choose a photo to upload, or add its link.');
                }
                break;
            case 'reply':
            case 'like':
            case 'repost':
            case 'bookmark':
                if ($url === null) {
                    throw new ClientError('Add the link this post is about.');
                }
                if ($type === 'reply' && $content === '') {
                    throw new ClientError('Write your reply.');
                }
                $props[self::URL_PROPERTY[$type]] = [$url];
                if ($type === 'bookmark' && $name !== '') {
                    $props['name'] = [$name];
                }
                break;
            case 'rsvp':
                if (!isset(self::RSVP_CHOICES[$get('rsvp')])) {
                    throw new ClientError('Choose your RSVP.');
                }
                if ($url === null) {
                    throw new ClientError('Add the link to the event.');
                }
                $event = $get('event');
                $props['in-reply-to'] = [$event === '' ? $url : ['type' => ['h-cite'], 'properties' => ['name' => [$event], 'url' => [$url]]]];
                $props['rsvp'] = [$get('rsvp')];
                break;
            case 'checkin':
                if ($get('venue') === '') {
                    throw new ClientError('Add the name of the place.');
                }
                $latitude = $get('latitude');
                $longitude = $get('longitude');
                if (($latitude === '') !== ($longitude === '')) {
                    throw new ClientError('Add both latitude and longitude, or neither.');
                }
                if ($latitude !== '' && (!is_numeric($latitude) || !is_numeric($longitude) || abs((float) $latitude) > 90 || abs((float) $longitude) > 180)) {
                    throw new ClientError('Latitude and longitude must be valid coordinates.');
                }
                $venue = ['name' => [$get('venue')]];
                foreach (['url' => $url ?? '', 'locality' => $get('locality'), 'region' => $get('region'), 'latitude' => $latitude, 'longitude' => $longitude] as $key => $value) {
                    if ($value !== '') {
                        $venue[$key] = [$value];
                    }
                }
                $props['checkin'] = [['type' => ['h-card'], 'properties' => $venue]];
                break;
            case 'listen':
                if ($get('song') === '') {
                    throw new ClientError('Add the name of what you listened to.');
                }
                $cite = ['name' => [$get('song')]];
                if ($get('artist') !== '') {
                    $cite['author'] = [$get('artist')];
                }
                if ($url !== null) {
                    $cite['url'] = [$url];
                }
                $props['listen-of'] = [['type' => ['h-cite'], 'properties' => $cite]];
                break;
        }

        if ($allPhotos !== []) {
            $alt = $get('alt');
            // One description can only describe one photo.
            $props['photo'] = count($allPhotos) === 1 && $alt !== '' ? [['value' => $allPhotos[0], 'alt' => $alt]] : $allPhotos;
        }
        $tags = array_values(array_filter(array_map('trim', explode(',', $get('category'))), 'strlen'));
        if ($tags !== []) {
            $props['category'] = $tags;
        }
        if ($get('status') === 'draft') {
            $props['post-status'] = ['draft'];
        }
        if (in_array($get('visibility'), ['unlisted', 'private'], true)) {
            $props['visibility'] = [$get('visibility')];
        }
        if ($get('published') !== '') {
            $time = strtotime($get('published'));
            if ($time === false) {
                throw new ClientError('That publish date is not valid.');
            }
            $props['published'] = [date('c', $time)];
        }
        if ($get('slug') !== '') {
            $props['mp-slug'] = [$get('slug')];
        }
        $chosen = array_values(array_intersect(array_filter((array) ($post['syndicate'] ?? []), 'is_string'), $allowedSyndication));
        if ($chosen !== []) {
            $props['mp-syndicate-to'] = $chosen;
        }
        return ['type' => ['h-entry'], 'properties' => $props];
    }

    public static function isFlat(array $props): bool
    {
        foreach ($props as $values) {
            foreach ($values as $value) {
                if (!is_scalar($value)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @return array<int,array{0:string,1:string}> Field names with [] for many values, as the spec's form encoding has it. */
    public static function formPairs(array $props): array
    {
        $pairs = [['h', 'entry']];
        foreach ($props as $key => $values) {
            foreach ($values as $value) {
                $pairs[] = [count($values) > 1 || $key === 'category' ? $key . '[]' : $key, (string) $value];
            }
        }
        return $pairs;
    }

    public static function formBody(array $props): string
    {
        return implode('&', array_map(fn($p) => rawurlencode($p[0]) . '=' . rawurlencode($p[1]), self::formPairs($props)));
    }

    private static function optionalUrl(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (!preg_match('#^https?://[^\s<>"\']+$#i', $value)) {
            throw new ClientError('Links must start with http:// or https://');
        }
        return $value;
    }
}
