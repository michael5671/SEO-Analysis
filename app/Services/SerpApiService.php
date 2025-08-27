<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiService
{
    public function search(string $query, int $topN = 10, bool $includePAA = true): array
    {
        $params = [
            'engine' => config('services.serpapi.engine', 'google'),
            'google_domain' => config('services.serpapi.domain', 'google.com'),
            'hl' => config('services.serpapi.hl', 'en'),
            'gl' => config('services.serpapi.gl', 'us'),
            'device' => 'desktop',
            'q' => $query,
            'num' => $topN,
            'no_cache' => true,
            'api_key' => env('SERPAPI_KEY'),
        ];

        $res = Http::timeout(25)->get('https://serpapi.com/search.json', $params);
        if (!$res->ok())
            return ['results' => [], 'paa' => []];
        $json = $res->json();

        $results = collect($json['organic_results'] ?? [])
            ->map(fn($it) => [
                'link' => $it['link'] ?? null,
                'title' => $it['title'] ?? null,
                'snippet' => $it['snippet'] ?? null,
                'position' => $it['position'] ?? null,
            ])->filter(fn($r) => !empty($r['link']))->values()->all();

        // ---- PAA: trả về danh sách item (question, answer, freq) ----
        $paa = [];
        if ($includePAA && !empty($json['related_questions'])) {
            foreach ($json['related_questions'] as $rq) {
                $q = trim($rq['question'] ?? '');
                $a = trim($rq['answer'] ?? $rq['snippet'] ?? '');
                if ($q === '')
                    continue;

                // gộp các item trùng câu hỏi (cộng freq, chọn answer dài hơn)
                if (!isset($paa[$q])) {
                    $paa[$q] = ['question' => $q, 'answer' => $a, 'freq' => 1];
                } else {
                    $paa[$q]['freq'] += 1;
                    if (mb_strlen($a) > mb_strlen($paa[$q]['answer'] ?? '')) {
                        $paa[$q]['answer'] = $a;
                    }
                }
            }
        }
        // về mảng tuần tự
        $paa = array_values($paa);

        Log::info('SerpAPI summary', ['q' => $query, 'organic' => count($results), 'paa' => count($paa)]);
        return ['results' => $results, 'paa' => $paa];
    }
}
