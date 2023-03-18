<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

defined('TYPO3_MODE') || die();

use WebVision\WvFileCleanup\Controller\CleanupController;

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'WebVision.WvFileCleanup',
    'file',
    'cleanup',
    '',
    [
        CleanupController::class => 'index, cleanup',
    ],
    [
        'access' => 'user,group',
        'workspaces' => 'online,custom',
        'icon' => 'EXT:wv_file_cleanup/Resources/Public/Icons/module-cleanup.svg',
        'labels' => 'LLL:EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf',
    ]
);
