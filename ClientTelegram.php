<?php
//ClientTelegram.php
class ClientTelegram {
    private string $token;
    private string $apiUrl;

    public function __construct(string $token) {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}/";
    }

    private function request(string $method, array $data = []): array {
        $ch = curl_init($this->apiUrl . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch); curl_close($ch);
        return json_decode($response, true) ?: [];
    }

    public function sendMessage(string $chatId, string $text, ?int $messageThreadId = null): array {
        $data = ['chat_id' => $chatId, 'text' => $text];
        if ($messageThreadId) $data['message_thread_id'] = $messageThreadId;
        return $this->request('sendMessage', $data);
    }

    public function createForumTopic(string $chatId, string $name): ?int {
        $response = $this->request('createForumTopic', ['chat_id' => $chatId, 'name' => $name]);
        return $response['result']['message_thread_id'] ?? null;
    }

    public function setChatMenuButton(string $webAppUrl): array {
        return $this->request('setChatMenuButton', ['menu_button' => ['type' => 'web_app', 'text' => 'Оформить доставку', 'web_app' => ['url' => $webAppUrl]]]);
    }
}