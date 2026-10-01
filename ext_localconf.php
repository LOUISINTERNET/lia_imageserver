<?php

declare(strict_types=1);

defined('TYPO3') or die();

// since TYPO3 v12.3
// is ignored in older ersions
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['lia_imageserver'] = 'EXT:lia_imageserver/Resources/Public/Stylesheets/';

// Core builds the Extbase ImageService in its ServiceProvider (Extbase\ServiceProvider::getImageService()),
// which overrides any Services.yaml definition of that id; the factory resolves SYS/Objects overrides.
// Without this entry f:image, f:media and f:uri.image render through the plain core service and bypass
// the image server.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Extbase\Service\ImageService::class] = [
    'className' => \LIA\LiaImageserver\Service\ImageService::class,
];

// File and ResourceStorage are data objects created via ResourceFactory, not DI container.
// SYS/Objects is the only mechanism to override them.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\File::class] = [
    'className' => \LIA\LiaImageserver\Domain\Model\File::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\ResourceStorage::class] = [
    'className' => \LIA\LiaImageserver\Domain\Model\ResourceStorage::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['lim'][] = 'LIA\\LiaImageserver\\ViewHelpers';
