<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class HelpdeskRating extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'helpdesk_ratings',
            protected: ['id'],
            required: ['ticket_id', 'rating']
        );
    }
}
