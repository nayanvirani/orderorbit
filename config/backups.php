<?php

return [
    // Where database backups are written (by hand, from Super admin › Backups). A Railway volume
    // mounted here on the web service
    // keeps them across deploys (Railway › orderorbit › Volumes, mount path /app/storage/backups).
    'path' => env('BACKUP_PATH', storage_path('backups')),
];
