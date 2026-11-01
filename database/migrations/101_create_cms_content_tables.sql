-- CMS: páginas, mídia, menus e SEO. Reaproveita posts, categories e faqs existentes.
CREATE TABLE cms_media (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  stored_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  width INT UNSIGNED DEFAULT NULL,
  height INT UNSIGNED DEFAULT NULL,
  title VARCHAR(190) NOT NULL DEFAULT '',
  alt_text VARCHAR(255) NOT NULL DEFAULT '',
  uploaded_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_media_path_unique (stored_path),
  CONSTRAINT cms_media_user_fk FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(190) NOT NULL,
  excerpt VARCHAR(500) NOT NULL DEFAULT '',
  content LONGTEXT NOT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  seo_title VARCHAR(190) NOT NULL DEFAULT '',
  seo_description VARCHAR(320) NOT NULL DEFAULT '',
  canonical_url VARCHAR(255) NOT NULL DEFAULT '',
  og_image VARCHAR(255) NOT NULL DEFAULT '',
  robots_index TINYINT(1) NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  published_at DATETIME DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_pages_slug_unique (slug),
  KEY cms_pages_status_index (status),
  CONSTRAINT cms_pages_creator_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT cms_pages_editor_fk FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT cms_pages_status_check CHECK (status IN ('draft','published'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_page_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  page_id INT UNSIGNED NOT NULL,
  snapshot_json JSON NOT NULL,
  note VARCHAR(80) NOT NULL DEFAULT '',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY cms_page_revisions_page_index (page_id, id),
  CONSTRAINT cms_page_revisions_page_fk FOREIGN KEY (page_id) REFERENCES cms_pages (id) ON DELETE CASCADE,
  CONSTRAINT cms_page_revisions_user_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_menu_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  location VARCHAR(20) NOT NULL DEFAULT 'header',
  label VARCHAR(80) NOT NULL,
  url VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY cms_menu_location_index (location, is_visible, sort_order),
  CONSTRAINT cms_menu_location_check CHECK (location IN ('header','footer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_seo_routes (
  route_path VARCHAR(160) NOT NULL,
  seo_title VARCHAR(190) NOT NULL DEFAULT '',
  seo_description VARCHAR(320) NOT NULL DEFAULT '',
  canonical_url VARCHAR(255) NOT NULL DEFAULT '',
  og_image VARCHAR(255) NOT NULL DEFAULT '',
  robots_index TINYINT(1) NOT NULL DEFAULT 1,
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (route_path),
  CONSTRAINT cms_seo_routes_user_fk FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE posts
  ADD COLUMN post_type VARCHAR(20) NOT NULL DEFAULT 'article' AFTER category,
  ADD COLUMN seo_title VARCHAR(190) NOT NULL DEFAULT '' AFTER video,
  ADD COLUMN seo_description VARCHAR(320) NOT NULL DEFAULT '' AFTER seo_title,
  ADD COLUMN canonical_url VARCHAR(255) NOT NULL DEFAULT '' AFTER seo_description,
  ADD COLUMN og_image VARCHAR(255) NOT NULL DEFAULT '' AFTER canonical_url,
  ADD COLUMN robots_index TINYINT(1) NOT NULL DEFAULT 1 AFTER og_image,
  ADD KEY posts_public_index (status, post_type, post_at);
