<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class Subscription extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'subscriptions',
            protected: ['id'],
            required: ['plan_id', 'plan_price_id', 'billing_cycle', 'contracted_amount']
        );
    }
}
