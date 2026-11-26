<?php

return [
    // Folder file status sesi live (harus bisa diakses web). Default public/live.
    'state_dir' => env('LIVE_STATE_DIR'),
    'state_url' => env('LIVE_STATE_URL'),
    // Jeda polling HP siswa (milidetik).
    'poll_ms' => (int) env('LIVE_POLL_MS', 1000),
];
