<?php
namespace App\Http\Controllers;

use App\Models\{Run, PaaItem, Heading, FaqItem, MapPaaHeading, MapHeadingFaq, SerpSnapshot};
use App\Services\{SerpApiService, HtmlParserService, TextHelperService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AnalyzeController extends Controller
{
    const TOP_N = 15;
    const SIM_TH_PAA_HEADING = 0.55;
    const SIM_TH_HEADING_FAQ = 0.55;

    public function form()
    {
        return view('form');
    }

    public function analyze(Request $req, SerpApiService $serp, HtmlParserService $parser)
    {
        $q = trim($req->input('q', ''));
        abort_if($q === ' ', 422, 'Thiếu input');
        $mode = TextHelperService::detectMode($q);

        $run = Run::create(['query' => $q, 'mode' => $mode]);

        // 1) gọi SERP
        $searchQuery = $q;
        $serpData = $serp->search($q, self::TOP_N, includePAA: true);

        // foreach ($serpData['results'] as $r) {
        //     SerpSnapshot::updateOrCreate(
        //         [
        //             'run_id' => $run->id,
        //             'url_hash' => \App\Services\TextHelperService::urlHash($r['link']),
        //             'position' => $r['position'] ?? null,
        //         ],
        //         [
        //             'query_used' => $searchQuery,
        //             'url' => $r['link'],
        //             'has_faq_rich' => (bool) ($r['faq_rich'] ?? false),
        //         ]
        //     );
        // }

        // 2) Lưu PAA
        foreach ($serpData['paa'] as $item) {
            $question = $item['question'];
            $answer = $item['answer'] ?? '';
            $freq = (int) ($item['freq'] ?? 1);

            PaaItem::updateOrCreate(
                ['run_id' => $run->id, 'question_hash' => TextHelperService::hash($question)],
                [
                    'question' => $question,
                    'answer' => $answer ?: null,
                    'answer_hash' => $answer ? TextHelperService::hash($answer) : null,
                    'intent' => TextHelperService::classifyIntent($question),
                    'freq' => DB::raw("GREATEST(freq,0)+$freq"),
                ]
            );
        }
        // 3) Crawl từng URL → headings + FAQ
        foreach ($serpData['results'] as $r) {
            $url = $r['link'];

            try {
                $resp = Http::retry(3, 300)
                    ->timeout(25)
                    ->withHeaders([
                        // UA “thật” hơn để tránh bot-block
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
                    ])
                    ->withOptions([
                        'allow_redirects' => true,
                        'verify' => false,          // MVP: tạm tắt SSL verify cho đỡ kén
                        'http_errors' => false,     // không throw exception với 4xx/5xx
                    ])
                    ->get($url);
            } catch (\Throwable $e) {
                \Log::warning('Fetch exception', ['url' => $url, 'err' => $e->getMessage()]);
                continue;
            }

            if (!$resp->ok()) {
                \Log::warning('Fetch non-200', ['url' => $url, 'status' => $resp->status()]);
                continue;
            }

            // Chỉ xử lý HTML
            $ct = strtolower($resp->header('Content-Type') ?? '');
            if (!str_contains($ct, 'text/html')) {
                \Log::info('Skip non-HTML', ['url' => $url, 'content_type' => $ct]);
                continue;
            }

            $html = $resp->body();

            // ===== Headings =====
            $headings = $parser->extractHeadings($html);
            foreach ($headings as $h) {
                $text = trim($h['text']);
                if ($text === '')
                    continue;

                \App\Models\Heading::firstOrCreate([
                    'run_id' => $run->id,
                    'url_hash' => \App\Services\TextHelperService::urlHash($url), // hoặc TextNlpService nếu bạn giữ tên cũ
                    'level' => $h['level'],
                    'text_hash' => \App\Services\TextHelperService::hash($text),
                ], [
                    'url' => $url,
                    'text' => $text,
                    'is_focus' => $this->isFocus($text, $q),
                ]);
            }

            // ===== FAQ JSON-LD =====
            [$hasFaq, $faqItems] = $parser->extractFaqSchema($html);
            if ($hasFaq) {
                foreach ($faqItems as $qa) {
                    $qh = \App\Services\TextHelperService::hash($qa['q']);
                    $ah = $qa['a'] ? \App\Services\TextHelperService::hash(strip_tags($qa['a'])) : null;

                    \App\Models\FaqItem::firstOrCreate([
                        'run_id' => $run->id,
                        'url_hash' => \App\Services\TextHelperService::urlHash($url),
                        'question_hash' => $qh,
                    ], [
                        'url' => $url,
                        'question' => $qa['q'],
                        'answer' => $qa['a'],
                        'answer_hash' => $ah,
                    ]);
                }
            }
        }


        // 4) Mapping PAA ↔ Heading
        $paa = PaaItem::where('run_id', $run->id)->get();
        $hds = Heading::where('run_id', $run->id)->get();
        foreach ($paa as $p) {
            foreach ($hds as $h) {
                $sim = TextHelperService::jaccard($p->question, $h->text);
                if ($sim >= self::SIM_TH_PAA_HEADING) {
                    MapPaaHeading::firstOrCreate(
                        ['run_id' => $run->id, 'paa_item_id' => $p->id, 'heading_id' => $h->id],
                        ['similarity' => $sim]
                    );
                }
            }
        }

        // 5) Mapping Heading ↔ FAQ
        $faqs = FaqItem::where('run_id', $run->id)->get();
        foreach ($hds as $h) {
            foreach ($faqs as $f) {
                $sim = TextHelperService::jaccard($h->text, $f->question);
                if ($sim >= self::SIM_TH_HEADING_FAQ) {
                    MapHeadingFaq::firstOrCreate(
                        ['run_id' => $run->id, 'heading_id' => $h->id, 'faq_item_id' => $f->id],
                        ['similarity' => $sim]
                    );
                }
            }
        }

        return redirect()->route('runs.show', ['id' => $run->id]);
    }

    public function show($id)
    {
        $run = Run::findOrFail($id);

        $paaConv = DB::table('view_paa_to_heading_conversion')->where('run_id', $id)->first();
        $hdConv = DB::table('view_heading_to_faq_conversion')->where('run_id', $id)->first();

        // === Tỷ lệ Index của trang có FAQ (xấp xỉ) ===
        // Tổng URL có FAQ schema trong lần crawl này
        $faqUrls = DB::table('faq_items')->where('run_id', $id)->distinct()->pluck('url_hash');
        $totalFaqPages = $faqUrls->count();

        // Bao nhiêu URL đó xuất hiện trong SERP snapshot của run này
        $indexedFaqPages = 0;
        if ($totalFaqPages > 0) {
            $indexedFaqPages = DB::table('serp_snapshots')
                ->where('run_id', $id)
                ->whereIn('url_hash', $faqUrls)
                ->distinct()->count('url_hash');
        }
        $indexRate = $totalFaqPages ? round(100 * $indexedFaqPages / $totalFaqPages, 2) : 0.0;


        // Dữ liệu khác
        $intent = DB::table('paa_items')->select('intent', DB::raw('SUM(freq) as n'))
            ->where('run_id', $id)->groupBy('intent')->pluck('n', 'intent');

        $perUrl = DB::table('headings')->select('url', DB::raw('COUNT(*) as c'))
            ->where('run_id', $id)->groupBy('url')->orderByDesc('c')->limit(10)->get();

        $topRel = DB::table('relevance_paa_products as r')
            ->join('paa_items as q', 'q.id', '=', 'r.paa_item_id')
            ->join('products as p', 'p.id', '=', 'r.product_id')
            ->where('r.run_id', $id)
            ->orderByDesc('r.score')
            ->limit(10)
            ->get([
                'q.question as paa_question',
                'p.name as product_name',
                'r.score'
            ]);
        
            

        return view('dashboard', compact(
            'run',
            'paaConv',
            'hdConv',
            'intent',
            'perUrl',
            'indexRate',
            'totalFaqPages',
            'indexedFaqPages',
            'topRel'
        ));

    }

    private function isFocus(string $heading, string $query): bool
    {
        $tokens = array_filter(preg_split('/\W+/u', mb_strtolower($query)));
        $t = mb_strtolower($heading);
        foreach ($tokens as $w) {
            if (mb_strlen($w) >= 3 && str_contains($t, $w))
                return true;
        }
        return false;
    }
}
