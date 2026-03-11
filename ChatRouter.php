<?php
class ChatRouter {
    private PDO $db;
    private TelegramClient $tg;
    private GeminiClient $gemini;
    private string $managerGroupId;

    public function __construct(PDO $db, TelegramClient $tg, GeminiClient $gemini, string $managerGroupId) {
        $this->db = $db;
        $this->tg = $tg;
        $this->gemini = $gemini;
        $this->managerGroupId = $managerGroupId;
    }

    private function getOrCreateClient(string $platform, string $externalId, string $clientName = ''): array {
        $stmt = $this->db->prepare("SELECT id FROM clients WHERE platform = ? AND external_id = ?");
        $stmt->execute([$platform, $externalId]);
        $clientId = $stmt->fetchColumn();

        if (!$clientId) {
            $stmt = $this->db->prepare("INSERT INTO clients (platform, external_id) VALUES (?, ?)");
            $stmt->execute([$platform, $externalId]);
            $clientId = $this->db->lastInsertId();
            
            $topicName = "[TG] " . ($clientName ?: $externalId);
            $topicId = $this->tg->createForumTopic($this->managerGroupId, $topicName);

            $stmt = $this->db->prepare("INSERT INTO topics (client_id, telegram_topic_id) VALUES (?, ?)");
            $stmt->execute([$clientId, $topicId]);
            return ['client_id' => (int)$clientId, 'topic_id' => $topicId, 'dialog_state' => 'bot'];
        }

        $stmt = $this->db->prepare("SELECT telegram_topic_id as topic_id, dialog_state FROM topics WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $topicData = $stmt->fetch(PDO::FETCH_ASSOC);
        $topicData['client_id'] = (int)$clientId;
        
        return $topicData;
    }

    private function logMessage(int $clientId, string $sender, string $text): void {
        $stmt = $this->db->prepare("INSERT INTO messages (client_id, sender, message_text) VALUES (?, ?, ?)");
        $stmt->execute([$clientId, $sender, $text]);
    }

    private function getHistory(int $clientId, int $limit = 5): array {
        $stmt = $this->db->prepare("SELECT sender, message_text FROM messages WHERE client_id = ? ORDER BY id DESC LIMIT ?");
        $stmt->execute([$clientId, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function handleClientMessage(string $platform, string $externalId, string $text, string $clientName = ''): void {
        $clientData = $this->getOrCreateClient($platform, $externalId, $clientName);
        $clientId = $clientData['client_id'];
        $topicId = $clientData['topic_id'];
        
        $this->logMessage($clientId, 'client', $text);

        if ($topicId) {
            $this->tg->sendMessage($this->managerGroupId, "Client: " . $text, $topicId);
        }

        if ($clientData['dialog_state'] === 'bot') {
            $history = $this->getHistory($clientId);
            $replyText = $this->gemini->generateResponse($text, $history);
            
            $this->tg->sendMessage($externalId, $replyText);
            $this->logMessage($clientId, 'bot', $replyText);
            
            if ($topicId) {
                $this->tg->sendMessage($this->managerGroupId, "Bot: " . $replyText, $topicId);
            }
        }
    }

    public function handleManagerReply(int $telegramTopicId, string $text): void {
        $stmt = $this->db->prepare("
            SELECT t.client_id, c.platform, c.external_id 
            FROM topics t
            JOIN clients c ON t.client_id = c.id
            WHERE t.telegram_topic_id = ?
        ");
        $stmt->execute([$telegramTopicId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            $stmt = $this->db->prepare("UPDATE topics SET dialog_state = 'human' WHERE client_id = ?");
            $stmt->execute([$client['client_id']]);

            $this->logMessage($client['client_id'], 'manager', $text);
            
            if ($client['platform'] === 'telegram') {
                $this->tg->sendMessage($client['external_id'], $text);
            }
        }
    }
}