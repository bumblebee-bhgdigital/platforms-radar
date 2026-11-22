<?php

namespace Platform\Radar\Services;

use Carbon\CarbonImmutable;

/**
 * Liest RSS 2.0, RSS 1.0 (RDF) und Atom in eine einheitliche Liste: title, url, excerpt, author, published_at, guid.
 * Reiner Parser ohne Netz und ohne Datenbank.
 */
class FeedParser
{
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_CONTENT = 'http://purl.org/rss/1.0/modules/content/';
    private const NS_ATOM = 'http://www.w3.org/2005/Atom';
    private const NS_RSS1 = 'http://purl.org/rss/1.0/';

    public function __construct(private int $excerptLength = 1000)
    {
    }

    /** @return list<array{title:string,url:?string,excerpt:?string,author:?string,published_at:?CarbonImmutable,guid:?string}> */
    public function parse(string $body): array
    {
        $prev = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string(ltrim($body), \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
        if (! $xml) {
            throw new \InvalidArgumentException('Kein gültiges XML.');
        }

        $root = strtolower($xml->getName());
        $items = match (true) {
            $root === 'rss' => $this->rss2($xml),
            $root === 'feed' => $this->atom($xml),
            $root === 'rdf' => $this->rss1($xml),
            default => throw new \InvalidArgumentException("Unbekanntes Feed-Format <{$xml->getName()}>."),
        };

        return array_values(array_filter($items, fn ($i) => $i['title'] !== '' || $i['url']));
    }

    private function rss2(\SimpleXMLElement $xml): array
    {
        $out = [];
        foreach ($xml->channel->item ?? [] as $item) {
            $dc = $item->children(self::NS_DC);
            $content = $item->children(self::NS_CONTENT);
            $out[] = $this->item(
                (string) $item->title,
                (string) $item->link ?: null,
                (string) $item->description ?: (string) $content->encoded,
                (string) $item->author ?: (string) $dc->creator,
                (string) $item->pubDate ?: (string) $dc->date,
                (string) $item->guid ?: null,
            );
        }

        return $out;
    }

    private function rss1(\SimpleXMLElement $xml): array
    {
        $out = [];
        foreach ($xml->children(self::NS_RSS1)->item ?? [] as $item) {
            $dc = $item->children(self::NS_DC);
            $out[] = $this->item((string) $item->title, (string) $item->link ?: null, (string) $item->description, (string) $dc->creator, (string) $dc->date, null);
        }

        return $out;
    }

    private function atom(\SimpleXMLElement $xml): array
    {
        $out = [];
        $entries = $xml->entry ?? $xml->children(self::NS_ATOM)->entry;
        foreach ($entries ?? [] as $entry) {
            $link = null;
            foreach ($entry->link as $l) {
                $rel = (string) $l['rel'];
                if ($rel === '' || $rel === 'alternate') {
                    $link = (string) $l['href'];
                    break;
                }
                $link ??= (string) $l['href'];
            }
            $out[] = $this->item(
                (string) $entry->title,
                $link ?: null,
                (string) $entry->summary ?: (string) $entry->content,
                (string) ($entry->author->name ?? ''),
                (string) $entry->published ?: (string) $entry->updated,
                (string) $entry->id ?: null,
            );
        }

        return $out;
    }

    private function item(string $title, ?string $url, string $text, string $author, string $date, ?string $guid): array
    {
        $excerpt = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return [
            'title' => mb_substr(trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 1000),
            'url' => $url ? mb_substr(trim($url), 0, 2048) : null,
            'excerpt' => $excerpt === '' ? null : mb_substr($excerpt, 0, $this->excerptLength),
            'author' => trim($author) === '' ? null : mb_substr(trim($author), 0, 255),
            'published_at' => $this->date($date),
            'guid' => $guid ? trim($guid) : null,
        ];
    }

    private function date(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Eindeutiger Schlüssel eines Eintrags innerhalb seiner Quelle. */
    public static function fingerprint(array $item): string
    {
        return sha1($item['guid'] ?? $item['url'] ?? ($item['title'] . '|' . ($item['published_at']?->toIso8601String() ?? '')));
    }
}
