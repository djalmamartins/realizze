<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class CmsPage extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'cms_pages',
            protected: ['id'],
            required: ['slug', 'title', 'content']
        );
    }
}
