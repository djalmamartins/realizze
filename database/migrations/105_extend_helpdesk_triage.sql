-- Atendimento avançado: equipe, tags, tempo dedicado e abertura pela equipe.
ALTER TABLE helpdesk_tickets
  ADD COLUMN team VARCHAR(60) NOT NULL DEFAULT '' AFTER assignee_user_id,
  ADD COLUMN opened_by_user_id INT UNSIGNED DEFAULT NULL AFTER team,
  ADD CONSTRAINT helpdesk_tickets_opener_fk FOREIGN KEY (opened_by_user_id) REFERENCES users (id) ON DELETE SET NULL;

CREATE TABLE helpdesk_ticket_tags (
  ticket_id BIGINT UNSIGNED NOT NULL,
  tag VARCHAR(40) NOT NULL,
  PRIMARY KEY (ticket_id, tag),
  KEY helpdesk_ticket_tags_tag_index (tag),
  CONSTRAINT helpdesk_ticket_tags_ticket_fk FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE helpdesk_time_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  minutes SMALLINT UNSIGNED NOT NULL,
  note VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY helpdesk_time_entries_ticket_index (ticket_id),
  CONSTRAINT helpdesk_time_entries_ticket_fk FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id) ON DELETE CASCADE,
  CONSTRAINT helpdesk_time_entries_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
