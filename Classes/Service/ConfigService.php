<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Service;

use TYPO3\CMS\Core\Resource\FileInterface;

class ConfigService
{
    /**
     * @var array
     */
    protected $imageserverFlexConfig;

    /**
     * @var array
     */
    protected $legacyConfig;

    /**
     * ConfigService constructor.
     * @param FileInterface $file
     */
    public function __construct(FileInterface $file)
    {
        $this->imageserverFlexConfig = $file->getStorage()->getImageserverAdapterFlexConfig();
        $this->legacyConfig = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver'] ?? [];
        // append the `basic.additionalParameter` from lagacy to flex
        // later isn't defined in flexform
        $this->imageserverFlexConfig['additionalParameter'] = $this->legacyConfig['additionalParameter'] ?? '';
    }

    /**
     * Returns current valid/active configuration.
     *
     * @return array|mixed
     */
    public function getConfig()
    {
        return $this->legacyConfig;
    }

    /**
     * Check the imageserver flexform settings to skip the imageserver.
     *
     * @return bool
     */
    public function isSkipImageServer(): bool
    {
        if (!isset($this->imageserverFlexConfig['skipImageServer'])) {
            return false;
        }
        return $this->imageserverFlexConfig['skipImageServer'] == 1;
    }

    /**
     * get allowed fileextensions for imageserver
     *
     * @return string
     */
    public function getAllowedFileExtensions(): string
    {
        $allowedFileExtensions = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];

        if (!empty($this->imageserverFlexConfig['allowedFileExtensions'])) {
            $allowedFileExtensions = $this->imageserverFlexConfig['allowedFileExtensions'];
        } elseif (!empty($this->legacyConfig['allowedFileExtensions'])) {
            $allowedFileExtensions = $this->legacyConfig['allowedFileExtensions'];
        }

        return $allowedFileExtensions;
    }
}
