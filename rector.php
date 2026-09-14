<?php

/**
 * Rector configuration for TYPO3 v13+v14 dual compatibility upgrade
 *
 * Usage:
 *   ./vendor/bin/rector process --dry-run --config=/path/to/rector.php  # Preview
 *   ./vendor/bin/rector process --config=/path/to/rector.php            # Apply
 *
 * Requires: composer require --dev ssch/typo3-rector:^3.11
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\PostRector\Rector\NameImportingPostRector;
use Rector\Set\ValueObject\LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/ext_localconf.php',
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
    $rectorConfig->removeUnusedImports();

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,

        // Dual v13+v14: use UP_TO_TYPO3_13 only (v14 rules introduce v14-only APIs)
        Typo3LevelSetList::UP_TO_TYPO3_13,

        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,
    ]);

    $rectorConfig->skip([
        __DIR__ . '/ext_emconf.php',
        __DIR__ . '/.Build',
        __DIR__ . '/vendor',

        ClassPropertyAssignToConstructorPromotionRector::class,
        RemoveParentCallWithoutParentRector::class,

        NameImportingPostRector::class => [__DIR__ . '/ext_localconf.php'],
    ]);
};
