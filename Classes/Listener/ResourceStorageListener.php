<?php

namespace WebVision\WvFileCleanup\Listener;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ResourceStorageListener implements SingletonInterface
{
    public function postFileMove(AfterFileMovedEvent $event): void
    {
        $file = $event->getFile();
        if ($file instanceof File) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
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
