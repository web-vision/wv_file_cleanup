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
            $this->getDatabaseConnection()->exec_UPDATEquery(
                'sys_file',
                'uid = ' . (int)$file->getUid(),
                [
                    'last_move' => time()
                ]
            );
        }
    }

    /**
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
