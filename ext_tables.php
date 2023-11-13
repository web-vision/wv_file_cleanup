<?php

defined('TYPO3') || die();

if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 12) {
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
}
