<?php
namespace App\Services;

use App\Models\PaaItem;
use Illuminate\Support\Facades\Cache;

class PaaWordCloudBuilder
{
    public function __construct(private EmbeddingService $embedder)
    {
    }

    /**
     * Sinh Word Cloud cho 1 run cụ thể
     * @param int $runId
     * @param int $topK
     * @return array [{text, value}]
     */
    public function forRun(int $runId, int $topK = 20): array
    {
        $questions = PaaItem::where('run_id', $runId)
            ->pluck('question')
            ->filter()
            ->values()
            ->all();

        if (empty($questions))
            return [];

        $text = implode(". ", $questions);
        
        // Gọi API Python KeyBERT
        $phrases = app(\App\Services\KeyphraseService::class)->extract($text, $topK);

        return $phrases; // [{text, value}]
    }



    /** Centroid + normalize */
    // private function centroid(array $emb): array
    // {
    //     if (empty($emb))
    //         return [];
    //     $dim = count($emb[0]);
    //     $sum = array_fill(0, $dim, 0.0);
    //     foreach ($emb as $v) {
    //         for ($i = 0; $i < $dim; $i++) {
    //             $sum[$i] += $v[$i];
    //         }
    //     }
    //     $n = count($emb);
    //     for ($i = 0; $i < $dim; $i++) {
    //         $sum[$i] /= $n;
    //     }
    //     $norm = sqrt(array_reduce($sum, fn($c, $v) => $c + $v * $v, 0));
    //     if ($norm > 0) {
    //         $sum = array_map(fn($v) => $v / $norm, $sum);
    //     }
    //     return $sum;
    // }

    /**
     * Maximal Marginal Relevance
     * @param float[][] $C candidate embeddings
     * @param float[]   $rep representativeness
     * @return int[] selected indices
     */
    // private function mmr(array $C, array $rep, int $k, float $lambda = 0.6): array
    // {
    //     $N = count($C);
    //     if ($N == 0)
    //         return [];

    //     $sel = [];
    //     $picked = array_fill(0, $N, false);

    //     // pick first by max rep
    //     $maxIdx = array_keys($rep, max($rep))[0];
    //     $sel[] = $maxIdx;
    //     $picked[$maxIdx] = true;

    //     while (count($sel) < min($k, $N)) {
    //         $bestI = -1;
    //         $bestScore = -1e9;
    //         foreach (range(0, $N - 1) as $i) {
    //             if ($picked[$i])
    //                 continue;
    //             $maxSim = -1.0;
    //             foreach ($sel as $j) {
    //                 $sim = EmbeddingService::cosine($C[$i], $C[$j]);
    //                 if ($sim > $maxSim)
    //                     $maxSim = $sim;
    //             }
    //             $mmr = $lambda * $rep[$i] - (1 - $lambda) * $maxSim;
    //             if ($mmr > $bestScore) {
    //                 $bestScore = $mmr;
    //                 $bestI = $i;
    //             }
    //         }
    //         if ($bestI < 0)
    //             break;
    //         $sel[] = $bestI;
    //         $picked[$bestI] = true;
    //     }
    //     return $sel;
    // }
}
