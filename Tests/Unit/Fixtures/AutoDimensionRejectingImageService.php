<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Fixtures;

use LIA\LiaImageserver\Service\AutoDimensionException;
use LIA\LiaImageserver\Service\ImageService;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * An image service whose processing rejects the requested `auto` side, as the real one does
 * for both sides `auto` or an image without dimensions.
 */
if (version_compare((new Typo3Version())->getVersion(), '14.3.0', '>=')) {
    final readonly class AutoDimensionRejectingImageService extends ImageService
    {
        use RecordingImageServiceBody;

        public function applyProcessingInstructionsLia($image, array $processingInstructions, $passThrough = false)
        {
            throw new AutoDimensionException('Example rejection of an auto dimension.', 1790842804);
        }
    }
} else {
    final class AutoDimensionRejectingImageService extends ImageService
    {
        use RecordingImageServiceBody;

        public function applyProcessingInstructionsLia($image, array $processingInstructions, $passThrough = false)
        {
            throw new AutoDimensionException('Example rejection of an auto dimension.', 1790842804);
        }
    }
}
