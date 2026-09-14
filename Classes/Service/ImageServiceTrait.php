<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Service;

use LIA\LiaImageserver\Event\ModifyProcessingInstructionsEvent;
use LIA\LiaImageserver\Helper;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

/**
 * Shared body of LIA\LiaImageserver\Service\ImageService.
 *
 * Lives in a trait so that ImageService.php can declare the class
 * conditionally — readonly (TYPO3 v14, parent is `readonly class`)
 * or non-readonly (TYPO3 v13.4, parent is a regular class).
 */
trait ImageServiceTrait
{
    protected readonly Helper $helper;

    public function __construct(
        ResourceFactory $resourceFactory,
        Helper $helper,
    ) {
        parent::__construct($resourceFactory);
        $this->helper = $helper;
    }

    /**
     * Create a processed file
     *
     * @param FileInterface|FileReference $image
     * @param array $processingInstructions
     *
     * @return ProcessedFile
     */
    public function applyProcessingInstructions($image, array $processingInstructions): ProcessedFile
    {
        if (is_callable([$image, 'getOriginalFile'])) {
            $image = $image->getOriginalFile();
        }
        $processingInstructions = $this->dispatchModifyProcessingInstructionsEvent($image, $processingInstructions);

        $processingInstructions = $this->ensureFitWithinExactDimensions($processingInstructions);

        if ($this->helper->isImageServerImage($image) && !$this->helper->isSkipImageServer($image)) {
            $startProperties = $image->getProperties();
            $processingInstructions = $this->transformProcessingInstructions($processingInstructions, $image);
            $image->setProcessingInstructions($processingInstructions);

            $image->updateProperties($processingInstructions);
            $image->updateProperties($startProperties);

            $processedImage = $image->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, $processingInstructions);
            $this->setCompatibilityValues($processedImage);

            return $processedImage;
        }
        return parent::applyProcessingInstructions($image, $processingInstructions);

    }

    /**
     * Create a processed file
     *
     * @param FileInterface|FileReference $image
     * @param array $processingInstructions
     * @param bool|false $passThrough
     *
     * @return ProcessedFile|FileInterface|FileReference
     */
    public function applyProcessingInstructionsLia($image, array $processingInstructions, $passThrough = false)
    {
        if (is_callable([$image, 'getOriginalFile'])) {
            $image = $image->getOriginalFile();
        }
        $processingInstructions = $this->dispatchModifyProcessingInstructionsEvent($image, $processingInstructions);

        $processingInstructions = $this->ensureFitWithinExactDimensions($processingInstructions);

        if ($this->helper->isImageServerImage($image)) {
            $startProperties = $image->getProperties();
            $startProcessingInstructions = $image->getProcessingInstructions();
            $processingInstructions = $this->transformProcessingInstructions($processingInstructions, $image);

            if ($this->helper->isSkipImageServer($image)) {
                return parent::applyProcessingInstructions($image, $processingInstructions);
            }
            $image->setProcessingInstructions($processingInstructions);

            $image->updateProperties($processingInstructions);
            $newImage = clone $image;
            $image->updateProperties($startProperties);
            $image->setProcessingInstructions($startProcessingInstructions);

            if ($passThrough === true) {
                return parent::applyProcessingInstructions($image, $processingInstructions);
            }

            return $newImage;
        }
        return parent::applyProcessingInstructions($image, $processingInstructions);

    }

    /**
     * Dispatch the ModifyProcessingInstructionsEvent for a rendering call.
     *
     * Owns the `liaContext` handling: the context is extracted from the
     * instructions and exposed read-only on the event; the returned
     * instruction set never contains the `liaContext` key, so every
     * downstream consumer (local processing, imgix clone) receives
     * instructions without it.
     *
     * @param FileInterface $image
     * @param array $processingInstructions
     *
     * @return array
     */
    private function dispatchModifyProcessingInstructionsEvent(FileInterface $image, array $processingInstructions): array
    {
        $context = [];
        if (array_key_exists('liaContext', $processingInstructions)) {
            $context = $processingInstructions['liaContext'];
            if (!is_array($context)) {
                throw new \InvalidArgumentException(
                    'The processing instruction "liaContext" must be an array, ' . get_debug_type($context) . ' given.',
                    1785394800
                );
            }
            unset($processingInstructions['liaContext']);
        }

        $event = new ModifyProcessingInstructionsEvent($image, $processingInstructions, $context);
        GeneralUtility::makeInstance(EventDispatcherInterface::class)->dispatch($event);

        return $event->getInstructions();
    }

    /**
     * When the caller specifies both `width` and `height` as bare numeric values
     * (i.e. without any `c`/`m` suffix), TYPO3 core's image processor scales the
     * source rectangle to exactly width × height — stretching the image when the
     * source aspect ratio does not match the requested one. Neither the visible
     * content nor the aspect ratio survives that transformation.
     *
     * Append `m` to both bare numerics so core treats the values as maximum
     * constraints: the entire source rectangle is preserved, scaled to fit
     * within the requested box, with the actual processed dimensions reflecting
     * the source aspect ratio. The `lim:image` ViewHelper writes those actual
     * dimensions into the `<img width="…" height="…">` attributes, so the
     * browser displays the image without any geometric distortion.
     *
     * Filling the leftover space (when the source aspect differs from the
     * requested aspect) is a layout/CSS concern in the surrounding container,
     * not an image-processing concern — exactly the same separation as
     * `object-fit: contain` in CSS.
     *
     * No-op when:
     *  - either dimension is missing,
     *  - either dimension already carries a `c` or `m` suffix (caller is being
     *    explicit and we must not override their choice),
     *  - either dimension is non-numeric (already a string with semantics).
     *
     * For the Imgix code path, `transformProcessingInstructions()` strips the
     * `m` suffix again and treats the value as an explicit width/height, so
     * the only behavioural difference there is that no `cropIS['fit'] = 'crop'`
     * is auto-applied — matching the editor intent that the entire source
     * remain visible.
     *
     * @param array $processingInstructions
     * @return array
     */
    protected function ensureFitWithinExactDimensions(array $processingInstructions): array
    {
        if (empty($processingInstructions['width']) || empty($processingInstructions['height'])) {
            return $processingInstructions;
        }

        $width = (string)$processingInstructions['width'];
        $height = (string)$processingInstructions['height'];

        $alreadyExplicit = str_contains($width, 'c')
            || str_contains($width, 'm')
            || str_contains($height, 'c')
            || str_contains($height, 'm');
        if ($alreadyExplicit) {
            return $processingInstructions;
        }

        if (!is_numeric($processingInstructions['width']) || !is_numeric($processingInstructions['height'])) {
            return $processingInstructions;
        }

        $processingInstructions['width'] = $width . 'm';
        $processingInstructions['height'] = $height . 'm';

        return $processingInstructions;
    }

    /**
     * Transform processing instructions
     *
     * @param array $processingInstructions
     * @param FileInterface|FileReference|null $image
     *
     * @return array
     */
    protected function transformProcessingInstructions(array $processingInstructions, $image = null): array
    {
        if ($this->helper->isSkipImageServer($image)) {
            /*
             * Every key read in this branch is OPTIONAL — a rendering may well ask for
             * nothing but a width. They were read unguarded, and under PHP 8 a missing
             * key is a warning that TYPO3's error handler escalates into an exception,
             * so a rendering without an aspect ratio took the whole page down. The
             * branch is only reached when a storage opts out of the image server, which
             * is why it went unnoticed for so long.
             */
            if (!empty($processingInstructions['ar'])) {
                [$targetWidth, $targetHeight] = GeneralUtility::intExplode(':', $processingInstructions['ar']);
                $localProcessingInstructions['width'] = $targetWidth;
                $localProcessingInstructions['height'] = $targetHeight;
                $targetRatio = $targetWidth / $targetHeight;
            }

            if (($processingInstructions['fit'] ?? null) == 'crop' && isset($targetRatio)) {
                //if there is a crop already
                $startOffsetX = 0;
                $startOffsetY = 0;
                $imageWidth = $image->getProperty('width');
                $imageHeight = $image->getProperty('height');

                if (!empty($processingInstructions['crop']) && is_object($processingInstructions['crop'])) {
                    $startOffsetX = $processingInstructions['crop']->getOffsetLeft();
                    $startOffsetY = $processingInstructions['crop']->getOffsetTop();
                    $imageWidth = $processingInstructions['crop']->getWidth();
                    $imageHeight = $processingInstructions['crop']->getHeight();
                }

                // Crop width / height are relative to original image
                // We always Center the cropped area
                $offsetX = 0.0;
                $offsetY = 0.0;
                if ($targetRatio > 1) {
                    $cropWidth = $imageWidth;
                    $cropHeight = $imageWidth / $targetRatio;
                    if ($cropHeight > $imageHeight) {
                        $cropHeight = $imageHeight;
                        $cropWidth = $imageHeight * $targetRatio;
                        $offsetX = ($imageWidth - $cropWidth) / 2;
                    } else {
                        $offsetY = ($imageHeight - $cropHeight) / 2;
                    }
                } else {
                    $cropHeight = $imageHeight;
                    $cropWidth = $imageHeight * $targetRatio;
                    if ($cropWidth > $imageWidth) {
                        $cropWidth = $imageWidth;
                        $cropHeight = $imageWidth / $targetRatio;
                        $offsetY = ($imageHeight - $cropHeight) / 2;
                    } else {
                        $offsetX = ($imageWidth - $cropWidth) / 2;
                    }
                }

                $crop = [
                    'x' => $startOffsetX + $offsetX,
                    'y' => $startOffsetY + $offsetY,
                    'width' => $cropWidth,
                    'height' => $cropHeight,
                ];
                $processingInstructions['crop'] = GeneralUtility::makeInstance(Area::class, ...$crop);
            }
            if (!empty($processingInstructions['w'])) {
                $processingInstructions['width'] = $processingInstructions['w'];
            }
            if (!empty($processingInstructions['h'])) {
                $processingInstructions['height'] = $processingInstructions['h'];
            }
        } else {
            if (!empty($processingInstructions['width']) || !empty($processingInstructions['height'])) {
                $cropping = [];

                if (isset($processingInstructions['width'])) {
                    $width = (string)$processingInstructions['width'];
                    if (substr_count($width, 'm') > 0) {
                        $width = str_replace('m', '', $width);
                        $processingInstructions['width'] = $width;
                    }
                    if (substr_count($width, 'c') > 0) {
                        $cropping['fit'] = 'crop';
                        [$width, $offset] = explode('c', $width, 2);
                        $processingInstructions['width'] = $width;
                        if (is_numeric($offset)) {
                            $cropping['crop'] = 'focalpoint';
                            $offset = MathUtility::forceIntegerInRange($offset, -100, 100);
                            $cropping['fp-x'] = ($offset / 100.0 + 1) / 2;
                        }
                    }
                }

                if (isset($processingInstructions['height'])) {
                    $height = (string)$processingInstructions['height'];
                    if (substr_count($height, 'm') > 0) {
                        $height = str_replace('m', '', $height);
                        $processingInstructions['height'] = $height;
                    }
                    if (substr_count($height, 'c') > 0) {
                        $cropping['fit'] = 'crop';
                        [$height, $offset] = explode('c', $height, 2);
                        $processingInstructions['height'] = $height;
                        if (is_numeric($offset)) {
                            $cropping['crop'] = 'focalpoint';
                            $offset = MathUtility::forceIntegerInRange($offset, -100, 100);
                            $cropping['fp-y'] = ($offset / 100.0 + 1) / 2;
                        }
                    }
                }

                // If both width and height are given, auto-apply fit=crop so the
                // delivered image matches the requested aspect ratio. Mirrors the
                // behaviour of processSourceSet() for srcset entries — otherwise
                // the main src would render without crop and the browser would
                // stretch the result to the explicit width/height attributes.
                if (!empty($processingInstructions['width']) && !empty($processingInstructions['height'])) {
                    $cropping['fit'] ??= 'crop';
                }

                if (!empty($cropping)) {
                    $processingInstructions['cropIS'] = $cropping;
                }
            }
        }

        return $processingInstructions;
    }

    /**
     * @param array $givenConfig
     * @param array $sourceSet
     * @param FileInterface $image
     * @param array $processingInstructions
     * @param array $instructionOverride
     */
    public function processSourceSet(array $givenConfig, array $sourceSet, FileInterface $image, array $processingInstructions, mixed $respectImageWidth, array $instructionOverride = []): string
    {
        if (isset($givenConfig['options'])) {
            ArrayUtility::mergeRecursiveWithOverrule($processingInstructions, $givenConfig['options']);
        }
        ArrayUtility::mergeRecursiveWithOverrule($processingInstructions, $sourceSet);
        ArrayUtility::mergeRecursiveWithOverrule($processingInstructions, $instructionOverride);

        if (isset($givenConfig['cropVariant'])) {
            $processingInstructions = $this->applyCropVariant($givenConfig, $image, $processingInstructions);
        }

        if (isset($processingInstructions['w']) && $this->hasRespectImageWidth($respectImageWidth)) {
            $imageWidth = $respectImageWidth;
            $sourceSetWidth = $processingInstructions['w'];

            if ($imageWidth <= $sourceSetWidth) {
                return '';
            }
        }

        // apply fit=crop if width and height are set
        if (isset($processingInstructions['w']) && isset($processingInstructions['h'])) {
            $processingInstructions['fit'] = 'crop';
        }
        $processedImage = $this->applyProcessingInstructionsLia($image, $processingInstructions);
        $uri = $this->getImageUri($processedImage);
        $width = $processingInstructions['w'];
        $uri .= " {$width}w";
        return $uri;
    }

    /**
     * @param array $processedConfig
     * @param array $sourceSets
     * @param array $processingInstructions
     */
    public function processDefaultSourceSets(FileInterface $image, array $processedConfig, array $sourceSets, array $processingInstructions, mixed $respectImageWidth): array
    {
        $processingInstructions = $this->injectMaxRenderWidthFromSourceSets($processingInstructions, $sourceSets);
        foreach ($sourceSets as $identifier => $givenConfig) {
            $preparedSrcSet = [];
            foreach ($givenConfig['srcset'] as $key => $sourceSet) {
                $uri = $this->processSourceSet($givenConfig, $sourceSet, $image, $processingInstructions, $respectImageWidth);
                if ($uri) {
                    $preparedSrcSet[] = $uri;
                }
            }

            if (count($preparedSrcSet) === 0) {
                return $processedConfig;
            }

            $isDefaultConfig = isset($givenConfig['default']) && $givenConfig['default'] === 1;
            if ($isDefaultConfig || $identifier === 'default') {
                $processedConfig['default']['src'] = explode(' ', (string)reset($preparedSrcSet))[0];
                $processedConfig['default']['srcset'] = implode(',', $preparedSrcSet);
                $processedConfig['default']['sizes'] = !empty($givenConfig['sizes']) ? $givenConfig['sizes'] : '100vw';
                $processedConfig['default']['media'] = 'default';
            } else {
                $processedConfig['sources'][$identifier]['srcset'] = implode(',', $preparedSrcSet);
                $processedConfig['sources'][$identifier]['sizes'] = !empty($givenConfig['sizes']) ? $givenConfig['sizes'] : '100vw';
                $processedConfig['sources'][$identifier]['media'] = $identifier;
            }
        }
        return $processedConfig;
    }

    /**
     * @param array $processedConfig
     * @param array $sourceSets
     * @param array $processingInstructions
     */
    public function processWebPSourceSets(FileInterface $image, array $processedConfig, array $sourceSets, array $processingInstructions, mixed $respectImageWidth): array
    {
        $processingInstructions = $this->injectMaxRenderWidthFromSourceSets($processingInstructions, $sourceSets);
        foreach ($sourceSets as $identifier => $givenConfig) {
            $preparedSrcSet = [];
            foreach ($givenConfig['srcset'] as $key => $sourceSet) {
                $uri = $this->processSourceSet($givenConfig, $sourceSet, $image, $processingInstructions, $respectImageWidth, ['fm' => 'webp']);
                if ($uri) {
                    $preparedSrcSet[] = $uri;
                }
            }

            if (count($preparedSrcSet) === 0) {
                return $processedConfig;
            }

            $isDefaultConfig = isset($givenConfig['default']) && $givenConfig['default'] === 1;
            if ($isDefaultConfig || $identifier === 'default') {
                $processedConfig['default']['src'] = explode(' ', (string)reset($preparedSrcSet))[0];
                $processedConfig['default']['srcset'] = implode(',', $preparedSrcSet);
                $processedConfig['default']['sizes'] = !empty($givenConfig['sizes']) ? $givenConfig['sizes'] : '100vw';
                $processedConfig['default']['media'] = 'default';
            } else {
                $processedConfig['webPSourceSets'][$identifier]['srcset'] = implode(',', $preparedSrcSet);
                $processedConfig['webPSourceSets'][$identifier]['sizes'] = !empty($givenConfig['sizes']) ? $givenConfig['sizes'] : '100vw';
                $processedConfig['webPSourceSets'][$identifier]['media'] = $identifier;
            }
        }
        return $processedConfig;
    }

    /**
     * Inject the largest requested source-set width into `liaContext` so
     * all srcset variants of one rendering call agree on `_maxRenderWidth`.
     *
     * @param array $processingInstructions
     * @param array $sourceSets
     *
     * @return array
     */
    private function injectMaxRenderWidthFromSourceSets(array $processingInstructions, array $sourceSets): array
    {
        $maxRenderWidth = 0;
        foreach ($sourceSets as $givenConfig) {
            foreach ($givenConfig['srcset'] ?? [] as $sourceSet) {
                if (isset($sourceSet['w'])) {
                    $maxRenderWidth = max($maxRenderWidth, (int)$sourceSet['w']);
                }
            }
        }
        if ($maxRenderWidth > 0) {
            $processingInstructions['liaContext']['_maxRenderWidth'] = $maxRenderWidth;
        }
        return $processingInstructions;
    }

    public function applyCropVariant(array $givenConfig, $image, array $processingInstructions): array
    {
        $cropString = $image->getProperty('crop') ?: '';
        $cropVariantCollection = CropVariantCollection::create((string)$cropString);
        $cropVariant = $givenConfig['cropVariant'] ?: 'default';
        $cropArea = $cropVariantCollection->getCropArea($cropVariant);
        if (!$cropArea->isEmpty()) {
            $processingInstructions['crop'] = $cropArea->makeAbsoluteBasedOnFile($image);
        }
        return $processingInstructions;
    }

    public function hasRespectImageWidth(mixed $respectImageWidth): bool
    {
        return $respectImageWidth > 0;
    }
}
