<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Fixtures;

use LIA\LiaImageserver\Helper;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * In-memory fake steering the ImageService branching without touching
 * extension configuration, storage drivers, or the imgix middleware.
 */
final class HelperFake extends Helper
{
    public function __construct(
        private readonly bool $imageServerImage,
        private readonly bool $skipImageServer,
    ) {}

    public function isImageServerImage(FileInterface $image): bool
    {
        return $this->imageServerImage;
    }

    public function isSkipImageServer(FileInterface $image): bool
    {
        return $this->skipImageServer;
    }
}
