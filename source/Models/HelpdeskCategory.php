<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class HelpdeskCategory extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'helpdesk_categories',
            protected: ['id'],
            required: ['name']
        );
    }
}
