<?php
namespace App\Services;

use DOMDocument, DOMXPath;

class HtmlParserService
{
    public function extractFaqSchema(string $html): array {
        $has = false; $items = [];
        if (preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m)) {
            foreach ($m[1] as $json) {
                $obj = json_decode($json, true);
                $list = is_array($obj) && array_keys($obj) !== range(0, count($obj)-1) ? [$obj] : (is_array($obj) ? $obj : []);
                foreach ($list as $o) {
                    if (($o['@type'] ?? '') === 'FAQPage' && !empty($o['mainEntity'])) {
                        $has = true;
                        foreach ($o['mainEntity'] as $qa) {
                            $q = $qa['name'] ?? '';
                            $a = $qa['acceptedAnswer']['text'] ?? '';
                            if ($q && $a) $items[] = ['q'=>$q,'a'=>$a];
                        }
                    }
                }
            }
        }
        return [$has, $items];
    }

    public function extractHeadings(string $html): array {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xp = new DOMXPath($dom);
        $nodes = $xp->query('//h2|//h3');
        $out = [];
        foreach ($nodes as $n) $out[] = ['level'=>strtolower($n->nodeName), 'text'=>trim($n->textContent)];
        return $out;
    }
}
