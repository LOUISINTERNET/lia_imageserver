<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Domain\Model;

use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ResourceStorage extends \TYPO3\CMS\Core\Resource\ResourceStorage
{
    protected array $imageserverAdapterFlex;

    public function getImageserverAdapterFlexConfig(): array
    {
        $storage = $this->getStorageRecord();
        if (empty($storage['imageserver_adapter_flex'])) {
            return [];
        }

        // v14: FlexFormService removed, method moved to FlexFormTools
        // v13: FlexFormTools exists but without convertFlexFormContentToArray
        if (method_exists(FlexFormTools::class, 'convertFlexFormContentToArray')) {
            return GeneralUtility::makeInstance(FlexFormTools::class)
                ->convertFlexFormContentToArray($storage['imageserver_adapter_flex']);
        }

        return GeneralUtility::makeInstance(FlexFormService::class)
            ->convertFlexFormContentToArray($storage['imageserver_adapter_flex']);
    }
}
