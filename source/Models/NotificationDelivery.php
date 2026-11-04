<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class NotificationDelivery extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'notification_deliveries',
            protected: ['id'],
            required: ['channel', 'dedupe_key', 'payload_json']
        );
    }
}
