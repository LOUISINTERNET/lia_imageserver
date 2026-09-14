<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Fixtures;

use LIA\LiaImageserver\Service\ImageServiceTrait;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Exposes the instruction transformation with the image server switched off for the
 * storage, which is the branch a per-storage `skipImageServer` flag selects. That
 * branch had never been executed before.
 *
 * Composes the TRAIT rather than extending `ImageService`: on this line the parent is
 * `readonly` from TYPO3 v14.3 on, and a non-readonly subclass cannot extend it while a
 * readonly one cannot assign the inherited `$helper`. The trait is where the code lives
 * anyway, so composing it is both possible and closer to the subject.
 */
final class SkippingImageService
{
    use ImageServiceTrait;

    public function __construct()
    {
        $this->helper = new HelperFake(imageServerImage: true, skipImageServer: true);
    }

    public function transformForLocalProcessing(array $processingInstructions, ?FileInterface $image = null): array
    {
        return $this->transformProcessingInstructions($processingInstructions, $image);
    }
}
