<?php

declare(strict_types=1);

namespace WebVision\WvFileCleanup\Tests\Functional\Fixtures\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @internal
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    /**
     * @var string[]
     */
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
        'typo3/cms-filelist',
    ];

    /**
     * @var string[]
     */
    protected array $testExtensionsToLoad = [
        'web-vision/wv_file_cleanup',
    ];

    /**
     * These two internal variable track if the given test is the first test of
     * that test case. This variable is set to current calling test case class.
     * Consecutive tests then optimize and do not create a full
     * database structure again but instead just truncate all tables which
     * is much quicker.
     */
    private static string $currentTestCaseClass = '';
    private bool $isFirstTest = true;

    /**
     * @before {@see self::setUp()}
     */
    protected function beforeSetUp(): void
    {
        $this->handleIsFirstTest();
        $this->handleAdditionalExtensionsToLoad();
    }

    protected function handleIsFirstTest(): void
    {
        // See if we're the first test of this test case.
        $currentTestCaseClass = static::class;
        if (self::$currentTestCaseClass !== $currentTestCaseClass) {
            self::$currentTestCaseClass = $currentTestCaseClass;
        } else {
            $this->isFirstTest = false;
        }
    }

    protected function handleAdditionalExtensionsToLoad(): void
    {
        if ($this->isFirstTest) {
            $this->coreExtensionsToLoad = $this->modifyCoreExtensionToLoad($this->coreExtensionsToLoad);
            $this->testExtensionsToLoad = $this->modifyTestExtensionToLoad($this->testExtensionsToLoad);
        }
    }

    /**
     * Only called before first test setup. Changing extension between tests of the same TestCase is not supported.
     *
     * @param string[] $coreExtensionsToLoad
     * @return string[]
     */
    protected function modifyCoreExtensionToLoad(array $coreExtensionsToLoad): array
    {
        return $coreExtensionsToLoad;
    }

    /**
     * Only called before first test setup. Changing extension between tests of the same TestCase is not supported.
     *
     * @param string[] $testExtensionsToLoad
     * @return string[]
     */
    protected function modifyTestExtensionToLoad(array $testExtensionsToLoad): array
    {
        return $testExtensionsToLoad;
    }

    final protected function isFirstTest(): bool
    {
        return $this->isFirstTest;
    }
}
