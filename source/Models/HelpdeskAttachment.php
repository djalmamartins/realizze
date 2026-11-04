<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class HelpdeskAttachment extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'helpdesk_attachments',
            protected: ['id'],
            required: ['ticket_id', 'message_id', 'stored_path', 'original_name', 'mime_type', 'size_bytes']
        );
    }
}
