<?php

declare(strict_types=1);

namespace WebVision\WvFileCleanup\Tests\Functional;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use WebVision\WvFileCleanup\Tests\Functional\Fixtures\Functional\AbstractFunctionalTestCase;

/**
 * @internal
 */
final class ExtensionLoadedTest extends AbstractFunctionalTestCase
{
    private const ADDITIONAL_EXTENSIONS_REQUIRED_TO_BE_LOADED = [
        'backend' => 'typo3/cms-backend',
        'core' => 'typo3/cms-core',
        'extbase' => 'typo3/cms-extbase',
        'filelist' => 'typo3/cms-filelist',
        'fluid' => 'typo3/cms-fluid',
        'frontend' => 'typo3/cms-frontend',
        'install' => 'typo3/cms-install',
    ];

    /**
     * @test
     */
    public function extensionManagementUtilityIsLoadedByExtensionKeyReturnsTrue(): void
    {
        $this->assertTrue(ExtensionManagementUtility::isLoaded('wv_file_cleanup'));
    }

    /**
     * @test
     */
    public function extensionManagementUtilityIsLoadedByComposerPackageNameReturnsTrue(): void
    {
        $this->assertTrue(ExtensionManagementUtility::isLoaded('web-vision/wv_file_cleanup'));
    }

    public static function additionalExtensionRequiredToBeLoadedInTest(): array
    {
        $return = [];
        foreach (self::ADDITIONAL_EXTENSIONS_REQUIRED_TO_BE_LOADED as $extensionKey => $composerPackageName) {
            $return[] = ['identifier' => $extensionKey];
            $return[] = ['identifier' => $composerPackageName];
        }
        return $return;
    }

    /**
     * @test
     * @dataProvider additionalExtensionRequiredToBeLoadedInTest
     */
    public function ensureDependingExtensionsIsLoadedEitherByExtensionKeyOrComposerPackageName(string $identifier): void
    {
        $this->assertTrue(ExtensionManagementUtility::isLoaded($identifier));
    }
}
