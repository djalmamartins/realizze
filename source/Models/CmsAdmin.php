<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class CmsAdmin extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'cms_admins',
            protected: ['id'],
            required: ['user_id', 'role_id']
        );
    }
}
