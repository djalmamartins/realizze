-- Troca do e-mail de acesso: o novo endereço só vale depois de confirmado por link enviado a ele.
CREATE TABLE IF NOT EXISTS user_email_changes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    new_email VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_email_changes_token_unique (token_hash),
    KEY user_email_changes_user_index (user_id, used_at),
    CONSTRAINT user_email_changes_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
