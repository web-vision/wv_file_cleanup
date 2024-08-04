<?php

declare(strict_types=1);

namespace WebVision\WvFileCleanup;

use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\WvFileCleanup\Domain\Repository\FileRepository;

/**
 * Provide factory to create FileFacade instances and to be used only in {@see FileRepository}.
 *
 * @internal and not part of public API.
 */
final class FileFacadeFactory
{
    private IconFactory $iconFactory;

    public function __construct(
        IconFactory $iconFactory
    ) {
        $this->iconFactory = $iconFactory;
    }

    /**
     * Create new not shared FileFacade instance for `$file`.
     */
    public function forFileInterface(FileInterface $file): FileFacade
    {
        return GeneralUtility::makeInstance(
            FileFacade::class,
            $file,
            $this->iconFactory->getIconForResource($file, Icon::SIZE_SMALL)
        );
    }
}
