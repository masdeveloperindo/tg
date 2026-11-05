<?php

return [
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'max:20480'],
        // Current upload UI is admin-only. Future client upload use cases need their own policy.
        'middleware' => ['auth', 'active', 'admin.session', 'auth.session', 'password.changed', 'admin.role', 'admin.2fa', 'private.response', 'throttle:60,1'],
        'cleanup' => true,
        'max_upload_time' => 5,
    ],
];
