-- Mensagens do formulário público da Central de Suporte.
CREATE TABLE contact_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message VARCHAR(4000) NOT NULL,
  whatsapp_ok TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'new',
  handled_by INT UNSIGNED DEFAULT NULL,
  handled_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY contact_messages_list_index (status, id),
  CONSTRAINT contact_messages_user_fk FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT contact_messages_status_check CHECK (status IN ('new','answered'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
