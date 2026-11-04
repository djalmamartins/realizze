<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class PlanPrice extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'plan_prices',
            protected: ['id'],
            required: ['plan_id', 'monthly_price', 'annual_price', 'valid_from']
        );
    }
}
