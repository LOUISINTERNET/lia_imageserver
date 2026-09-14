<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\VersionNumberUtility;

// defined('TYPO3') or die();

// before TYPO3 v12.3
if (VersionNumberUtility::getNumericTypo3Version() < VersionNumberUtility::convertVersionNumberToInteger('12.3')) {
    $GLOBALS['TBE_STYLES']['skins']['lia_imageserver'] = [];
    $GLOBALS['TBE_STYLES']['skins']['lia_imageserver']['name'] = 'lia_imageserver';
    $GLOBALS['TBE_STYLES']['skins']['lia_imageserver']['stylesheetDirectories'] = [
        'EXT:lia_imageserver/Resources/Public/Stylesheets/',
    ];
}
