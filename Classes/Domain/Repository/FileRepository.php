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
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
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
    protected $queryBuilder = null;

    /**
     * LEGACY CODE
     * @var
     */
    protected $databaseConnection = null;

    /**
     * FileRepository constructor
     */
    public function __construct()
    {
        $configuration = [];
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['wv_file_cleanup'])) {
            $configuration = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['wv_file_cleanup']);
        }
        if (!empty($configuration['fileNameDenyPattern'])) {
            $this->fileNameDenyPattern = $configuration['fileNameDenyPattern'];
        }
        $this->initDatabaseConnection();
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
        if ($this->queryBuilder) {
            // sys_refindex
            $queryBuilder_1 = $this->queryBuilder->getQueryBuilderForTable('sys_refindex');
            $res_1 = $queryBuilder_1
                //->select('recuid,count(*) as count_files')
                ->count('recuid')
                ->from('sys_refindex')
                ->where(
                    $queryBuilder_1->expr()->eq('ref_table', '\'sys_file\''),
                    $queryBuilder_1->expr()->eq('ref_uid', (int)$file->getUid()),
                    $queryBuilder_1->expr()->eq('deleted', 0),
                    $queryBuilder_1->expr()->neq('tablename', '\'sys_file_metadata\'')
                )
                ->execute();
            $refIndexCount = $res_1->fetch()['COUNT(`recuid`)'];

            // sys_file_reference
            $queryBuilder_2 = $this->queryBuilder->getQueryBuilderForTable('sys_file_reference');
            $res_2 = $queryBuilder_2
                //->select('recuid,count(*) as count_files')
                ->count('uid')
                ->from('sys_file_reference')
                ->where(
                    $queryBuilder_2->expr()->eq('table_local', '\'sys_file\''),
                    $queryBuilder_2->expr()->eq('uid_local', (int)$file->getUid()),
                    $queryBuilder_2->expr()->eq('deleted', 0)
                )
                ->execute();
            $fileReferenceCount = $res_2->fetch()['COUNT(`uid`)'];

        } elseif ($this->databaseConnection) {
            // LEGACY CODE
            
            // sys_refindex
            $refIndexCount = $this->databaseConnection->exec_SELECTcountRows(
                'recuid',
                'sys_refindex',
                'ref_table=\'sys_file\''
                . ' AND ref_uid=' . (int)$file->getUid()
                . ' AND deleted=0'
                . ' AND tablename != \'sys_file_metadata\''
            );

            // sys_file_reference
            $fileReferenceCount = $this->databaseConnection->exec_SELECTcountRows(
                'uid',
                'sys_file_reference',
                'table_local=\'sys_file\''
                . ' AND uid_local=' . (int)$file->getUid()
                . ' AND deleted=0'
            );
        }
        return max((int)$refIndexCount, (int)$fileReferenceCount);
    }

    /**
     * Get timestamp when file was last moved to another folder
     *
     * @param File $file
     * @return int
     */
    public function getLastMove(File $file)
    {
        if ($this->queryBuilder) {
            $queryBuilder_1 = $this->queryBuilder->getQueryBuilderForTable('sys_file');
            $res = $queryBuilder_1
                ->select('last_move')
                ->from('sys_file')
                ->where(
                    $queryBuilder_1->expr()->eq('uid', (int)$file->getUid())
                )
                ->execute();
            $row = $res->fetch();
        } elseif ($this->databaseConnection) {
            // LEGACY CODE
            $row = $this->databaseConnection->exec_SELECTgetSingleRow(
                'last_move',
                'sys_file',
                'uid=' . $file->getUid()
            );
        }
        return $row ? $row['last_move'] : 0;
    }

    /**
     * @return void
     */
    protected function initDatabaseConnection()
    {
        if (class_exists('\TYPO3\CMS\Core\Database\ConnectionPool')) {
            $this->queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
        } elseif ($GLOBALS['TYPO3_DB']) {
            // LEGACY CODE
            $this->databaseConnection = $GLOBALS['TYPO3_DB'];
        }
    }
}
