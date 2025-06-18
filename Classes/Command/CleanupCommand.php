<?php

namespace WebVision\WvFileCleanup\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\WvFileCleanup\Domain\Repository\FileRepository;

/**
The symfony commands wv_file_cleanup:cleanup and wv_file_cleanup:emptyrecycler are available.

Example of using the command controllers from CLI context:
.vendor/bin/typo3 wv_file_cleanup:cleanup --help
.vendor/bin/typo3 wv_file_cleanup:cleanup 1:/ -r --verbose
.vendor/bin/typo3 wv_file_cleanup:cleanup 1:/Redaktion/Bilder/Aktuelles/2016/ -r --verbose --dry-run
.vendor/bin/typo3  wv_file_cleanup:emptyrecycler 1:/ -a 1month --verbose
To only match *.pdf files you can set the fileNameDenyPattern to /^(?!.*\b.pdf\b)/
.vendor/bin/typo3  wv_file_cleanup:cleanup 1:/ --verbose --dry-run --file-deny-pattern='/^(?!.*\b.pdf\b)/'

It is recommended to use the commands in a CLI context, but they can also be setup in the scheduler as scheduler tasks.

Options
You can configure an fileNameDenyPattern that holds a regular expression that is used to check the filename against. If the pattern matches the file is excluded from the cleanup and also not visible in het BE module.
Default value is /index.html/i so all index.html files are excluded and can be adjusted in the extension configuration (see extension manager).
The value can also be overwritten in the command controller (and scheduler task).
 */

/**
 * Class CleanupCommand
 *
 * @package WebVision\WvFileCleanup\Command
 */
class CleanupCommand extends Command
{
    /**
     * @var FileRepository
     */
    protected FileRepository $fileRepository;

    public function __construct(FileRepository $fileRepository)
    {
        parent::__construct();
        $this->fileRepository = $fileRepository;
    }

    /**
     * Configuring the command options
     */
    protected function configure(): void
    {
        $this->setDescription('Cleanup un-used files')
            ->addArgument(
                'folder',
                InputArgument::REQUIRED,
                'Combined identifier of root folder (example: 1:/)'
            )
            ->addOption(
                'age',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Only files not in use since (when known) and created/uploaded before (strtotime string)',
                '1 month'
            )
            ->addOption(
                'file-deny-pattern',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Regular expression to match (preg_match) the filename against. Matching files are excluded from cleanup. Example to match only *.pdf: /^(?!.*\b.pdf\b)/',
                '/index.html/i'
            )
            ->addOption(
                'path-deny-pattern',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Regular expression to match (preg_match) the filepath against. Matching files are excluded from cleanup. Example to exclude "files" and "downloads" directory: /(files|downloads)/i'
            )
            ->addOption(
                'recursive',
                'r',
                InputOption::VALUE_NONE,
                'Search sub folders of $folder recursive'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Dry run do not really move files to recycler folder'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws InsufficientFolderAccessPermissionsException
     * @throws ResourceDoesNotExistException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $age = $age = strtotime('-' . $input->getOption('age'));
        $recursive = $input->getOption('recursive');
        $dryRun = $input->getOption('dry-run');
        $fileDenyPattern = $input->getOption('file-deny-pattern');
        $pathDenyPattern = $input->getOption('path-deny-pattern');

        if ($age === false) {
            $io->error('Value of \'age\' isn\'t recognized. See http://php.net/manual/en/function.strtotime.php for possible values');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('DryRun option active');
        }

        [$storageUid, $folderPath] = explode(':', $input->getArgument('folder'), 2);

        // Fallback for when only a path is given
        if (!is_numeric($storageUid)) {
            $storageUid = 1;
            $folderPath = $input->getArgument('folder');
        }

        $storage = $resourceFactory->getStorageObject($storageUid);
        $evaluatePermissions = $storage->getEvaluatePermissions();
        // Temporary disable permission checks
        $storage->setEvaluatePermissions(false);

        if (!$storage->hasFolder($folderPath)) {
            $io->warning('Unknown folder [' . $folderPath . '] in storage ' . $storageUid);
            // Restore permissions
            $storage->setEvaluatePermissions($evaluatePermissions);
            return 1;
        }
        $folderObject = $storage->getFolder($folderPath);

        $files = $this->fileRepository->findUnusedFile($folderObject, $recursive, $fileDenyPattern, $pathDenyPattern);

        if ($output->isVerbose()) {
            $io->newLine();
            $io->writeln('Found ' . count($files) . ' un-used files');
            $io->newLine();
        }

        foreach ($files as $key => $file) {
            $fileAge = $file->getLastReferenceTimestamp() ?: $file->getResource()->getModificationTime();
            if ($output->isVerbose()) {
                $io->writeln('File: ' . $file->getPublicUrl() . ': ' . date('Ymd', $fileAge) . ' < ' . date('Ymd', $age));
            }
            // Remove all files "newer" than age from our array
            if ($fileAge > $age) {
                unset($files[$key]);
            }
        }

        if ($output->isVerbose()) {
            $io->newLine();
            $io->writeln('Found ' . count($files) . ' un-used files older than ' . date('Ymd', $age));
            $io->newLine();
        }

        if (!$dryRun) {
            $movedFilesCount = 0;
            foreach ($files as $fileFacade) {
                try {
                    /** @var File $file */
                    $file = $fileFacade->getResource();
                    $folder = $file->getParentFolder();

                    if (!$folder->hasFolder('_recycler_')) {
                        $recycler = $folder
                            ->getStorage()
                            ->createFolder('_recycler_', $folder);
                    } else {
                        $recycler = $folder->getSubfolder('_recycler_');
                    }
                    $file->moveTo($recycler);
                    $movedFilesCount++;
                } catch (\Exception $e) {
                }
            }
            $io->writeln('Moved ' . $movedFilesCount . ' file(s) to recycler folders');
        }

        // Restore permissions
        $storage->setEvaluatePermissions($evaluatePermissions);

        $io->success('All done!');
        return Command::SUCCESS;
    }
}
