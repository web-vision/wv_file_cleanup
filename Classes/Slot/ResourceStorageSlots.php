<?php
namespace WebVision\WvFileCleanup\Slot;

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
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FolderInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ResourceStorage
 *
 * @author Frans Saris <t3ext@beech.it>
 */
class ResourceStorageSlots implements SingletonInterface
{
    /**
     * Post file move signal
     *
     * @param FileInterface $file
     * @param Folder $targetFolder
     * @param FolderInterface $originalFolder
     */
    public function postFileMove(FileInterface $file, Folder $targetFolder, FolderInterface $originalFolder)
    {
        if ($file instanceof File) {
            $queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
                ->getQueryBuilderForTable('sys_file');
            $queryBuilder->update('sys_file')
                ->where(
                    $queryBuilder->expr()->eq('uid', (int)$file->getUid())
                )
                ->set('last_move', time())
                ->execute();
        }
    }
}
