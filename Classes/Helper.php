<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver;

use AUS\AusDriverAmazonS3\Driver\AmazonS3Driver;
use LIA\LiaImageserver\Service\ConfigService;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Helper
{
    /**
     * @param FileInterface $image
     * @return bool
     */
    public function isImageServerImage(FileInterface $image): bool
    {
        if (!$this->isAllowedFileExtension($image)) {
            return false;
        }

        if (ExtensionManagementUtility::isLoaded('lia_imageserver')) {
            return $this->fileIsOnS3($image);
        }
        return false;
    }

    /**
     * Check whether the imgix middleware is loaded and use the configuration there, otherwise the image server will be skipped.
     *
     * @return bool
     */
    public function isSkipImageServer(FileInterface $image): bool
    {
        if (!ExtensionManagementUtility::isLoaded('lia_middleware_imgix')) {
            return true;
        }
        $configService = GeneralUtility::makeInstance(ConfigService::class, $image);
        return $configService->isSkipImageServer();
    }

    /**
     * Check whether the file is on the amazon s3.
     *
     * @param FileInterface $image
     * @return bool
     */
    protected function fileIsOnS3(FileInterface $image): bool
    {
        return class_exists(AmazonS3Driver::class)
            ? $image->getStorage()->getDriverType() === AmazonS3Driver::DRIVER_TYPE : false;
    }

    /**
     * Check whether the file extension is allowed
     * @param FileInterface $image
     * @return bool
     */
    protected function isAllowedFileExtension(FileInterface $image): bool
    {
        $configService = GeneralUtility::makeInstance(ConfigService::class, $image);
        $allowedExtensions = $configService->getAllowedFileExtensions();
        $allowed = GeneralUtility::trimExplode(',', $allowedExtensions);

        return in_array($image->getExtension(), $allowed);
    }
}
