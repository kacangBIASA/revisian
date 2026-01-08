<?php
// app/config/plans.php

return [
    'free' => [
        'branch_limit'          => 1,       // Free max 1 cabang
        'history_retention_days'=> 30,      // Free simpan 1 bulan
        'charts'                => false,   // chart hanya pro
        'reports_export'        => false,   // export PDF/Excel hanya pro
    ],
    'pro' => [
        'branch_limit'          => null,    // null = unlimited
        'history_retention_days'=> null,    // null = unlimited
        'charts'                => true,
        'reports_export'        => true,
    ],
];
