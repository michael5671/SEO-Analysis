<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    protected $apiKey;
    protected $model;
    protected $timeout;

    public function __construct()
    {
        $this->apiKey = env('HF_API_KEY');
        $this->model = env('HF_EMBED_MODEL_1', 'sentence-transformers/all-MiniLM-L6-v2');
        $this->timeout = (int) env('HF_TIMEOUT', 40);
    }
    public function embed(string $text): array
    {
        $text = trim($text);
        if ($text === '')
            return [];

        $endpoint = "https://router.huggingface.co/hf-inference/models/{$this->model}/pipeline/feature-extraction";

        $resp = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                // HF khuyến nghị có options.wait_for_model để lần đầu tải model
                'inputs' => $text,
                'options' => ['wait_for_model' => true],
                // Một số backend hỗ trợ parameters.pooling/normalize; nếu không hỗ trợ thì ta tự pooling ở dưới.
                'parameters' => ['pooling' => 'mean', 'normalize' => true],
            ]);

        if (!$resp->ok()) {
            Log::warning('HF embed HTTP error', [
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 500),
            ]);
            return [];
        }

        $json = $resp->json();

        // Router có thể trả:
        // 1) [f1, f2, ...]  -> đã pooled
        // 2) [[t1f1, ...], [t2f1, ...], ...] -> token-level -> cần mean-pooling
        if (is_array($json) && !empty($json)) {
            if (is_array($json[0])) {
                // token-level -> mean pooling
                $dim = count($json[0]);
                $sum = array_fill(0, $dim, 0.0);
                $count = 0;
                foreach ($json as $tokVec) {
                    if (!is_array($tokVec) || count($tokVec) !== $dim)
                        continue;
                    for ($i = 0; $i < $dim; $i++)
                        $sum[$i] += (float) $tokVec[$i];
                    $count++;
                }
                if ($count > 0) {
                    for ($i = 0; $i < $dim; $i++)
                        $sum[$i] /= $count;
                    // chuẩn hoá L2 nhẹ để ổn định cosine
                    $norm = sqrt(array_reduce($sum, fn($c, $v) => $c + $v * $v, 0));
                    if ($norm > 0)
                        $sum = array_map(fn($v) => $v / $norm, $sum);
                    return $sum;
                }
                return [];
            } else {
                // đã pooled -> đảm bảo float
                return array_map('floatval', $json);
            }
        }

        Log::warning('HF embed unexpected payload', ['payload_sample' => mb_substr(json_encode($json), 0, 300)]);
        return [];
    }

    // app/Services/EmbeddingService.php
    public static function mix(array $a, array $b, float $alpha = 0.7): array
    {
        if (empty($a) && empty($b))
            return [];
        if (empty($b))
            return $a;
        if (empty($a))
            return $b;
        $n = min(count($a), count($b));
        $out = [];
        for ($i = 0; $i < $n; $i++)
            $out[$i] = $alpha * $a[$i] + (1 - $alpha) * $b[$i];
        // chuẩn hóa nhẹ
        $norm = sqrt(array_reduce($out, fn($c, $v) => $c + $v * $v, 0));
        if ($norm > 0)
            $out = array_map(fn($v) => $v / $norm, $out);
        return $out;
    }

    /** cosine similarity 0..1 (đã scale vào [0..1]) */
    public static function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0)
            return 0.0;
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na == 0 || $nb == 0)
            return 0.0;
        $sim = $dot / (sqrt($na) * sqrt($nb));
        return max(0.0, min(1.0, ($sim + 1) / 2));
    }
    /**
     * Batch embedding cho nhiều câu 1 lần
     * @param string[] $texts
     * @param string|null $model - override model khác (ví dụ multilingual MiniLM)
     * @return float[][] embeddings đã chuẩn hoá
     */
    public function embedBatch(array $texts, ?string $model = null): array
    {
        $texts = array_values(array_filter(array_map('trim', $texts)));
        if (empty($texts))
            return [];

        $endpoint = "https://router.huggingface.co/hf-inference/models/"
            . ($model ?: $this->model) . "/pipeline/feature-extraction";

        $resp = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                'inputs' => $texts,
                'options' => ['wait_for_model' => true],
                'parameters' => ['pooling' => 'mean', 'normalize' => true],
            ]);

        if (!$resp->ok()) {
            Log::warning('HF embedBatch error', [
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 500),
            ]);
            return [];
        }

        $json = $resp->json();
        // Nếu HF trả từng vector riêng lẻ
        if (isset($json[0]) && is_array($json[0]) && is_float($json[0][0] ?? null)) {
            // mảng các vector pooled
            return array_map(fn($v) => array_map('floatval', $v), $json);
        }
        // Nếu HF trả token-level cho từng câu
        if (isset($json[0]) && is_array($json[0]) && is_array($json[0][0])) {
            return array_map(function ($tokVecs) {
                $dim = count($tokVecs[0] ?? []);
                $sum = array_fill(0, $dim, 0.0);
                $count = 0;
                foreach ($tokVecs as $vec) {
                    for ($i = 0; $i < $dim; $i++)
                        $sum[$i] += (float) $vec[$i];
                    $count++;
                }
                if ($count > 0) {
                    for ($i = 0; $i < $dim; $i++)
                        $sum[$i] /= $count;
                    $norm = sqrt(array_reduce($sum, fn($c, $v) => $c + $v * $v, 0));
                    if ($norm > 0)
                        $sum = array_map(fn($v) => $v / $norm, $sum);
                }
                return $sum;
            }, $json);
        }
        return [];
    }

    /**
     * Gom trùng semantic (dedup) bằng cosine
     * @param float[][] $embeddings
     * @param float $threshold
     * @return int[] chỉ số các phần tử giữ lại
     */
    public static function semanticDedup(array $embeddings, float $threshold = 0.85): array
    {
        $N = count($embeddings);
        $taken = array_fill(0, $N, false);
        $keep = [];
        for ($i = 0; $i < $N; $i++) {
            if ($taken[$i])
                continue;
            $keep[] = $i;
            for ($j = $i + 1; $j < $N; $j++) {
                if ($taken[$j])
                    continue;
                $sim = self::cosine($embeddings[$i], $embeddings[$j]);
                if ($sim >= $threshold)
                    $taken[$j] = true;
            }
        }
        return $keep;
    }

    /** Sinh n-gram đơn giản */
    public static function ngrams(string $s, int $maxN = 3): array
    {
        $toks = preg_split('/\s+/u', mb_strtolower(trim($s)));
        $toks = array_values(array_filter($toks));
        $res = [];
        $nT = count($toks);
        for ($n = 1; $n <= $maxN; $n++) {
            for ($i = 0; $i + $n <= $nT; $i++) {
                $res[] = implode(' ', array_slice($toks, $i, $n));
            }
        }
        return $res;
    }

    /** Min-Max scale tiện dụng */
    public static function minMaxScale(array $xs): array
    {
        $min = min($xs);
        $max = max($xs);
        if ($max - $min < 1e-9)
            return array_fill(0, count($xs), 1.0);
        return array_map(fn($x) => ($x - $min) / ($max - $min), $xs);
    }
    // app/Services/EmbeddingService.php
    public function extractKeyphrases(string $text, string $model = 'ml6team/keyphrase-extraction-distilbert-inspec'): array
    {
        $endpoint = "https://api-inference.huggingface.co/models/{$model}";

        $resp = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                'inputs' => $text,
                'options' => ['wait_for_model' => true],
            ]);

        if (!$resp->ok()) {
            Log::warning('HF keyphrase HTTP error', [
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 500),
            ]);
            return [];
        }

        return $resp->json();
    }

}
