<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addStaticFile('lia_imageserver', 'Configuration/TypoScript', 'LIA Imageserver');
