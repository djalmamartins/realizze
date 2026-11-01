<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class CmsAuditLog extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'cms_audit_logs',
            protected: ['id'],
            required: ['event_type']
        );
    }
}
