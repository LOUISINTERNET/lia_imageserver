<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Fixtures;

use LIA\LiaImageserver\Service\ImageService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Registers an ImageService double across both supported core versions.
 *
 * TYPO3 v13's Extbase ImageService implements SingletonInterface, v14's does not.
 * addInstance() refuses a singleton on v13 and setSingletonInstance() requires one on v14,
 * so neither call alone works on both versions.
 */
trait ImageServiceDoubleRegistration
{
    private function registerImageServiceDouble(object $double): void
    {
        if ($double instanceof SingletonInterface) {
            GeneralUtility::setSingletonInstance(ImageService::class, $double);
            return;
        }

        GeneralUtility::addInstance(ImageService::class, $double);
    }
}
