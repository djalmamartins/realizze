CREATE TABLE IF NOT EXISTS user_legal_acceptances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    document_version VARCHAR(30) NOT NULL,
    accepted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(512) DEFAULT NULL,

    PRIMARY KEY (id),

    KEY user_legal_acceptances_user_index (user_id),

    CONSTRAINT user_legal_acceptances_user_fk
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT user_legal_acceptances_type_check
        CHECK (document_type IN ('terms_of_use', 'privacy_policy'))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
