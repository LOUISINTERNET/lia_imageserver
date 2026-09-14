<?php

/*
 * This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace LIA\LiaImageserver\ViewHelpers;

use LIA\LiaImageserver\Helper;
use LIA\LiaImageserver\Service\ImageService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * This ViewHelper is based on the `f:image` ViewHelper and extends it with some additional attributes.
 *
 * Example
 * =======
 *
 * .. code-block:: html
 *
 *
 *      <picture>
 *          <lim:image
 *              treatIdAsReference="true"
 *              src="{image.id}"
 *              alt="{image.alternative}"
 *              enableWebPSupport="true"
 *              width="600"
 *              height="400"
 *              loading="lazy"
 *              cropVariant="tablet"
 *              additionalAttributes="{itemprop: 'test'}"
 *              imageServerOptions="{fm: 'pjpg', q: 'lossless'}"
 *              sizes="{
 *                  0: '100vw',
 *                  1: '(min-width: 850px) 350px',
 *                  2: '(min-width: 1024px) calc((100vw - 130px) / 2)'
 *              }"
 *              sourceSets="{
 *                  0: {w:320},
 *                  1: {w:350},
 *                  2: {w:490},
 *                  3: {w:1050}
 *              }"
 *              passThrough="true"
 *          />
 *      </picture>
 *
 * You can also use the inline notation.
 *
 * .. code-block:: html
 *
 *      <picture>
 *          <f:variable name="sizes" value="{
 *              0: '100vw',
 *              1: '(min-width: 850px) 350px',
 *              2: '(min-width: 1024px) calc((100vw - 130px) / 2)'
 *          }" />
 *          <f:variable name="srcSet" value="{0: {w:320}, 1: {w:350}, 2: {w:490}, 3: {w:1050}}" />
 *          {lim:image(treatIdAsReference: true, src: image.id, alt: image.alternative, width: '600c', height: '400c', cropVariant: 'tablet', imageServerOptions: '{fm: "pjpg", q: "lossless"}', sizes: sizes, sourceSets: srcSet, passThrough: true, loading: 'lazy')}
 *      </picture>
 */
class ImageViewHelper extends AbstractTagBasedViewHelper
{
    use LiaContextTrait;

    /**
     * @var string $tagName
     */
    protected $tagName = 'img';

    /**
     * Initialize this ViewHelper with all arguments.
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('imageServerOptions', 'array', '', false, []);
        $this->registerArgument('objectFit', 'string', '', false);
        $this->registerArgument('objectPosition', 'string', '', false);
        $this->registerArgument('sizes', 'array', '', false, []);
        $this->registerArgument('sourceSets', 'array', '', false, []);
        $this->registerArgument('enableWebPSupport', 'boolean', '', false, false);
        $this->registerArgument('respectImageWidth', 'int', 'If set source-set definitions will be processed to ensure not to exceed the given image width', false, 0);
        $this->registerArgument('respectImageWidthClasses', 'array', 'If respectImageWidh is set, this classes will be applied to the IMG tag', false, ['staticimage', 'u-respect-image-width']);
        $this->registerArgument('passThrough', 'boolean', '', false, false);
        $this->registerArgument('loading', 'string', 'Loading attribute (none|eager|lazy)', false, 'lazy');
        $this->registerLiaContextArgument();

        // Arguments from core-imageviewhelper as this is now final
        // https://github.com/TYPO3/typo3/blob/main/typo3/sysext/fluid/Classes/ViewHelpers/Uri/ImageViewHelper.php
        $this->registerArgument('src', 'string', 'src', false, '');
        $this->registerArgument('treatIdAsReference', 'bool', 'given src argument is a sys_file_reference record', false, false);
        $this->registerArgument('image', 'object', 'image');
        $this->registerArgument('crop', 'string|bool|array', 'overrule cropping of image (setting to FALSE disables the cropping set in FileReference)');
        $this->registerArgument('cropVariant', 'string', 'select a cropping variant, in case multiple croppings have been specified or stored in FileReference', false, 'default');
        $this->registerArgument('fileExtension', 'string', 'Custom file extension to use');

        $this->registerArgument('width', 'string', 'width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('height', 'string', 'height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('minWidth', 'int', 'minimum width of the image');
        $this->registerArgument('minHeight', 'int', 'minimum height of the image');
        $this->registerArgument('maxWidth', 'int', 'maximum width of the image');
        $this->registerArgument('maxHeight', 'int', 'maximum height of the image');
        $this->registerArgument('absolute', 'bool', 'Force absolute URL', false, false);
        $this->registerArgument('base64', 'bool', 'Return a base64 encoded version of the image', false, false);
        $this->registerArgument('fetchPriority', 'string', 'A string representing the priority hint. Possible values are:. "high", "low" or "auto"', false);
    }

    /**
     * Resizes a given image (if required) and renders the respective img tag
     *
     * @see https://docs.typo3.org/typo3cms/TyposcriptReference/ContentObjects/Image/
     *
     * @return string Rendered tag
     *
     * @throws \Exception
     */
    public function render(): string
    {
        $src = (string)$this->arguments['src'];
        $image = $this->arguments['image'];
        $treatIdAsReference = (bool)$this->arguments['treatIdAsReference'];
        $cropString = $this->arguments['crop'];
        $absolute = $this->arguments['absolute'];

        if (($src === '' && $image === null) || ($src !== '' && $image !== null)) {
            throw new Exception('You must either specify a string src or a File object.', 1460976233);
        }

        if ($this->hasArgument('fetchPriority')) {
            $fetchPriority = in_array($this->arguments['fetchPriority'], ['low', 'high', 'auto']) ? $this->arguments['fetchPriority'] : 'auto';
            $this->tag->addAttribute('fetchPriority', $fetchPriority);
        }

        if ($src !== '' && preg_match('/^(https?:)?\/\//', $src)) {
            if (Environment::getContext()->isDevelopment()) {
                $this->tag->addAttribute('data-publicurl', $src);
            }

            $this->tag->addAttribute('src', $src);

            if (!empty($this->arguments['width'])) {
                $this->tag->addAttribute('width', $this->arguments['width']);
            }
            if (!empty($this->arguments['height'])) {
                $this->tag->addAttribute('height', $this->arguments['height']);
            }
            return $this->tag->render();
        }
        try {
            $imageService = self::getImageService();
            $image = $imageService->getImage($src, $image, $treatIdAsReference);

            if (Environment::getContext()->isDevelopment()) {
                $this->tag->addAttribute('data-publicurl', $image->getPublicUrl());
            }

            $cropString = $this->getCropping($this->arguments['crop'], $image);

            $cropVariantCollection = CropVariantCollection::create($cropString);
            $cropVariant = $this->arguments['cropVariant'] ?: 'default';
            $cropArea = $cropVariantCollection->getCropArea($cropVariant);
            $processingInstructions = $this->arguments['imageServerOptions'];

            ArrayUtility::mergeRecursiveWithOverrule($processingInstructions, [
                'width' => $this->arguments['width'],
                'height' => $this->arguments['height'],
                'minWidth' => $this->arguments['minWidth'],
                'minHeight' => $this->arguments['minHeight'],
                'maxWidth' => $this->arguments['maxWidth'],
                'maxHeight' => $this->arguments['maxHeight'],
                'crop' => $this->getCropValue($cropArea, $processingInstructions, $image),
            ]);
            $processingInstructions = $this->mergeLiaContextIntoInstructions($processingInstructions, (int)$this->arguments['width']);

            $processedImage = $imageService->applyProcessingInstructionsLia($image, $processingInstructions, $this->arguments['passThrough']);
            $imageUri = $imageService->getImageUri($processedImage, $this->arguments['absolute']);

            if (!$this->tag->hasAttribute('data-focus-area')) {
                $focusArea = $cropVariantCollection->getFocusArea($cropVariant);
                if (!$focusArea->isEmpty()) {
                    $this->tag->addAttribute('data-focus-area', (string)$focusArea->makeAbsoluteBasedOnFile($image));
                }
            }

            $this->tag->addAttribute('src', $imageUri);
            $this->tag->addAttribute('width', $processedImage->getProperty('width'));
            $this->tag->addAttribute('height', $processedImage->getProperty('height'));
            $this->tag->addAttribute('loading', $this->getLazyLoading());

            $alt = $image->getProperty('alternative');
            $title = $image->getProperty('title');

            if (empty($this->additionalArguments['alt'])) {
                $this->tag->addAttribute('alt', $alt);
            }
            if (empty($this->additionalArguments['title']) && $title) {
                $this->tag->addAttribute('title', $title);
            }

            $this->checkAttributes([
                'objectFit' => 'data-object-fit',
                'objectPosition' => 'data-object-position',
            ]);

            if (!empty($this->arguments['sizes'])) {
                $reversedSizes = array_reverse($this->arguments['sizes']);
                $this->tag->addAttribute('sizes', implode(',' . PHP_EOL, $reversedSizes));
            }

            if (!empty($this->arguments['sourceSets'])) {
                $srcSetUris = $this->generateSrcsets($cropArea, $image);
                $this->tag->addAttribute('srcset', implode(',' . PHP_EOL, $srcSetUris));
            }

            if ($this->arguments['respectImageWidth'] > 0) {
                $this->respectImageWidth($this->arguments['respectImageWidth']);
            }

            if ($this->arguments['enableWebPSupport']) {
                return $this->enableWebPSupport($cropArea, $image, ['fm' => 'webp']);
            }
        } catch (ResourceDoesNotExistException $exception) {
            GeneralUtility::makeInstance(LoggerInterface::class)->warning('ImageViewHelper: file does not exist', ['exception' => $exception]);
        } catch (\UnexpectedValueException $exception) {
            GeneralUtility::makeInstance(LoggerInterface::class)->warning('ImageViewHelper: file has been replaced with a folder', ['exception' => $exception]);
        } catch (\RuntimeException $exception) {
            GeneralUtility::makeInstance(LoggerInterface::class)->warning('ImageViewHelper: file is outside of a storage', ['exception' => $exception]);
        } catch (\InvalidArgumentException $exception) {
            GeneralUtility::makeInstance(LoggerInterface::class)->warning('ImageViewHelper: file storage does not exist', ['exception' => $exception]);
        }

        return $this->tag->render();
    }

    /**
     * @param array $attributes
     *
     *
     * @throws \Exception
     */
    protected function checkAttributes($attributes = [])
    {
        foreach ($attributes as $key => $attribute) {
            if (!empty($this->arguments[$key])) {
                $this->tag->addAttribute($attribute, $this->arguments[$key]);
            }
        }
    }

    /**
     * @param string $respectImageWidth
     *
     *
     * @throws \Exception
     */
    protected function respectImageWidth($respectImageWidth)
    {
        $this->tag->addAttribute('width', $respectImageWidth);
        $this->tag->removeAttribute('height');
        $mergedClasses = $this->tag->getAttribute('class') . ' ' . implode(' ', $this->arguments['respectImageWidthClasses']);
        $this->tag->addAttribute('class', $mergedClasses);
    }

    /**
     * @param FileInterface|string $image
     * @param array $optionalParams
     */
    protected function enableWebPSupport(Area $cropArea, $image, array $optionalParams = [], bool $externalImage = false): string
    {
        $srcsetAttribute = implode(',' . PHP_EOL, $this->generateSrcsets($cropArea, $image, $optionalParams, $externalImage));
        $sizesAttribute = $this->tag->getAttribute('sizes');
        $webPSupportSourceTag = sprintf(
            '<source srcset="%s" sizes="%s" type="image/webp" />',
            htmlspecialchars($srcsetAttribute, ENT_QUOTES | ENT_HTML5),
            htmlspecialchars((string)$sizesAttribute, ENT_QUOTES | ENT_HTML5)
        );
        $imgTag = $this->tag->render();

        return $webPSupportSourceTag . PHP_EOL . $imgTag;
    }

    /**
     * @param string|array|bool|null $crop
     */
    protected function getCropping($crop = null, ?FileInterface $image = null): string
    {
        if (!empty($crop)) {
            if (is_array($crop)) {
                return json_encode($crop);
            }
            return $crop;
        }

        if ($image != null && $image->hasProperty('crop') && $image->getProperty('crop')) {
            return $image->getProperty('crop');
        }

        return '';
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getLazyLoading()
    {
        $loading = $this->additionalArguments['loading'] ?? $this->arguments['loading'];
        if ($loading == 'none') {
            return '';
        }

        return $loading ?? 'lazy';
    }

    /**
     * @param FileInterface|string $image
     * @param array $optionalParams
     * @return array<int, string>
     */
    protected function generateSrcsets(Area $cropArea, $image, array $optionalParams = [], bool $externalImage = false)
    {
        $srcSetUris = [];
        $respectImageWidth = $this->arguments['respectImageWidth'];
        $maxRenderWidth = $this->determineMaxSourceSetWidth();
        foreach ($this->arguments['sourceSets'] as $src) {
            $params = $this->arguments['imageServerOptions'];
            ArrayUtility::mergeRecursiveWithOverrule($params, $src);

            if (!isset($params['w'])) {
                $stringyfiedSrc = json_encode($src);
                throw new \Exception("No 'w' parameter given on sourceSet ($stringyfiedSrc). Please check your config.", 7015033580);
            }

            // Derive proportional `h` from the ViewHelper's width/height when the
            // source set only carries `w`. Mirrors the editor intent of e.g.
            // <lim:image width="320" height="460" sourceSets="{0:{w:320}, 1:{w:390}}">:
            // every responsive variant should preserve the 320:460 aspect ratio
            // requested for the main `src`.
            if (!isset($params['h'])
                && is_numeric($this->arguments['width'] ?? null)
                && is_numeric($this->arguments['height'] ?? null)
            ) {
                $aspectRatio = (float)$this->arguments['height'] / (float)$this->arguments['width'];
                $params['h'] = (int)round((float)$params['w'] * $aspectRatio);
            }

            if ($respectImageWidth > 0) {
                $imageWidth = $respectImageWidth;
                $sourceSetWidth = $params['w'];

                if ($imageWidth <= $sourceSetWidth) {
                    continue;
                }
            }
            $imageService = self::getImageService();

            if ($externalImage) {
                // Only set crop when getCropValue resolves to a value. External
                // images (e.g. YouTube thumbnails) have no TYPO3 crop area, so
                // getCropValue returns null. Letting null reach Imgix\UrlBuilder
                // triggers rawurlencode(null) — a PHP 8.1+ deprecation that
                // TYPO3 elevates to an exception in dev mode.
                $cropValue = $this->getCropValue($cropArea, $params);
                if ($cropValue !== null) {
                    $params['crop'] = $cropValue;
                }
                ArrayUtility::mergeRecursiveWithOverrule($params, $optionalParams);
                $srcSetUris[] = $imageService->getImageUriForExternalFile($image, $params) . " {$params['w']}w"; // @phpstan-ignore method.notFound (method provided by lia_middleware_imgix via SYS/Objects override)
            } else {
                $helper = GeneralUtility::makeInstance(Helper::class);
                // Translate Imgix-style short keys (`w`/`h`) into TYPO3 core keys
                // (`width`/`height`) whenever the file does not go through Imgix:
                // either because the middleware is not loaded at all (the original
                // `isSkipImageServer` case), or because the file lives on local
                // storage instead of S3. Without this, core ignores the `w` key and
                // returns the unprocessed original — every srcset entry then points
                // to the same source file, which the browser stretches to the
                // explicit width/height on the `<img>` tag.
                if (!$helper->isImageServerImage($image)) {
                    $params['width'] = $params['w'];
                    if (isset($params['h'])) {
                        $params['height'] = $params['h'];
                    }
                }
                // Same null-guard for the FAL branch — see comment above.
                $cropValue = $this->getCropValue($cropArea, $params, $image);
                if ($cropValue !== null) {
                    $params['crop'] = $cropValue;
                }
                ArrayUtility::mergeRecursiveWithOverrule($params, $optionalParams);
                $params = $this->mergeLiaContextIntoInstructions($params, $maxRenderWidth);
                $processedImage = $imageService->applyProcessingInstructionsLia($image, $params, $this->arguments['passThrough']);
                $srcSetUris[] = $imageService->getImageUri($processedImage, $this->arguments['absolute']) . " {$params['w']}w";
            }
        }
        return $srcSetUris;
    }

    /**
     * Largest requested source-set width of this rendering call:
     * all srcset variants of one call must agree on `_maxRenderWidth`.
     *
     * @return int 0 when no source set carries a `w` parameter
     */
    private function determineMaxSourceSetWidth(): int
    {
        $maxSourceSetWidth = 0;
        foreach ($this->arguments['sourceSets'] as $sourceSet) {
            $mergedParams = $this->arguments['imageServerOptions'];
            ArrayUtility::mergeRecursiveWithOverrule($mergedParams, $sourceSet);
            if (isset($mergedParams['w'])) {
                $maxSourceSetWidth = max($maxSourceSetWidth, (int)$mergedParams['w']);
            }
        }
        return $maxSourceSetWidth;
    }

    /**
     * Get cropping values by given parameters.
     *
     * @param Area $cropArea
     * @param array $params
     * @param ?FileInterface $image
     *
     * @return mixed
     */
    protected function getCropValue(Area $cropArea, array $params, ?FileInterface $image = null)
    {
        if ($cropArea->isEmpty() && !empty($params['crop'])) {
            return $params['crop'];
        }

        if (!$cropArea->isEmpty() && $image !== null) {
            return $cropArea->makeAbsoluteBasedOnFile($image);
        }

        return null;
    }

    /**
     * Summary of getImageService
     * @return ImageService
     */
    protected static function getImageService(): ImageService
    {
        return GeneralUtility::makeInstance(ImageService::class);
    }
}
