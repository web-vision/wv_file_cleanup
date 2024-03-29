<?php

defined('TYPO3_MODE') || die();

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'WebVision.WvFileCleanup',
    'file',
    'cleanup',
    '',
    [
        \WebVision\WvFileCleanup\Controller\CleanupController::class => 'index, cleanup',
    ],
    [
        'access' => 'user,group',
        'workspaces' => 'online,custom',
        'icon' => 'EXT:wv_file_cleanup/Resources/Public/Icons/module-cleanup.svg',
        'labels' => 'LLL:EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf',
    ]
);
