<?php

return [
    // Internal registration approved after restored-backup rehearsal and regression.
    // Public booking remains blocked until real delivery and HTTPS are verified.
    'enabled' => true,
    'channels' => ['internal' => true, 'viti' => true, 'public_booking' => false],
    'verification_minutes' => 30,
    'pending_retention_days' => 7,
];
