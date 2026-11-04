<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class HelpdeskMessage extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'helpdesk_messages',
            protected: ['id'],
            required: ['ticket_id', 'author_type', 'body']
        );
    }
}
