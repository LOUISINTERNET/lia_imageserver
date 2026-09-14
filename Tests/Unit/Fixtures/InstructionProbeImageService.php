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
 * Probe for the REAL source-set processing methods: only the innermost
 * processing call is intercepted, so the instruction flow through
 * processSourceSet()/processDefaultSourceSets()/processWebPSourceSets()
 * stays genuine and observable.
 *
 * Conditionally readonly — see RecordingImageService.
 */
if (version_compare((new Typo3Version())->getVersion(), '14.3.0', '>=')) {
    final readonly class InstructionProbeImageService extends ImageService
    {
        use InstructionProbeImageServiceBody;
    }
} else {
    final class InstructionProbeImageService extends ImageService
    {
        use InstructionProbeImageServiceBody;
    }
}
