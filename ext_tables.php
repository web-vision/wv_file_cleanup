<?php
defined('TYPO3_MODE') || die();

if (TYPO3_MODE === 'BE') {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'WebVision.WvFileCleanup',
        'file',
        'cleanup',
        '',
        array(
            'Cleanup' => 'index, cleanup, recycler',
        ),
        array(
            'access' => 'user,group',
            'workspaces' => 'online,custom',
            'icon' => 'EXT:wv_file_cleanup/Resources/Public/Icons/module-cleanup.svg',
            'labels' => 'LLL:EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf'
        )
    );
}
