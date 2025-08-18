<?php

namespace WebVision\WvFileCleanup;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileFacade
 *
 * This class is meant to be a wrapper for Resource\File objects, which do not
 * provide necessary methods needed in the views of the filelist extension. It
 * is a first approach to get rid of the FileList class that mixes up PHP,
 * HTML and JavaScript.
 */
class FileFacade
{
    /**
     * Cache of last known reference timestamp for each file
     *
     * @var array
     */
    protected static $lastReferenceTimestamps = [];

    /**
     * @var \TYPO3\CMS\Core\Database\ConnectionPool
     */
    protected $queryBuilder;

    /**
     * LEGACY CODE
     * @var
     */
    protected $databaseConnection;

    public function __construct(
        private readonly FileInterface $resource,
        private readonly IconFactory $iconFactory
    )
    {
       # $this->resource = $resource;
       # $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        $title = htmlspecialchars($this->resource->getName() . ' [' . (int)$this->resource->getProperty('uid') . ']');
        return '<span title="' . $title . '">' . $this->iconFactory->getIconForResource($this->resource, Icon::SIZE_SMALL) . '</span>';
    }

    /**
     * @return FileInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return bool
     */
    public function getIsEditable()
    {
        return $this->getIsWritable()
            && GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'], $this->resource->getExtension());
    }

    /**
     * @return bool
     */
    public function getIsMetadataEditable()
    {
        return $this->resource->isIndexed() && $this->getIsWritable() && $this->getBackendUser()->check('tables_modify', 'sys_file_metadata');
    }

    /**
     * @return int
     */
    public function getMetadataUid()
    {
        $uid = 0;
        $method = '_getMetadata';
        if (is_callable([$this->resource, $method])) {
            $metadata = call_user_func([$this->resource, $method]);

            if (isset($metadata['uid'])) {
                $uid = (int)$metadata['uid'];
            }
        }

        return $uid;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->resource->getName();
    }

    /**
     * @return string
     */
    public function getPath()
    {
        $method = 'getReadablePath';
        if (is_callable([$this->resource->getParentFolder(), $method])) {
            return call_user_func([$this->resource->getParentFolder(), $method]);
        }

        return '';
    }

    /**
     * @return string
     */
    public function getPublicUrl()
    {
        return $this->resource->getPublicUrl(true);
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return strtoupper($this->resource->getExtension());
    }

    /**
     * @return string
     */
    public function getLastModified()
    {
        return BackendUtility::date($this->resource->getModificationTime());
    }

    /**
     * @return string
     */
    public function getSize()
    {
        return GeneralUtility::formatSize($this->resource->getSize(), $this->getLanguageService()->getLL('byteSizeUnits'));
    }

    /**
     * @return bool
     */
    public function getIsReadable()
    {
        $method = 'checkActionPermission';
        if (is_callable([$this->resource, $method])) {
            return call_user_func_array([$this->resource, $method], ['read']);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function getIsWritable()
    {
        $method = 'checkActionPermission';
        if (is_callable([$this->resource, $method])) {
            return call_user_func_array([$this->resource, $method], ['write']);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function getIsReplaceable()
    {
        $method = 'checkActionPermission';
        if (is_callable([$this->resource, $method])) {
            return call_user_func_array([$this->resource, $method], ['replace']);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function getIsRenamable()
    {
        $method = 'checkActionPermission';
        if (is_callable([$this->resource, $method])) {
            return call_user_func_array([$this->resource, $method], ['rename']);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function getIsDeletable()
    {
        $method = 'checkActionPermission';
        if (is_callable([$this->resource, $method])) {
            return call_user_func_array([$this->resource, $method], ['delete']);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function getIsImage()
    {
        return GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], strtolower($this->getExtension()));
    }

    /**
     * Fetch, cache and return the number of references of a file
     *
     * @return int
     */
    public function getLastReferenceTimestamp()
    {
        $uid = (int)$this->resource->getProperty('uid');

        if ($uid <= 0) {
            return 0;
        }

        if (!isset(self::$lastReferenceTimestamps[$uid])) {
            self::$lastReferenceTimestamps[$uid] = 0;
            $row = null;

            if ($this->queryBuilder) {
                $queryBuilder = $this->queryBuilder->getQueryBuilderForTable('sys_file_reference');
                $result = $queryBuilder
                    ->select('tstamp')
                    ->from('sys_file_reference')
                    ->where(
                        $queryBuilder->expr()->eq('uid_local', (int)$this->resource->getProperty('uid')),
                        $queryBuilder->expr()->eq('deleted', 1)
                    )
                    ->orderBy('tstamp DESC')
                    ->execute();
                $row = $result->fetchAllAssociative();
            }

            if (is_array($row)) {
                self::$lastReferenceTimestamps[$uid] = $row['tstamp'];
            }
        }

        return self::$lastReferenceTimestamps[$uid];
    }

    /**
     * @param string $method
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (is_callable([$this->resource, $method])) {
            return call_user_func_array([$this->resource, $method], $arguments);
        }

        return null;
    }

    protected function initDatabaseConnection()
    {
           $this->queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
    }

    /**
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return \TYPO3\CMS\Core\Localization\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
