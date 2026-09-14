<?php

/*
 * This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace LIA\LiaImageserver\ViewHelpers\Uri;

use LIA\LiaImageserver\Service\ImageService;
use LIA\LiaImageserver\ViewHelpers\LiaContextTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * This ViewHelper generate an uri for the given image.
 *
 * Examples
 * ========
 *
 * .. code-block:: html
 *
 *      <picture>
 *          <lim:uri.image
 *              treatIdAsReference="true"
 *              src="{image.uid}"
 *              alt="{image.alternative}"
 *              width="600"
 *              height="400"
 *              cropVariant="pasteHereYourCropVariant"
 *              imageServerOptions="{fm: 'pjpg', q: 'lossless'}"
 *              passThrough="true"
 *          />
 *      </picture>
 *
 * To use the cropping add a `m` to the `width` and the `height`. It looks like this.
 *
 * .. code-block:: html
 *
 *      <picture>
 *          <lim:uri.image
 *              treatIdAsReference="true"
 *              src="{image.uid}"
 *              alt="{image.alternative}"
 *              width="600m"
 *              height="400m"
 *              cropVariant="pasteHereYourCropVariant"
 *              imageServerOptions="{fm: 'pjpg', q: 'lossless'}"
 *              passThrough="true"
 *          />
 *      </picture>
 *
 * You can also use this ViewHelper as inline notation.
 *
 * .. code-block:: html
 *
 *      <picture>
 *          {lim:uri.image(treatIdAsReference: true, src: image.id, alt: image.alternative, width: '600c', height: '400c', cropVariant: 'pasteHereYourCropVariant', imageServerOptions: '{fm: "pjpg", q: "lossless"}', passThrough: true)}
 *      </picture>
 */
class ImageViewHelper extends AbstractViewHelper
{
    use LiaContextTrait;

    /**
     * Initialize this ViewHelper with all arguments.
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('imageServerOptions', 'array', 'Imageserver related options to be passed to. You can pass any valid Imageserver option. This options will overrule all other defined attributes', false, []);
        $this->registerArgument('passThrough', 'boolean', 'If you have to skip the imageserver set this argument to `true`.', false, false);
        $this->registerLiaContextArgument();

        // Arguments from ImageViewHelper wich is now final
        $this->registerArgument('src', 'string', 'Here goes the source of the image. It can be the uid of it or a relative or absolute path to the image.', false, '');
        $this->registerArgument('treatIdAsReference', 'bool', 'If you use an UID in the `src` attribute you have to set this argument to `true` to get an result.', false, false);
        $this->registerArgument('image', 'object', 'a FAL object (\\TYPO3\\CMS\\Core\\Resource\\File or \\TYPO3\\CMS\\Core\\Resource\\FileReference)');
        $this->registerArgument('crop', 'string|bool', 'If you want to deactivate cropping of the image, set this attribute to `true`');
        $this->registerArgument('cropVariant', 'string', 'If you have defined multiple cropping variants enter here the cropping variant you want to use for the cropping of your image.', false, 'default');
        $this->registerArgument('fileExtension', 'string', 'Custom file extension to use');

        $this->registerArgument('width', 'string', 'width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('height', 'string', 'height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('minWidth', 'int', 'minimum width of the image');
        $this->registerArgument('minHeight', 'int', 'minimum height of the image');
        $this->registerArgument('maxWidth', 'int', 'maximum width of the image');
        $this->registerArgument('maxHeight', 'int', 'maximum height of the image');
        $this->registerArgument('absolute', 'bool', 'Force absolute URL', false, false);
    }

    /**
     * Resizes the image (if required) and returns its path. If the image was not resized, the path will be equal to $src
     *
     * @return string
     *
     * @throws Exception
     */
    public function render(): string
    {
        $src = (string)$this->arguments['src'];
        $image = $this->arguments['image'];
        $treatIdAsReference = $this->arguments['treatIdAsReference'];
        $cropString = $this->arguments['crop'];
        $absolute = $this->arguments['absolute'];

        if (($src === '' && is_null($image)) || ($src !== '' && !is_null($image))) {
            throw new Exception('You must either specify a string src or a File object.', 1460976233);
        }

        $imageService = self::getImageService();

        try {
            $image = $imageService->getImage($src, $image, $treatIdAsReference);

            if ($cropString === null && $image->hasProperty('crop') && $image->getProperty('crop')) {
                $cropString = $image->getProperty('crop');
            }

            $cropVariantCollection = CropVariantCollection::create((string)$cropString);
            $cropVariant = $this->arguments['cropVariant'] ?: 'default';
            $cropArea = $cropVariantCollection->getCropArea($cropVariant);
            $processingInstructions = [
                'width' => $this->arguments['width'],
                'height' => $this->arguments['height'],
                'minWidth' => $this->arguments['minWidth'],
                'minHeight' => $this->arguments['minHeight'],
                'maxWidth' => $this->arguments['maxWidth'],
                'maxHeight' => $this->arguments['maxHeight'],
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
            ];

            ArrayUtility::mergeRecursiveWithOverrule($processingInstructions, $this->arguments['imageServerOptions']);
            $processingInstructions = $this->mergeLiaContextIntoInstructions($processingInstructions, (int)$this->arguments['width']);
            $processedImage = $imageService->applyProcessingInstructionsLia($image, $processingInstructions, $this->arguments['passThrough']);
            return $imageService->getImageUri($processedImage, $absolute);
        } catch (ResourceDoesNotExistException $exception) {
            GeneralUtility::makeInstance(LoggerInterface::class)->warning('Uri\\ImageViewHelper: file does not exist', ['exception' => $exception]);
        } catch (\UnexpectedValueException $exception) {
            GeneralUtility::makeInstance(LoggerInterface::class)->warning('Uri\\ImageViewHelper: file has been replaced with a folder', ['exception' => $exception]);
        } catch (\RuntimeException $exception) {
            GeneralUtility::makeInstance(LoggerInterface::class)->warning('Uri\\ImageViewHelper: file is outside of a storage', ['exception' => $exception]);
        } catch (\InvalidArgumentException $exception) {
            GeneralUtility::makeInstance(LoggerInterface::class)->warning('Uri\\ImageViewHelper: file storage does not exist', ['exception' => $exception]);
        }
        return '';
    }

    /**
     * Create and return an instance of the ImageService class.
     *
     * @return ImageService
     */
    protected static function getImageService(): ImageService
    {
        return GeneralUtility::makeInstance(ImageService::class);
    }
}
