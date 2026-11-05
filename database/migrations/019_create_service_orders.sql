-- Ordens de serviço comerciais. Cobrança é opcional e não representa recebimento.
CREATE TABLE organization_service_order_sequences (
 organization_id BIGINT UNSIGNED NOT NULL, sequence_year SMALLINT UNSIGNED NOT NULL, current_number INT UNSIGNED NOT NULL,
 PRIMARY KEY (organization_id,sequence_year), CONSTRAINT service_order_sequence_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE organization_service_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 financial_context_id BIGINT UNSIGNED NOT NULL, order_number VARCHAR(40) NOT NULL, customer_contact_id BIGINT UNSIGNED NOT NULL,
 description TEXT DEFAULT NULL, responsible_user_id INT UNSIGNED DEFAULT NULL, start_date DATE DEFAULT NULL, due_date DATE DEFAULT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft', subtotal_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
 discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0, total_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
 notes TEXT DEFAULT NULL, receivable_entry_id BIGINT UNSIGNED DEFAULT NULL, idempotency_key CHAR(64) NOT NULL,
 created_by INT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY service_orders_number_uq (organization_id,order_number), UNIQUE KEY service_orders_idempotency_uq (organization_id,idempotency_key),
 UNIQUE KEY service_orders_receivable_uq (receivable_entry_id), KEY service_orders_status_idx (organization_id,status,due_date),
 CONSTRAINT service_orders_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
 CONSTRAINT service_orders_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
 CONSTRAINT service_orders_customer_fk FOREIGN KEY (customer_contact_id) REFERENCES finance_contacts(id) ON DELETE RESTRICT,
 CONSTRAINT service_orders_responsible_fk FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT service_orders_receivable_fk FOREIGN KEY (receivable_entry_id) REFERENCES finance_entries(id) ON DELETE RESTRICT,
 CONSTRAINT service_orders_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT service_orders_status_ck CHECK (status IN ('draft','open','in_progress','completed','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE organization_service_order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, service_order_id BIGINT UNSIGNED NOT NULL, item_type VARCHAR(20) NOT NULL,
 description VARCHAR(255) NOT NULL, quantity_milli BIGINT UNSIGNED NOT NULL, unit_price_cents BIGINT UNSIGNED NOT NULL,
 discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0, total_cents BIGINT UNSIGNED NOT NULL,
 KEY service_order_items_order_idx (service_order_id), CONSTRAINT service_order_items_order_fk FOREIGN KEY (service_order_id) REFERENCES organization_service_orders(id) ON DELETE CASCADE,
 CONSTRAINT service_order_items_type_ck CHECK (item_type IN ('service','item'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE organization_service_order_attachments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, service_order_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL,
 uploaded_by INT UNSIGNED DEFAULT NULL, stored_path VARCHAR(255) NOT NULL, original_name VARCHAR(190) NOT NULL,
 mime_type VARCHAR(100) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT service_order_attachment_order_fk FOREIGN KEY (service_order_id) REFERENCES organization_service_orders(id) ON DELETE CASCADE,
 CONSTRAINT service_order_attachment_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
 CONSTRAINT service_order_attachment_user_fk FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE organization_service_order_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, service_order_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL,
 actor_user_id INT UNSIGNED DEFAULT NULL, action VARCHAR(40) NOT NULL, metadata_json JSON DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY service_order_history_idx (service_order_id,created_at),
 CONSTRAINT service_order_history_order_fk FOREIGN KEY (service_order_id) REFERENCES organization_service_orders(id) ON DELETE CASCADE,
 CONSTRAINT service_order_history_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
 CONSTRAINT service_order_history_user_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
