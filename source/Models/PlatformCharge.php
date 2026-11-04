<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class PlatformCharge extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'platform_charges',
            protected: ['id'],
            required: ['subscription_id', 'amount', 'due_date']
        );
    }
}
