<?php

defined('TYPO3') || die();

// @todo Remove complete if block when TYPO3 v11 support is dropped. Since TYPO3 v12 this is provided with the
//       `Configuration/Backend/Modules.php` file as part of the new backend module routing and registration.
//       See: https://review.typo3.org/c/Packages/TYPO3.CMS/+/73058
//       See: https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Breaking-96733-RemovedSupportForModuleHandlingBasedOnTBE_MODULES.html
//       See: https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-96733-NewBackendModuleRegistrationAPI.html
if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 12) {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'WvFileCleanup',
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
