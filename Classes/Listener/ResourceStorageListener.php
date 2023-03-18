<?php

namespace WebVision\WvFileCleanup\Listener;

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
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ResourceStorageListener implements SingletonInterface
{
    /**
     * @param AfterFileMovedEvent $event
     */
    public function postFileMove(AfterFileMovedEvent $event)
    {
        $file = $event->getFile();
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
