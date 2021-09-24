<?php
namespace WebVision\WvFileCleanup\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use WebVision\WvFileCleanup\Domain\Repository\FileRepository;
use WebVision\WvFileCleanup\FileFacade;

/**
 * Class CleanupCommand
 */
class CleanupCommand extends Command
{
    /**
     * Configuring the command options
     */
    public function configure()
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $resourceFactory = $objectManager->get(ResourceFactory::class);
        $fileRepository = $objectManager->get(FileRepository::class);

        $age = $age = strtotime('-' . $input->getOption('age'));
        $recursive = $input->getOption('recursive');
        $dryRun = $input->getOption('dry-run');
        $fileDenyPattern = $input->getOption('file-deny-pattern');
        $pathDenyPattern = $input->getOption('path-deny-pattern');

        if ($age === false) {
            $io->error('Value of \'age\' isn\'t recognized. See http://php.net/manual/en/function.strtotime.php for possible values');
            return 1;
        }

        if ($dryRun) {
            $io->note('DryRun option active');
        }

        list($storageUid, $folderPath) = explode(':', $input->getArgument('folder'), 2);

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

        $files = $fileRepository->findUnusedFile($folderObject, $recursive, $fileDenyPattern, $pathDenyPattern);

        if ($output->isVerbose()) {
            $io->newLine();
            $io->writeln('Found ' . count($files) . ' un-used files');
            $io->newLine();
        }

        /** @var FileFacade $file */
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
                        $recycler = $folder->getStorage()->createFolder('_recycler_', $folder);
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
        return 0;
    }
}
