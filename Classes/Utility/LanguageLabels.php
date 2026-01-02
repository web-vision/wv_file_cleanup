<?php

declare(strict_types=1);

namespace WebVision\WvFileCleanup\Utility;

use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Provides TYPO3 version related label definitions to be
 * TYPO3 version agnostic.
 *
 * @internal for EXT:wv_file_cleanup internal usage and not part of public API.
 */
final class LanguageLabels
{
    private Typo3Version $typo3Version;

    public function __construct(
        Typo3Version $typo3Version
    ) {
        $this->typo3Version = $typo3Version;
    }

    /**
     * @return string[]
     */
    public function getControllerLanguageLabelFiles(): array
    {
        return [
            'EXT:core/Resources/Private/Language/locallang_core.xlf',
            'EXT:core/Resources/Private/Language/locallang_misc.xlf',
            'EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf',
            'EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf',
        ];
    }

    public function getDisplayThumbsLabel(): string
    {
        if ($this->typo3Version->getMajorVersion() < 12) {
            // 'LLL:EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf:displayThumbs'
            return 'displayThumbs';
        }
        // LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.view.showThumbnails'
        return 'labels.view.showThumbnails';
    }

    public function searchFoldersRecursiveLabel(): string
    {
        // 'LLL:EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf:search_folders_recursive'
        return 'search_folders_recursive';
    }

    public function oneLevelUpLabel(): string
    {
        // 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.upOneLevel'
        return 'labels.upOneLevel';
    }

    public function missingFolderPermissionsMessage(): string
    {
        //  'LLL:EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf:missingFolderPermissionsMessage'
        return 'missingFolderPermissionsMessage';
    }

    public function missingFolderPermissionsTitle(): string
    {
        // 'LLL:EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf:missingFolderPermissionsTitle'
        return 'missingFolderPermissionsTitle';
    }

    public function folderNotFoundMessage(): string
    {
        // 'LLL:EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf:folderNotFoundMessage'
        return 'folderNotFoundMessage';
    }

    public function folderNotFoundTitle(): string
    {
        // 'LLL:EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf:folderNotFoundTitle'
        return 'folderNotFoundTitle';
    }
}
