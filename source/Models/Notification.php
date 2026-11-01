<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class Notification extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'notifications',
            protected: ['id'],
            required: ['user_id', 'audience', 'event_type', 'title', 'dedupe_key']
        );
    }
}
