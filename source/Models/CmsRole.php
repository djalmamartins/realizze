<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class CmsRole extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'cms_roles',
            protected: ['id'],
            required: ['role_key', 'name']
        );
    }
}
