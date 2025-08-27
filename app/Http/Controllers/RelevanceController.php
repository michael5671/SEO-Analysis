<?php

namespace App\Http\Controllers;

use App\Models\{Run, PaaItem, Product};
use App\Services\EmbeddingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelevanceController extends Controller
{
    // Tính relevance cho 1 run; lưu top-N sản phẩm cho mỗi PAA
    public function compute(Request $req, int $runId)
    {
        $topN = (int) ($req->input('top', 5));
        $run = Run::findOrFail($runId);

        $embed = app(EmbeddingService::class);

        $paas = PaaItem::where('run_id', $runId)->get();
        $products = Product::all();

        // Chuẩn bị embedding cho product (cache vào DB nếu chưa có)
        foreach ($products as $p) {
            $vec = $p->embedding ? json_decode($p->embedding, true) : null;
            if (!is_array($vec) || empty($vec)) {
                $source = trim($p->name . '. ' . ($p->description ?? ''));
                $vec = $embed->embed($source);
                $p->embedding = json_encode($vec);
                $p->save();
            }

            $p->vec = $vec;
        }

        // Tính cho từng PAA
        $alpha = (float) env('PAA_MIX_ALPHA', 0.7);
        foreach ($paas as $q) {

            $qVec = $q->embedding ? json_decode($q->embedding, true) : null;
            if (!is_array($qVec) || empty($qVec)) {
                $qVec = app(\App\Services\EmbeddingService::class)->embed("query: " . $q->question);
                $q->embedding = json_encode($qVec);
                $q->save();
            }

            $aVec = $q->answer_embedding ? json_decode($q->answer_embedding, true) : null;
            if ((!is_array($aVec) || empty($aVec)) && !empty($q->answer)) {
                $aVec = app(\App\Services\EmbeddingService::class)->embed("answer: " . $q->answer);
                $q->answer_embedding = json_encode($aVec);
                $q->save();
            }
            $qFinal = \App\Services\EmbeddingService::mix((array) $qVec, (array) $aVec, $alpha);
            if (empty($qFinal))
                continue;

            // tính cosine với tất cả product
            $scores = [];
            foreach ($products as $p) {
                $scores[] = [
                    'product_id' => $p->id,
                    'score' => EmbeddingService::cosine($qFinal, $p->vec),
                ];
            }

            // chọn top-N
            usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
            $top = array_slice($scores, 0, $topN);

            // lưu
            foreach ($top as $row) {
                DB::table('relevance_paa_products')->updateOrInsert(
                    ['run_id' => $runId, 'paa_item_id' => $q->id, 'product_id' => $row['product_id']],
                    ['score' => $row['score'], 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        return redirect()->route('relevance.show', ['runId' => $runId]);
    }

    // Xem heatmap đơn giản
    public function show(int $runId)
    {
        $run = Run::findOrFail($runId);

        $rows = DB::table('relevance_paa_products as r')
            ->join('paa_items as q', 'q.id', '=', 'r.paa_item_id')
            ->join('products as p', 'p.id', '=', 'r.product_id')
            ->where('r.run_id', $runId)
            ->select('q.id as qid', 'q.question', 'p.id as pid', 'p.name as product', 'r.score')
            ->orderBy('qid')->orderByDesc('score')
            ->get();

        // Pivot: questions x products
        $questions = $rows->pluck('question', 'qid')->unique();
        $products = $rows->pluck('product', 'pid')->unique();

        $matrix = [];
        foreach ($questions as $qid => $qText) {
            $matrix[$qid] = array_fill_keys(array_keys($products->toArray()), 0);
        }
        foreach ($rows as $r) {
            $matrix[$r->qid][$r->pid] = round($r->score, 3);
        }

        return view('relevance', [
            'run' => $run,
            'questions' => $questions,
            'products' => $products,
            'matrix' => $matrix
        ]);
    }
}
