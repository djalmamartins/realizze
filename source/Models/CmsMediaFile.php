<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class CmsMediaFile extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'cms_media',
            protected: ['id'],
            required: ['stored_path', 'original_name', 'mime_type', 'size_bytes']
        );
    }
}
