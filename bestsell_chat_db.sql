-- database.sql
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(50) NOT NULL, -- 'telegram', 'whatsapp'
    external_id VARCHAR(100) NOT NULL, -- Telegram User ID or WA phone number
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_client (platform, external_id)
);

CREATE TABLE topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    telegram_topic_id BIGINT NULL, -- ID of the topic in the manager's group
    dialog_state ENUM('bot', 'human') DEFAULT 'bot',
    human_timeout TIMESTAMP NULL, -- When the bot should take over again
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    sender ENUM('client', 'bot', 'manager') NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);