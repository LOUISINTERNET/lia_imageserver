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
use TYPO3\CMS\Core\Information\Typo3Version;

/*
 * ViewHelper-facing recording fake: captures the instruction sets a
 * ViewHelper hands to the service (BEFORE the dispatch helper strips
 * `liaContext`) without running any real processing.
 *
 * Declared conditionally readonly for the same reason as ImageService
 * itself: on TYPO3 >= 14.3 the parent chain is readonly, before it is not,
 * and PHP forbids mixing in either direction.
 */
if (version_compare((new Typo3Version())->getVersion(), '14.3.0', '>=')) {
    final readonly class RecordingImageService extends ImageService
    {
        use RecordingImageServiceBody;
    }
} else {
    final class RecordingImageService extends ImageService
    {
        use RecordingImageServiceBody;
    }
}
