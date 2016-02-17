<?php
namespace WebVision\WvFileCleanup\Command;

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
use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use WebVision\WvFileCleanup\FileFacade;

/**
 * Class FileCommandController
 */
class FileCommandController extends CommandController
{

    /**
     * @var \WebVision\WvFileCleanup\Domain\Repository\FileRepository
     * @inject
     */
    protected $fileRepository;

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceFactory
     * @inject
     */
    protected $resourceFactory;

    /**
     * Cleanup un-used files
     *
     * Moves all files uploaded before given timestamp to the correct _recycler_ folder
     * If the last known usage is known that timestamp is used as age of the file
     *
     * @param string $folder Combined identifier of root folder (example: 1:/)
     * @param string $age Only files not in use since (when known) and created/uploaded before (strtotime string)
     * @param bool $recursive Search sub folders of $folder recursive
     * @param bool $verbose Output some extra debug input
     * @param bool $dryRun Dry run do not really move files to recycler folder
     * @param string $fileDenyPattern Regular expression to match (preg_match) the filename against. Matching files are excluded from cleanup. Example to match only *.pdf: /^(?!.*\b.pdf\b)/
     */
    public function cleanupCommand($folder, $age = '1 month', $recursive = true, $verbose = false, $dryRun = false, $fileDenyPattern = '/index.html/i')
    {
        $age = strtotime('-' . $age);

        if ($age === false) {
            $this->outputLine('Value of \'age\' isn\'t recognized. See http://php.net/manual/en/function.strtotime.php for possible values');
            return;
        }

        list($storageUid, $folderPath) = explode(':', $folder, 2);

        // Fallback for when only a path is given
        if (!is_numeric($storageUid)) {
            $storageUid = 1;
            $folderPath = $folder;
        }

        $storage = $this->resourceFactory->getStorageObject($storageUid);
        $evaluatePermissions = $storage->getEvaluatePermissions();
        // Temporary disable permission checks
        $storage->setEvaluatePermissions(false);

        if (!$storage->hasFolder($folderPath)) {
            $this->outputLine('Unknown folder [' . $folderPath . '] in storage ' . $storageUid);
            // Restore permissions
            $storage->setEvaluatePermissions($evaluatePermissions);
            return;
        }
        $folderObject = $storage->getFolder($folderPath);

        $files = $this->fileRepository->findUnusedFile($folderObject, $recursive, $fileDenyPattern);

        if ($verbose) {
            $this->outputLine();
            $this->outputLine('Found ' . count($files) . ' un-used files');
            $this->outputLine();
        }

        /** @var FileFacade $file */
        foreach ($files as $key => $file) {
            $fileAge = $file->getLastReferenceTimestamp() ?: $file->getResource()->getModificationTime();
            if ($verbose) {
                $this->outputLine('File: ' . $file->getName() . ': ' . date('Ymd', $fileAge) . ' < ' . date('Ymd', $age));
            }
            // Remove all files "newer" then age from our array
            if ($fileAge > $age) {
                unset($files[$key]);
            }
        }
        if ($verbose) {
            $this->outputLine();
            $this->outputLine('Found ' . count($files) . ' un-used files older then ' . date('Ymd', $age));
            $this->outputLine();
        }

        if (!$dryRun) {
            $movedFilesCount = 0;
            foreach ($files as $fileFacade) {
                try {
                    $file = $fileFacade->getResource();
                    $folder = $file->getParentFolder();

                    if (!$folder->hasFolder('_recycler_')) {
                        $recycler = $folder->getStorage()->createFolder('_recycler_', $folder);
                    } else {
                        $recycler = $folder->getSubfolder('_recycler_');
                    }
                    $file->moveTo($recycler);
                    $movedFilesCount++;
                } catch (\Exception $e) {
                }
            }
            $this->outputLine('Moved ' . $movedFilesCount . ' file(s) to recycler folders');
        }

        // Restore permissions
        $storage->setEvaluatePermissions($evaluatePermissions);
    }

    /**
     * Empty recycler folders
     *
     * Permanently delete files from recycler folders
     *
     * @param string $folder Combined identifier of root folder (example: 1:/)
     * @param string $age Only files that are in recycler folder since ... (strtotime string)
     * @param bool $recursive Search sub folders of $folder recursive
     * @param bool $verbose Output some extra debug input
     * @param bool $dryRun Dry run do not really delete files
     * @param string $fileDenyPattern Regular expression to match (preg_match) the filename against. Matching files are excluded from cleanup. Example to match only *.pdf: /^(?!.*\b.pdf\b)/
     */
    public function emptyRecyclerCommand($folder, $age = '1 month', $recursive = true, $verbose = false, $dryRun = false, $fileDenyPattern = '/index.html/i')
    {
        $age = strtotime('-' . $age);

        if ($age === false) {
            $this->outputLine('Value of \'age\' isn\'t recognized. See http://php.net/manual/en/function.strtotime.php for possible values');
            return;
        }

        list($storageUid, $folderPath) = explode(':', $folder, 2);

        // Fallback for when only a path is given
        if (!is_numeric($storageUid)) {
            $storageUid = 1;
            $folderPath = $folder;
        }

        $storage = $this->resourceFactory->getStorageObject($storageUid);
        $evaluatePermissions = $storage->getEvaluatePermissions();
        // Temporary disable permission checks
        $storage->setEvaluatePermissions(false);

        if (!$storage->hasFolder($folderPath)) {
            $this->outputLine('Unknown folder [' . $folderPath . '] in storage ' . $storageUid);
            // Restore permissions
            $storage->setEvaluatePermissions($evaluatePermissions);
            return;
        }
        $folderObject = $storage->getFolder($folderPath);

        $files = $this->fileRepository->findAllFilesInRecyclerFolder($folderObject, $recursive, $fileDenyPattern);

        if ($verbose) {
            $this->outputLine();
            $this->outputLine('Found ' . count($files) . ' files');
            $this->outputLine();
        }

        /** @var File $file */
        foreach ($files as $key => $file) {
            $fileAge = $file->getModificationTime();
            if ($verbose) {
                $this->outputLine('File: ' . $file->getParentFolder()->getReadablePath() . $file->getName() . ': ' . date('Ymd', $fileAge) . ' < ' . date('Ymd', $age));
            }
            // Remove all files "newer" then age from our array
            if ($fileAge > $age) {
                unset($files[$key]);
            }
        }
        if ($verbose) {
            $this->outputLine();
            $this->outputLine('Found ' . count($files) . ' files longer then ' . date('Ymd', $age) . ' in recycler folder');
            $this->outputLine();
        }

        if (!$dryRun) {
            $deletedFilesCount = 0;
            /** @var File $file */
            foreach ($files as $file) {
                try {
                    $file->delete();
                    $deletedFilesCount++;
                } catch (FileOperationErrorException $e) {
                    $this->outputLine('Failed to remove ' . $file->getName() . ' [' . $e->getMessage() . ']');
                }
            }
            $this->outputLine('Deleted ' . $deletedFilesCount . ' file(s) from recycler folders');
        }

        // Restore permissions
        $storage->setEvaluatePermissions($evaluatePermissions);
    }
}
