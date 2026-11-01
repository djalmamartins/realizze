<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class HelpdeskTicket extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'helpdesk_tickets',
            protected: ['id'],
            required: ['requester_user_id', 'category_id', 'subject', 'idempotency_key']
        );
    }
}
