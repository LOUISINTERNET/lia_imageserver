<?php

/**
 * Fractor configuration for TYPO3 v13+v14 dual compatibility upgrade
 *
 * Handles non-PHP file migrations: FlexForms, TypoScript, YAML, Fluid
 *
 * Usage:
 *   cd /path/to/extension && ./vendor/bin/fractor process --dry-run  # Preview
 *   cd /path/to/extension && ./vendor/bin/fractor process            # Apply
 *
 * NOTE: Fractor auto-discovers fractor.php in CWD. No --config flag supported.
 *
 * Requires: composer require --dev a9f/typo3-fractor:^0.5
 */

declare(strict_types=1);

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\FractorTypoScript\Configuration\TypoScriptProcessorOption;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;

return FractorConfiguration::configure()
    ->withPaths([
        __DIR__ . '/Configuration',
        __DIR__ . '/Resources',
    ])
    ->withSkip([
        __DIR__ . '/.Build',
        __DIR__ . '/vendor',
    ])
    ->withSets([
        // Dual v13+v14: use UP_TO_TYPO3_13 only (v14 rules introduce v14-only syntax)
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ])
    ->withOptions([
        TypoScriptProcessorOption::INDENT_CONDITIONS => true,
    ]);
