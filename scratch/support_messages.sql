CREATE TABLE IF NOT EXISTS support_messages (
    idSupportMessage INT NOT NULL AUTO_INCREMENT,
    sender_user_id INT NOT NULL,
    recipient_user_id INT NOT NULL,
    sender_role VARCHAR(20) NOT NULL,
    recipient_role VARCHAR(20) NOT NULL,
    message_text TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (idSupportMessage),
    INDEX idx_support_sender (sender_user_id),
    INDEX idx_support_recipient (recipient_user_id, recipient_role, is_read),
    INDEX idx_support_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
