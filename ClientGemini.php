<?php
// ClientGemini.php
class ClientGemini {
    private string $apiKey;
    private ?string $activeModel = null;
    public function __construct(string $apiKey) { $this->apiKey = $apiKey; }
    private function getBestModel(): string {
        if ($this->activeModel) return $this->activeModel;
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models?key={$this->apiKey}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = json_decode(curl_exec($ch), true); curl_close($ch);
        $best = 'gemini-2.5-flash-lite';
        if (!empty($res['models'])) {
            $lite = []; $flash = [];
            foreach ($res['models'] as $m) {
                $n = $m['name'] ?? ''; $methods = $m['supportedGenerationMethods'] ?? [];
                if (!in_array('generateContent', $methods) || stripos($n, 'pro') !== false || stripos($n, 'ultra') !== false || preg_match('/gemini-(1\.|2\.0)/', $n)) continue;
                $clean = str_replace('models/', '', $n);
                preg_match('/\d+\.\d+/', $n, $v);
                $data = ['name' => $clean, 'v' => !empty($v) ? (float)$v[0] : 999];
                if (stripos($n, 'lite') !== false) $lite[] = $data;
                elseif (stripos($n, 'flash') !== false) $flash[] = $data;
            }
            if (!empty($lite)) { usort($lite, fn($a, $b) => $a['v'] <=> $b['v']); $best = $lite[0]['name']; }
            elseif (!empty($flash)) { usort($flash, fn($a, $b) => $a['v'] <=> $b['v']); $best = $flash[0]['name']; }
        }
        return $this->activeModel = $best;
    }
public function generateResponse(string $prompt, array $history = []): string {
        $contents = [];
        foreach ($history as $msg) $contents[] = ['role' => $msg['sender'] === 'bot' ? 'model' : 'user', 'parts' => [['text' => $msg['message_text']]]];
        $contents[] = ['role' => 'user', 'parts' => [['text' => $prompt]]];
        $model = $this->getBestModel();
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['contents' => $contents]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch); curl_close($ch);
        $result = json_decode($res, true);
        if (!isset($result['candidates'])) throw new Exception("Gemini API Error ({$model}): " . $res);
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) throw new Exception("Gemini API Content Error ({$model}): " . $res);
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
}