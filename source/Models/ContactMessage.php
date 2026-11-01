<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class ContactMessage extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'contact_messages',
            protected: ['id'],
            required: ['name', 'email', 'subject', 'message']
        );
    }
}
