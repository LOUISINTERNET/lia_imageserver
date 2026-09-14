<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Service;

use LIA\LiaImageserver\Helper;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

class ImageService extends \TYPO3\CMS\Extbase\Service\ImageService
{
    /**
     * @var Helper
     */
    protected $helper;

    public function __construct(ResourceFactory $resourceFactory)
    {
        parent::__construct($resourceFactory);
        $this->helper = GeneralUtility::makeInstance(Helper::class);
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
            if (!empty($processingInstructions['ar'])) {
                [$targetWidth, $targetHeight] = GeneralUtility::intExplode(':', $processingInstructions['ar']);
                $localProcessingInstructions['width'] = $targetWidth;
                $localProcessingInstructions['height'] = $targetHeight;
                $targetRatio = $targetWidth / $targetHeight;
            }

            if (($processingInstructions['fit'] ?? null) === 'crop' && isset($targetRatio)) {
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
                    if (substr_count($processingInstructions['width'], 'm') > 0) {
                        $processingInstructions['width'] = str_replace('m', '', $processingInstructions['width']);
                    }
                    if (substr_count($processingInstructions['width'], 'c') > 0) {
                        $cropping['fit'] = 'crop';
                        [$width, $offset] = explode('c', $processingInstructions['width'], 2);
                        $processingInstructions['width'] = $width;
                        if (is_numeric($offset)) {
                            $cropping['crop'] = 'focalpoint';
                            $offset = MathUtility::forceIntegerInRange($offset, -100, 100);
                            $cropping['fp-x'] = ($offset / 100.0 + 1) / 2;
                        }
                    }
                }

                if (isset($processingInstructions['height'])) {
                    if (substr_count($processingInstructions['height'], 'm') > 0) {
                        $processingInstructions['height'] = str_replace('m', '', $processingInstructions['height']);
                    }
                    if (substr_count($processingInstructions['height'], 'c') > 0) {
                        $cropping['fit'] = 'crop';
                        [$height, $offset] = explode('c', $processingInstructions['height'], 2);
                        $processingInstructions['height'] = $height;
                        if (is_numeric($offset)) {
                            $cropping['crop'] = 'focalpoint';
                            $offset = MathUtility::forceIntegerInRange($offset, -100, 100);
                            $cropping['fp-y'] = ($offset / 100.0 + 1) / 2;
                        }
                    }
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
     * @param FileReference $image
     * @param array $processingInstructions
     * @param mixed $respectImageWidth
     * @param array $instructionOverride
     *
     * @return string
     */
    public function processSourceSet($givenConfig, $sourceSet, $image, $processingInstructions, $respectImageWidth, $instructionOverride = []): string
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
     * @param FileReference $image
     * @param array $processedConfig
     * @param array $sourceSets
     * @param array $processingInstructions
     * @param mixed $respectImageWidth
     *
     * @return array
     */
    public function processDefaultSourceSets($image, $processedConfig, $sourceSets, $processingInstructions, $respectImageWidth): array
    {
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
                $processedConfig['default']['src'] = explode(' ', reset($preparedSrcSet))[0];
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
     * @param FileReference $image
     * @param array $processedConfig
     * @param array $sourceSets
     * @param array $processingInstructions
     * @param mixed $respectImageWidth
     *
     * @return array
     */
    public function processWebPSourceSets($image, $processedConfig, $sourceSets, $processingInstructions, $respectImageWidth): array
    {
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

            $processedConfig['webPSourceSets'][$identifier]['srcset'] = implode(',', $preparedSrcSet);
            $processedConfig['webPSourceSets'][$identifier]['sizes'] = !empty($givenConfig['sizes']) ? $givenConfig['sizes'] : '100vw';
            $processedConfig['webPSourceSets'][$identifier]['media'] = $identifier;
        }
        return $processedConfig;
    }

    /**
     * @param array $givenConfig
     * @param FileReference $image
     * @param array $processingInstructions
     *
     * @return mixed
     */
    public function applyCropVariant($givenConfig, $image, $processingInstructions)
    {
        $cropVariantCollection = CropVariantCollection::create($image->getProperty('crop'));
        $cropVariant = $givenConfig['cropVariant'] ?: 'default';

        if ($cropVariantCollection === null) {
            $cropVariantCollection = CropVariantCollection::create($cropVariant);
        }

        $cropArea = $cropVariantCollection->getCropArea($cropVariant);
        if (!$cropArea->isEmpty()) {
            $processingInstructions['crop'] = $cropArea->makeAbsoluteBasedOnFile($image);
        }
        return $processingInstructions;
    }

    /**
     * @return bool
     */
    public function hasRespectImageWidth($respectImageWidth)
    {
        return $respectImageWidth > 0;
    }
}
