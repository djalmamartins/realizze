<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class CmsMenuItem extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'cms_menu_items',
            protected: ['id'],
            required: ['location', 'label', 'url']
        );
    }
}
