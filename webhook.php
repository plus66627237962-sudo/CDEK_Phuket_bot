<?php
//webhook.php
require_once 'config.php';
require_once 'db_connect.php';
require_once 'ClientTelegram.php';
require_once 'ClientGemini.php';
require_once 'ChatRouter.php';

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$tg = new ClientTelegram(TG_TOKEN);
$gemini = new ClientGemini(GEMINI_API_KEY);
$router = new ChatRouter($pdo, $tg, $gemini, MANAGER_GROUP_ID);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    if (empty($text)) exit;

    if ((string)$chatId === (string)MANAGER_GROUP_ID) {
        if ($topicId = $message['message_thread_id'] ?? null) $router->handleManagerReply($topicId, $text);
    } else {
        $firstName = $message['from']['first_name'] ?? '';
        $router->handleClientMessage('telegram', (string)$chatId, $text, $firstName);
    }
}