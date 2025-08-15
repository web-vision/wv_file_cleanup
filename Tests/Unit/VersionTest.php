<?php

declare(strict_types=1);

namespace WebVision\WvFileCleanup\Tests\Unit;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @internal
 */
final class VersionTest extends UnitTestCase
{
    public static function core11DataSets(): \Generator
    {
        $t3v = new Typo3Version();
        yield 'major version is 11' => [
            'label' => 'major version is 11',
            'expected' => 11,
            'value' => $t3v->getMajorVersion(),
        ];
        yield 'branch is 11.5' => [
            'label' => 'branch is 11.5',
            'expected' => '11.5',
            'value' => $t3v->getBranch(),
        ];
        yield 'version is greater or equal than 11.5.0' => [
            'label' => 'version is greater or equal than 11.5.0',
            'expected' => true,
            'value' => version_compare($t3v->getVersion(), '11.5.0', '>='),
        ];
        yield 'version is lower than 11.6.0' => [
            'label' => 'version is lower than 11.6.0',
            'expected' => true,
            'value' => version_compare($t3v->getVersion(), '11.6.0', '<'),
        ];
    }

    /**
     * @test
     * @dataProvider core11DataSets
     * @group not-core-12
     *
     * @param int|string|bool $expected
     * @param int|string|bool $value
     */
    public function verifyVersionForCore11(string $label, $expected, $value): void
    {
        $this->assertSame($expected, $value, $label);
    }

    public static function core12DataSets(): \Generator
    {
        $t3v = new Typo3Version();
        yield 'major version is 12' => [
            'label' => 'major version is 12',
            'expected' => 12,
            'value' => $t3v->getMajorVersion(),
        ];
        yield 'branch is 12.4' => [
            'label' => 'branch is 12.4',
            'expected' => '12.4',
            'value' => $t3v->getBranch(),
        ];
        yield 'version is greater or equal than 12.4.0' => [
            'label' => 'version is greater or equal than 12.4.0',
            'expected' => true,
            'value' => version_compare($t3v->getVersion(), '12.4.0', '>='),
        ];
        yield 'version is lower than 12.5.0' => [
            'label' => 'version is lower than 12.5.0',
            'expected' => true,
            'value' => version_compare($t3v->getVersion(), '12.5.0', '<'),
        ];
    }

    /**
     * @test
     * @dataProvider core12DataSets
     * @group not-core-11
     *
     * @param int|string|bool $expected
     * @param int|string|bool $value
     */
    public function verifyVersionForCore12(string $label, $expected, $value): void
    {
        $this->assertSame($expected, $value, $label);
    }
}
