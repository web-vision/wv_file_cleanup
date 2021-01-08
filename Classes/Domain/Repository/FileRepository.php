<?php
namespace WebVision\WvFileCleanup\Domain\Repository;

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

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WebVision\WvFileCleanup\FileFacade;

/**
 * Class FileRepository
 *
 * @author Frans Saris <t3ext@beech.it>
 */
class FileRepository implements SingletonInterface
{
    /**
     * @var string
     */
    protected $fileNameDenyPattern = '';

    /**
     * @var \TYPO3\CMS\Core\Database\ConnectionPool
     */
    protected $connection = null;

    /**
     * FileRepository constructor
     */
    public function __construct()
    {
        $this->connection = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
        $this->fileNameDenyPattern = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('wv_file_cleanup', 'fileNameDenyPattern');
    }

    /**
     * Find all unused files
     *
     * @param Folder $folder
     * @param bool $recursive
     * @param string $fileDenyPattern
     *
     * @return \WebVision\WvFileCleanup\FileFacade[]
     */
    public function findUnusedFile(Folder $folder, $recursive = true, $fileDenyPattern = null)
    {
        $return = [];
        $files = $folder->getFiles(0, 0, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, $recursive);
        if ($fileDenyPattern === null) {
            $fileDenyPattern = $this->fileNameDenyPattern;
        }

        // filer out all files in _recycler_ and _processed_ folder + check fileDenyPattern
        $files = array_filter($files, function (FileInterface $file) use ($fileDenyPattern) {
            if ($file->getParentFolder()->getName() === '_recycler_' || $file instanceof ProcessedFile) {
                return false;
            }
            if (!empty($fileDenyPattern) && preg_match($fileDenyPattern, $file->getName())) {
                return false;
            }
            return true;
        });

        // filter out all files with references
        $files = array_filter($files, function (File $file) {
            return $this->getReferenceCount($file) === 0;
        });

        foreach ($files as $file) {
            $return[] = new FileFacade($file);
        }

        return $return;
    }

    /**
     * Find all files in _recycler_ folder(s)
     *
     * @param Folder $folder
     * @param bool $recursive
     * @param string $fileDenyPattern
     *
     * @return File[]
     */
    public function findAllFilesInRecyclerFolder(Folder $folder, $recursive = true, $fileDenyPattern = null)
    {
        if ($fileDenyPattern === null) {
            $fileDenyPattern = $this->fileNameDenyPattern;
        }
        $folders = [];
        $files = [];
        if (!$recursive) {
            if ($folder->hasFolder('_recycler_')) {
                $folders[] = $folder->getSubfolder('_recycler_');
            }
        } else {
            foreach ($folder->getStorage()->getFoldersInFolder($folder, 0, 0, true, true) as $subFolder) {
                if ($subFolder->getName() === '_recycler_') {
                    $folders[] = $subFolder;
                }
            }
        }

        /** @var Folder $folder */
        foreach ($folders as $folder) {
            $files += $folder->getFiles();
        }

        // Check fileDenyPattern
        if (!empty($fileDenyPattern)) {
            $files = array_filter($files, function (FileInterface $file) use ($fileDenyPattern) {
                if (preg_match($fileDenyPattern, $file->getName())) {
                    return false;
                }
                return true;
            });
        }

        return $files;
    }

    /**
     * Get count of current references
     *
     * @param File $file
     *
     * @return int
     */
    public function getReferenceCount(File $file)
    {
        // sys_refindex
        $queryBuilder1 = $this->connection->getQueryBuilderForTable('sys_refindex');
        $res1 = $queryBuilder1
            ->count('recuid')
            ->from('sys_refindex')
            ->where(
                $queryBuilder1->expr()->eq(
                    'ref_table',
                    $queryBuilder1->createNamedParameter('sys_file', Connection::PARAM_STR)
                ),
                $queryBuilder1->expr()->eq(
                    'ref_uid',
                    $queryBuilder1->createNamedParameter($file->getUid(), Connection::PARAM_INT)
                ),
                $queryBuilder1->expr()->eq(
                    'deleted',
                    $queryBuilder1->createNamedParameter(0, Connection::PARAM_INT)
                ),
                $queryBuilder1->expr()->neq(
                    'tablename',
                    $queryBuilder1->createNamedParameter('sys_file_metadata', Connection::PARAM_STR)
                )
            )
            ->execute();
        $refIndexCount = (int)$res1->fetchColumn(0);

        // sys_file_reference
        $queryBuilder2 = $this->connection->getQueryBuilderForTable('sys_file_reference');
        $res2 = $queryBuilder2
            ->count('uid')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder2->expr()->eq(
                    'table_local',
                    $queryBuilder2->createNamedParameter('sys_file', Connection::PARAM_STR)
                ),
                $queryBuilder2->expr()->eq(
                    'uid_local',
                    $queryBuilder2->createNamedParameter($file->getUid(), Connection::PARAM_INT)
                ),
                $queryBuilder2->expr()->eq(
                    'deleted',
                    $queryBuilder2->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
            ->execute();
        $fileReferenceCount = (int)$res2->fetchColumn(0);

        return max($refIndexCount, $fileReferenceCount);
    }

    /**
     * Get timestamp when file was last moved to another folder
     *
     * @param File $file
     * @return int
     */
    public function getLastMove(File $file)
    {
        $queryBuilder = $this->connection->getQueryBuilderForTable('sys_file');
        $res = $queryBuilder
            ->select('last_move')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('uid', (int)$file->getUid())
            )
            ->execute();
        $row = $res->fetch();

        return $row ? $row['last_move'] : 0;
    }
}
