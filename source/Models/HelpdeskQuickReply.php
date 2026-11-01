<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class HelpdeskQuickReply extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'helpdesk_quick_replies',
            protected: ['id'],
            required: ['title', 'body']
        );
    }
}
