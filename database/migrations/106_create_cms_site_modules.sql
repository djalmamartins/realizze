-- Destaques, depoimentos, acessos, comunicações, versões, log técnico e pastas da base de conhecimento.
CREATE TABLE cms_slides (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(140) NOT NULL,
  content TEXT NOT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  link_url VARCHAR(255) NOT NULL DEFAULT '',
  link_label VARCHAR(60) NOT NULL DEFAULT '',
  alignment VARCHAR(10) NOT NULL DEFAULT 'left',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY cms_slides_public_index (status, sort_order),
  CONSTRAINT cms_slides_status_check CHECK (status IN ('draft','published')),
  CONSTRAINT cms_slides_alignment_check CHECK (alignment IN ('left','center','right'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_testimonials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_name VARCHAR(120) NOT NULL,
  company VARCHAR(120) NOT NULL DEFAULT '',
  body VARCHAR(1000) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  photo_path VARCHAR(255) DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY cms_testimonials_public_index (status, sort_order),
  CONSTRAINT cms_testimonials_status_check CHECK (status IN ('draft','published')),
  CONSTRAINT cms_testimonials_rating_check CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sem IP nem identificação: somente hashes que mudam a cada dia.
CREATE TABLE cms_page_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  path VARCHAR(190) NOT NULL,
  visitor_hash CHAR(64) NOT NULL,
  session_hash CHAR(64) NOT NULL,
  viewed_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY cms_page_views_time_index (viewed_at),
  KEY cms_page_views_path_index (path, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_broadcasts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  body VARCHAR(2000) NOT NULL,
  audience VARCHAR(12) NOT NULL DEFAULT 'all',
  channel VARCHAR(10) NOT NULL DEFAULT 'internal',
  priority VARCHAR(10) NOT NULL DEFAULT 'info',
  link VARCHAR(255) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'scheduled',
  scheduled_at DATETIME NOT NULL,
  expires_at DATETIME DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  recipients INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY cms_broadcasts_due_index (status, scheduled_at),
  CONSTRAINT cms_broadcasts_user_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT cms_broadcasts_audience_check CHECK (audience IN ('all','customers','staff')),
  CONSTRAINT cms_broadcasts_channel_check CHECK (channel IN ('internal','email')),
  CONSTRAINT cms_broadcasts_priority_check CHECK (priority IN ('info','warning','critical')),
  CONSTRAINT cms_broadcasts_status_check CHECK (status IN ('scheduled','sent','canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications
  ADD COLUMN body VARCHAR(2000) NOT NULL DEFAULT '' AFTER title,
  ADD COLUMN priority VARCHAR(10) NOT NULL DEFAULT 'info' AFTER body,
  ADD COLUMN broadcast_id INT UNSIGNED DEFAULT NULL AFTER ticket_id,
  ADD COLUMN expires_at DATETIME DEFAULT NULL AFTER read_at,
  ADD CONSTRAINT notifications_broadcast_fk FOREIGN KEY (broadcast_id) REFERENCES cms_broadcasts (id) ON DELETE CASCADE;

CREATE TABLE cms_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product VARCHAR(12) NOT NULL,
  version VARCHAR(30) NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT '',
  notes TEXT NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_versions_unique (product, version),
  CONSTRAINT cms_versions_user_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_system_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fingerprint CHAR(40) NOT NULL,
  level VARCHAR(10) NOT NULL,
  message VARCHAR(500) NOT NULL,
  source VARCHAR(255) NOT NULL DEFAULT '',
  path VARCHAR(190) NOT NULL DEFAULT '',
  occurrences INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(10) NOT NULL DEFAULT 'open',
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY cms_system_logs_fingerprint_unique (fingerprint),
  KEY cms_system_logs_list_index (status, last_seen_at),
  CONSTRAINT cms_system_logs_level_check CHECK (level IN ('error','warning','notice')),
  CONSTRAINT cms_system_logs_status_check CHECK (status IN ('open','resolved'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE help_article_votes (
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  helpful TINYINT(1) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, user_id),
  CONSTRAINT help_article_votes_post_fk FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
  CONSTRAINT help_article_votes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cms_role_permissions (role_id, permission_key)
SELECT r.id, p.permission_key FROM cms_roles r JOIN (
  SELECT 'admin' role_key, 'reports.view' permission_key UNION ALL SELECT 'admin','broadcasts.send' UNION ALL SELECT 'admin','versions.manage' UNION ALL SELECT 'admin','system_logs.view'
  UNION ALL SELECT 'editor','reports.view'
) p ON p.role_key = r.role_key;
