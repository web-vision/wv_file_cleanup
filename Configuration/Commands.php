<?php

return [
    'wv_file_cleanup:cleanup' => [
        'class' => \WebVision\WvFileCleanup\Command\CleanupCommand::class,
    ],
    'wv_file_cleanup:emptyrecycler' => [
        'class' => \WebVision\WvFileCleanup\Command\EmptyRecyclerCommand::class,
    ],
];
