<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Domain\Model;

use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ResourceStorage extends \TYPO3\CMS\Core\Resource\ResourceStorage
{
    /**
     * @var FlexFormService
     */
    private ?FlexFormService $flexFormService = null;

    /**
     * @var array
     */
    protected array $imageserverAdapterFlex;

    /**
     * @return array
     */
    public function getImageserverAdapterFlexConfig(): array
    {
        $storage = $this->getStorageRecord();
        if (empty($storage['imageserver_adapter_flex'])) {
            // if current storage has no imageserver flexform configuration return an empty array
            return [];
        }

        return $this->getFlexFormService()->convertFlexFormContentToArray($storage['imageserver_adapter_flex']);
    }

    /**
     * @return mixed
     */
    public function getFlexFormService(): FlexFormService
    {
        if ($this->flexFormService === null) {
            $this->flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        }
        return $this->flexFormService;
    }
}
