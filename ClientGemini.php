<?php
class ClientGemini {
    private string $apiKey;
    private string $apiUrl;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
    }

    public function generateResponse(string $prompt, array $history = []): string {
        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg['sender'] === 'bot' ? 'model' : 'user',
                'parts' => [['text' => $msg['message_text']]]
            ];
        }
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];

        $data = ['contents' => $contents];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'System error generating response';
    }
}