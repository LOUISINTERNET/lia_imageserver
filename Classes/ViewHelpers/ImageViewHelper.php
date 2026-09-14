<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\ViewHelpers;

use LIA\LiaImageserver\Domain\Model\File;
use LIA\LiaImageserver\Helper;
use LIA\LiaImageserver\Service\ImageService;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

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
    /**
     * @var string $tagName
     */
    protected $tagName = 'img';

    /**
     * @var \TYPO3\CMS\Extbase\Service\ImageService $imageService;
     */
    protected \TYPO3\CMS\Extbase\Service\ImageService $imageService;

    /**
     * Default constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->imageService = GeneralUtility::makeInstance(ImageService::class);
    }

    /**
     * Initialize this ViewHelper with all arguments.
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('imageServerOptions', 'array', 'Imageserver related options to be passed to. You can pass any valid Imageserver option. This options will overrule all other defined attributes', false, []);
        $this->registerArgument('objectFit', 'string', 'Polyfill object fit mode', false);
        $this->registerArgument('objectPosition', 'string', 'Polyfill object position', false);
        $this->registerArgument('sizes', 'array', 'Define here the sizes for the diffrent viewports.', false, []);
        $this->registerArgument('sourceSets', 'array', 'Source set configuration, make sure you always provide a default source set', false, []);
        $this->registerArgument('enableWebPSupport', 'boolean', 'Enable image webp support', false, false);
        $this->registerArgument('respectImageWidth', 'int', 'If set source-set definitions will be processed to ensure not to exceed the given image width', false, 0);
        $this->registerArgument('respectImageWidthClasses', 'array', 'If respectImageWidh is set, this classes will be applied to the IMG tag', false, ['staticimage', 'u-respect-image-width']);
        $this->registerArgument('passThrough', 'boolean', 'If you have to skip the imageserver set this argument to `true`.', false, false);

        // Arguments from ImageViewHelper wich is now final
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('alt', 'string', 'Specifies an alternate text for an image', false);
        $this->registerTagAttribute('ismap', 'string', 'Specifies an image as a server-side image-map. Rarely used. Look at usemap instead', false);
        $this->registerTagAttribute('longdesc', 'string', 'Specifies the URL to a document that contains a long description of an image', false);
        $this->registerTagAttribute('usemap', 'string', 'Specifies an image as a client-side image-map', false);
        $this->registerTagAttribute('loading', 'string', 'Native lazy-loading for images property. Can be "lazy", "eager" or "auto"', false);
        $this->registerTagAttribute('decoding', 'string', 'Provides an image decoding hint to the browser. Can be "sync", "async" or "auto"', false);
        $this->registerTagAttribute('fetchPriority', 'string', 'A string representing the priority hint. Possible values are:. "high", "low" or "auto"', false);

        $this->registerArgument('src', 'string', 'a path to a file, a combined FAL identifier or an uid (int). If $treatIdAsReference is set, the integer is considered the uid of the sys_file_reference record. If you already got a FAL object, consider using the $image parameter instead', false, '');
        $this->registerArgument('treatIdAsReference', 'bool', 'If you use an UID in the `src` attribute you have to set this argument to `true` to get an result.', false, false);
        $this->registerArgument('image', 'object', 'a FAL object (\\TYPO3\\CMS\\Core\\Resource\\File or \\TYPO3\\CMS\\Core\\Resource\\FileReference)');
        $this->registerArgument('crop', 'string|bool', 'overrule cropping of image (setting to FALSE disables the cropping set in FileReference)');
        $this->registerArgument('cropVariant', 'string', 'select a cropping variant, in case multiple croppings have been specified or stored in FileReference', false, 'default');
        $this->registerArgument('fileExtension', 'string', 'Custom file extension to use');

        $this->registerArgument('width', 'string', 'width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('height', 'string', 'height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('minWidth', 'int', 'minimum width of the image');
        $this->registerArgument('minHeight', 'int', 'minimum height of the image');
        $this->registerArgument('maxWidth', 'int', 'maximum width of the image');
        $this->registerArgument('maxHeight', 'int', 'maximum height of the image');
        $this->registerArgument('absolute', 'bool', 'Force absolute URL', false, false);

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

        if (($src === '' && is_null($this->arguments['image'])) || ($src !== '' && !is_null($this->arguments['image']))) {
            throw new \TYPO3Fluid\Fluid\Core\ViewHelper\Exception('You must either specify a string src or a File object.', 1382284106);
        }

        if ($this->hasArgument('fetchPriority')) {
            $fetchPriority = in_array($this->arguments['fetchPriority'], ['low', 'high', 'auto']) ? $this->arguments['fetchPriority'] : 'auto';
            $this->tag->addAttribute('fetchPriority', $fetchPriority);
        }

        if ($src !== '' && preg_match('/^(https?:)?\/\//', $src)) {
            if (\TYPO3\CMS\Core\Core\Environment::getContext()->isDevelopment()) {
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
            $image = $this->imageService->getImage($src, $this->arguments['image'], $this->arguments['treatIdAsReference']);

            if (\TYPO3\CMS\Core\Core\Environment::getContext()->isDevelopment()) {
                $this->tag->addAttribute('data-publicurl', $image->getPublicUrl());
            }

            $cropString = $this->getCropping($this->arguments['crop'], $image);
            // If the given parameter is empty the function getCropping returns an empty array.
            if (is_array($cropString)) {
                $cropString = json_encode($cropString);
            }

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

            $processedImage = $this->imageService->applyProcessingInstructionsLia($image, $processingInstructions, $this->arguments['passThrough']);
            $imageUri = $this->imageService->getImageUri($processedImage, $this->arguments['absolute'], $this->arguments['passThrough']);

            if (!$this->tag->hasAttribute('data-focus-area')) {
                $focusArea = $cropVariantCollection->getFocusArea($cropVariant);
                if (!$focusArea->isEmpty()) {
                    $this->tag->addAttribute('data-focus-area', $focusArea->makeAbsoluteBasedOnFile($image));
                }
            }

            $this->tag->addAttribute('src', $imageUri);
            $this->tag->addAttribute('width', $processedImage->getProperty('width'));
            $this->tag->addAttribute('height', $processedImage->getProperty('height'));
            $this->tag->addAttribute('loading', $this->getLazyLoading($this->arguments['loading']));

            $alt = $image->getProperty('alternative');
            $title = $image->getProperty('title');

            if (empty($this->arguments['alt'])) {
                $this->tag->addAttribute('alt', $alt);
            }
            if (empty($this->arguments['title']) && $title) {
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
        } catch (ResourceDoesNotExistException $e) {
            // thrown if file does not exist
        } catch (\UnexpectedValueException $e) {
            // thrown if a file has been replaced with a folder
        } catch (\RuntimeException $e) {
            // RuntimeException thrown if a file is outside of a storage
        } catch (\InvalidArgumentException $e) {
            // thrown if file storage does not exist
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
     * @param Area $cropArea
     * @param FILE $image
     * @param array $optionalParams
     * @param bool $externalImage
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function enableWebPSupport($cropArea, $image, $optionalParams = [], $externalImage = false)
    {
        $srcsetAttribute = implode(',' . PHP_EOL, $this->generateSrcsets($cropArea, $image, $optionalParams, $externalImage));
        $sizesAttribute = $this->tag->getAttribute('sizes');
        $webPSupportSourceTag = sprintf('<source srcset="%s" sizes="%s" type="image/webp" />', $srcsetAttribute, $sizesAttribute);
        $imgTag = $this->tag->render();

        return $webPSupportSourceTag . PHP_EOL . $imgTag;
    }

    /**
     * @param array $crop
     * @param FILE $image
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getCropping($crop = null, $image = null)
    {
        if (!empty($crop)) {
            return $crop;
        }
        if ($image != null && $image->hasProperty('crop') && $image->getProperty('crop')) {
            return $image->getProperty('crop');
        }
        return [];
    }

    /**
     * @param string $loading
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getLazyLoading($loading)
    {
        if ($loading == 'none') {
            return '';
        }
        if (empty($loading)) {
            return 'lazy';
        }
        return $loading;
    }

    /**
     * @param Area $cropArea
     * @param File $image
     * @param array $optionalParams
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function generateSrcsets(Area $cropArea, $image, $optionalParams = [], $externalImage = false)
    {
        $srcSetUris = [];
        $respectImageWidth = $this->arguments['respectImageWidth'];
        foreach ($this->arguments['sourceSets'] as $src) {
            $params = $this->arguments['imageServerOptions'];
            ArrayUtility::mergeRecursiveWithOverrule($params, $src);

            if (!isset($params['w'])) {
                $stringyfiedSrc = json_encode($src);
                throw new \Exception("No 'w' parameter given on sourceSet ($stringyfiedSrc). Please check your config.");
            }

            if ($respectImageWidth > 0) {
                $imageWidth = $respectImageWidth;
                $sourceSetWidth = $params['w'];

                if ($imageWidth <= $sourceSetWidth) {
                    continue;
                }
            }

            if ($externalImage) {
                $params['crop'] = $this->getCropValue($cropArea, $params);
                ArrayUtility::mergeRecursiveWithOverrule($params, $optionalParams);
                $srcSetUris[] = $this->imageService->getImageUriForExternalFile($image, $params) . " {$params['w']}w";
            } else {
                $helper = GeneralUtility::makeInstance(Helper::class);
                if ($helper->isSkipImageServer($image)) {
                    $params['width'] = $params['w'];
                }
                $params['crop'] = $this->getCropValue($cropArea, $params, $image);
                ArrayUtility::mergeRecursiveWithOverrule($params, $optionalParams);
                $processedImage = $this->imageService->applyProcessingInstructionsLia($image, $params, $this->arguments['passThrough']);
                $srcSetUris[] = $this->imageService->getImageUri($processedImage, $this->arguments['absolute'], $this->arguments['passThrough']) . " {$params['w']}w";
            }
        }
        return $srcSetUris;
    }

    /**
     * Get cropping values by given parameters.
     *
     * @param Area $cropArea
     * @param array $params
     * @param FileInterface $image
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

        return '';
    }
}
