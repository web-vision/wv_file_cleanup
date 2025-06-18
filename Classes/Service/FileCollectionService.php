<?php

namespace WebVision\WvFileCleanup\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileCollectionRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileCollectionService
{
    /**
     * @var FileCollectionRepository
     */
    protected $fileCollectionRepository;

    /**
     * Keeps the file uids of all files which are used in file collections of the type "folder" or "category"
     *
     * @var array
     */
    protected $fileUids = [];

    public function __construct()
    {
        $this->fileCollectionRepository = GeneralUtility::makeInstance(FileCollectionRepository::class);
    }

    /**
     * @throws ResourceDoesNotExistException
     */
    public function initialize(int $storage, string $folder): void
    {
        $fileCollectionUids = array_unique(
            array_merge(
                $this->getFileCollectionUidsByStorageAndFolder($storage, $folder),
                $this->getFileCollectionUidsForCategoryCollections()
            )
        );

        foreach ($fileCollectionUids as $collectionUid) {
            $fileCollection = $this->fileCollectionRepository->findByUid($collectionUid);
            $fileCollection->loadContents();

            $fileUids = [];
            /** @var File $file */
            foreach ($fileCollection->getItems() as $file) {
                $fileUids[] = $file->getUid();
            }

            $this->fileUids = array_unique(array_merge($this->fileUids, $fileUids));
        }
    }

    public function isFileCollectionFile(File $file): bool
    {
        return in_array($file->getUid(), $this->fileUids, true);
    }

    /**
     * @return array<int, int>
     */
    private function getFileCollectionUidsByStorageAndFolder(
        int $storage,
        string $folder
    ): array {

        $typo3Version = (new Typo3Version)->getMajorVersion();

        if ($typo3Version >= 12) {
            $queryResult = $this->getFilesLTS12($storage, $folder);
        } else {
            $queryResult = $this->getFilesLTS11($storage, $folder);
        }

        $result = [];
        foreach ($queryResult as $record) {
            $result[] = $record['uid'];
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function getFileCollectionUidsForCategoryCollections(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connection->getQueryBuilderForTable('sys_file_collection');
        $queryResult = $queryBuilder
            ->select('uid')
            ->from('sys_file_collection')
            ->where(
                $queryBuilder->expr()->eq(
                    'type',
                    $queryBuilder->createNamedParameter('category', Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($queryResult as $record) {
            $result[] = $record['uid'];
        }

        return $result;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function getFilesLTS12($storage, $folder): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connection->getQueryBuilderForTable('sys_file_collection');
        $queryResult = $queryBuilder
            ->select('uid')
            ->from('sys_file_collection')
            ->where(
                $queryBuilder->expr()->eq(
                    'type',
                    $queryBuilder->createNamedParameter('folder', \Doctrine\DBAL\ParameterType::STRING)
                ),
                $queryBuilder->expr()->neq(
                    'folder_identifier',
                    $queryBuilder->createNamedParameter($storage . ':' . $folder, \Doctrine\DBAL\ParameterType::STRING)
                ),
                $queryBuilder->expr()->like(
                    'folder_identifier',
                    $queryBuilder->createNamedParameter(
                        $queryBuilder->escapeLikeWildcards($folder) . '%',
                        \Doctrine\DBAL\ParameterType::STRING
                    )
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
            return $queryResult;

    }
    /**
     * @return array<mixed, mixed>
     */
    private function getFilesLTS11($storage, $folder): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connection->getQueryBuilderForTable('sys_file_collection');
        $queryResult = $queryBuilder
            ->select('uid')
            ->from('sys_file_collection')
            ->where(
                $queryBuilder->expr()->eq(
                    'type',
                    $queryBuilder->createNamedParameter('folder', Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'storage',
                    $queryBuilder->createNamedParameter($storage, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->neq(
                    'folder',
                    $queryBuilder->createNamedParameter('', Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->like(
                    'folder',
                    $queryBuilder->createNamedParameter(
                        $queryBuilder->escapeLikeWildcards($folder) . '%',
                        Connection::PARAM_STR
                    )
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
        return $queryResult;
    }
}

