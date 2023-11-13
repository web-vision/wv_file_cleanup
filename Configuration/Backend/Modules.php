<?php

return [
    'file_WvFileCleanupCleanup' => [
        'parent' => 'file',
        'access' => 'user,group',
        'workspaces' => 'online,custom',
        'path' => '/file/wvfilecleanup',
        'icon' => 'EXT:wv_file_cleanup/Resources/Public/Icons/module-cleanup.svg',
        'labels' => 'LLL:EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf',
        'extensionName' => 'wv_file_cleanup',
        'controllerActions' => [
            \WebVision\WvFileCleanup\Controller\CleanupController::class => ['index', 'cleanup'],
        ],
    ],
];
