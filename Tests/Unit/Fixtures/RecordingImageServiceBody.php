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
 * Shared body of RecordingImageService — lives in a trait so the fixture
 * class can be declared conditionally readonly, mirroring ImageService
 * (readonly on TYPO3 >= 14.3, regular class before). Recorders are
 * ArrayObject instances: a readonly property cannot be reassigned, but the
 * object it holds stays mutable.
 */
trait RecordingImageServiceBody
{
    public readonly \ArrayObject $liaInstructionSets;

    public readonly \ArrayObject $defaultSourceSetInstructions;

    public readonly \ArrayObject $webPSourceSetInstructions;

    public readonly \ArrayObject $externalImageArguments;

    private readonly ?FileInterface $imageToReturn;

    public function __construct(?FileInterface $imageToReturn = null)
    {
        $this->liaInstructionSets = new \ArrayObject();
        $this->defaultSourceSetInstructions = new \ArrayObject();
        $this->webPSourceSetInstructions = new \ArrayObject();
        $this->externalImageArguments = new \ArrayObject();
        $this->imageToReturn = $imageToReturn;
    }

    // Provided in production by the image server backend that overrides this service (SYS/Objects)
    public function getImageUriForExternalFile($src, $arguments): string
    {
        $this->externalImageArguments[] = $arguments;
        return 'https://external.example.com/example.jpg';
    }

    public function getImage(string $src, $image, bool $treatIdAsReference): FileInterface
    {
        if ($this->imageToReturn === null) {
            throw new \LogicException('Construct RecordingImageService with an image to return.', 1785394801);
        }
        return $this->imageToReturn;
    }

    public function applyProcessingInstructionsLia($image, array $processingInstructions, $passThrough = false)
    {
        $this->liaInstructionSets[] = $processingInstructions;
        return $image;
    }

    public function getImageUri(FileInterface $image, bool $absolute = false): string
    {
        return '/processed/example.jpg';
    }

    public function processDefaultSourceSets(FileInterface $image, array $processedConfig, array $sourceSets, array $processingInstructions, mixed $respectImageWidth): array
    {
        $this->defaultSourceSetInstructions[] = $processingInstructions;
        return $processedConfig;
    }

    public function processWebPSourceSets(FileInterface $image, array $processedConfig, array $sourceSets, array $processingInstructions, mixed $respectImageWidth): array
    {
        $this->webPSourceSetInstructions[] = $processingInstructions;
        return $processedConfig;
    }
}
