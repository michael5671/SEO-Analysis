<?php
 
namespace App\Services;

use Illuminate\Support\Facades\Http;

class KeyphraseService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('KEYPHRASE_API', 'http://127.0.0.1:8088');
    }

    public function extract(string $text, int $topN = 20): array
    {
        $resp = Http::timeout(60)
            ->post($this->baseUrl . '/keyphrases', [
                'text' => $text,
                'top_n' => $topN,
            ]);

        if ($resp->ok()) {
            return $resp->json();
        }
        return [];
    }
}
