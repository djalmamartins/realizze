<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class HelpdeskEvent extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'helpdesk_events',
            protected: ['id'],
            required: ['ticket_id', 'event_type']
        );
    }
}
