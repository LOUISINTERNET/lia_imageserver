<?php

declare(strict_types=1);

defined('TYPO3') or die();

// since TYPO3 v12.3
// is ignored in older ersions
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['lia_imageserver'] = 'EXT:lia_imageserver/Resources/Public/Stylesheets/';

// ImageService override moved to Configuration/Services.yaml

// File and ResourceStorage are data objects created via ResourceFactory, not DI container.
// SYS/Objects is the only mechanism to override them. Deprecated in v14, plan removal for v15.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\File::class] = [
    'className' => \LIA\LiaImageserver\Domain\Model\File::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\ResourceStorage::class] = [
    'className' => \LIA\LiaImageserver\Domain\Model\ResourceStorage::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['lim'][] = 'LIA\\LiaImageserver\\ViewHelpers';
