-- Plataforma: planos, histórico de preços, assinaturas e cobranças da assinatura Realizze.
-- Separado de finance_*: nada aqui representa finanças privadas dos clientes.
CREATE TABLE plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_key VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  benefits_json JSON NOT NULL,
  features_json JSON NOT NULL,
  max_companies INT UNSIGNED DEFAULT NULL,
  max_users INT UNSIGNED DEFAULT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY plans_key_unique (plan_key),
  KEY plans_public_index (status, is_public, sort_order),
  CONSTRAINT plans_status_check CHECK (status IN ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plan_prices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id INT UNSIGNED NOT NULL,
  monthly_price DECIMAL(12,2) NOT NULL,
  annual_price DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  valid_from DATETIME NOT NULL,
  valid_to DATETIME DEFAULT NULL,
  change_reason VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY plan_prices_current_index (plan_id, valid_to),
  CONSTRAINT plan_prices_plan_fk FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT,
  CONSTRAINT plan_prices_user_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT plan_prices_amount_check CHECK (monthly_price >= 0 AND annual_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  organization_id BIGINT UNSIGNED DEFAULT NULL,
  plan_id INT UNSIGNED NOT NULL,
  plan_price_id INT UNSIGNED NOT NULL,
  billing_cycle VARCHAR(10) NOT NULL,
  contracted_amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  started_at DATETIME DEFAULT NULL,
  renews_at DATE DEFAULT NULL,
  canceled_at DATETIME DEFAULT NULL,
  cancel_reason VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY subscriptions_status_index (status),
  KEY subscriptions_user_index (user_id, status),
  KEY subscriptions_org_index (organization_id, status),
  CONSTRAINT subscriptions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT subscriptions_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT,
  CONSTRAINT subscriptions_plan_fk FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT,
  CONSTRAINT subscriptions_price_fk FOREIGN KEY (plan_price_id) REFERENCES plan_prices (id) ON DELETE RESTRICT,
  CONSTRAINT subscriptions_creator_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT subscriptions_owner_check CHECK ((user_id IS NULL) <> (organization_id IS NULL)),
  CONSTRAINT subscriptions_cycle_check CHECK (billing_cycle IN ('monthly','annual')),
  CONSTRAINT subscriptions_status_check CHECK (status IN ('pending','active','past_due','canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subscription_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  from_status VARCHAR(20) DEFAULT NULL,
  to_status VARCHAR(20) DEFAULT NULL,
  from_plan_id INT UNSIGNED DEFAULT NULL,
  to_plan_id INT UNSIGNED DEFAULT NULL,
  contracted_amount DECIMAL(12,2) DEFAULT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  actor_user_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY subscription_events_timeline_index (subscription_id, id),
  CONSTRAINT subscription_events_sub_fk FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE RESTRICT,
  CONSTRAINT subscription_events_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_charges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subscription_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(40) DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  due_date DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  paid_at DATETIME DEFAULT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'manual',
  provider_reference VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY platform_charges_reference_unique (reference),
  UNIQUE KEY platform_charges_period_unique (subscription_id, due_date),
  UNIQUE KEY platform_charges_provider_unique (provider, provider_reference),
  KEY platform_charges_status_index (status, due_date),
  CONSTRAINT platform_charges_sub_fk FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE RESTRICT,
  CONSTRAINT platform_charges_status_check CHECK (status IN ('pending','overdue','paid','failed','canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_charge_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  charge_id BIGINT UNSIGNED NOT NULL,
  outcome VARCHAR(20) NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'manual',
  provider_reference VARCHAR(120) DEFAULT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  actor_user_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY platform_charge_attempts_charge_index (charge_id, id),
  CONSTRAINT platform_charge_attempts_charge_fk FOREIGN KEY (charge_id) REFERENCES platform_charges (id) ON DELETE RESTRICT,
  CONSTRAINT platform_charge_attempts_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
