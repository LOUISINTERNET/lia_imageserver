<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Fixtures;

use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Shared body of InstructionProbeImageService (see RecordingImageServiceBody
 * for the conditional-readonly rationale).
 */
trait InstructionProbeImageServiceBody
{
    public readonly \ArrayObject $recordedInstructionSets;

    public function __construct()
    {
        $this->recordedInstructionSets = new \ArrayObject();
    }

    public function applyProcessingInstructionsLia($image, array $processingInstructions, $passThrough = false)
    {
        $this->recordedInstructionSets[] = $processingInstructions;
        return $image;
    }

    public function getImageUri(FileInterface $image, bool $absolute = false): string
    {
        return '/processed/example.jpg';
    }
}
