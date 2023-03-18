<?php

namespace WebVision\WvFileCleanup\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use WebVision\WvFileCleanup\Domain\Repository\FileRepository;

/**
 * Class EmptyRecyclerCommand
 */
class EmptyRecyclerCommand extends Command
{
    /**
     * @var FileRepository
     */
    protected $fileRepository;

    /**
     * @var ResourceFactory
     */
    protected $resourceFactory;

    public function injectFileRepository(FileRepository $fileRepository): void
    {
        $this->fileRepository = $fileRepository;
    }

    public function injectResourceFactory(ResourceFactory $resourceFactory): void
    {
        $this->resourceFactory = $resourceFactory;
    }

    /**
     * Configuring the command options
     */
    protected function configure(): void
    {
        $this->setDescription('Empty recycler folders')
            ->addArgument(
                'folder',
                InputArgument::REQUIRED,
                'Combined identifier of root folder (example: 1:/)'
            )
            ->addOption(
                'age',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Only files that are in recycler folder since ... (strtotime string)',
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
     * @throws InsufficientFolderAccessPermissionsException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $age = $age = strtotime('-' . $input->getOption('age'));
        $recursive = $input->getOption('recursive');
        $dryRun = $input->getOption('dry-run');
        $fileDenyPattern = $input->getOption('file-deny-pattern');

        if ($age === false) {
            $io->error('Value of \'age\' isn\'t recognized. See https://php.net/manual/en/function.strtotime.php for possible values');
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

        $storage = $this->resourceFactory->getStorageObject($storageUid);
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

        $files = $this->fileRepository->findAllFilesInRecyclerFolder($folderObject, $recursive, $fileDenyPattern);

        if ($output->isVerbose()) {
            $io->newLine();
            $io->writeln('Found ' . count($files) . ' used files');
            $io->newLine();
        }

        foreach ($files as $key => $file) {
            $fileAge = $this->fileRepository->getLastMove($file);
            // Fallback to modification time
            if (!$fileAge) {
                $fileAge = $file->getModificationTime();
            }
            if ($output->isVerbose()) {
                $io->writeln('File: ' . $file->getParentFolder()->getReadablePath() . $file->getName() . ': ' . date('Ymd', $fileAge) . ' < ' . date('Ymd', $age));
            }
            // Remove all files "newer" than age from our array
            if ($fileAge > $age) {
                unset($files[$key]);
            }
        }

        if ($output->isVerbose()) {
            $io->newLine();
            $io->writeln('Found ' . count($files) . ' files longer than ' . date('Ymd', $age) . ' in recycler folder');
            $io->newLine();
        }

        if (!$dryRun) {
            $deletedFilesCount = 0;
            foreach ($files as $file) {
                try {
                    $file->delete();
                    $deletedFilesCount++;
                } catch (FileOperationErrorException $e) {
                    $io->writeln('Failed to remove ' . $file->getName() . ' [' . $e->getMessage() . ']');
                }
            }
            $io->writeln('Deleted ' . $deletedFilesCount . ' file(s) from recycler folders');
        }

        // Restore permissions
        $storage->setEvaluatePermissions($evaluatePermissions);

        $io->success('All done!');
        return Command::SUCCESS;
    }
}
