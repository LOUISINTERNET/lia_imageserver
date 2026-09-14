<?php

/*
 * This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace LIA\LiaImageserver\ViewHelpers\Widget;

use LIA\LiaImageserver\Service\ImageService;
use LIA\LiaImageserver\ViewHelpers\LiaContextTrait;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * This ViewHelper render a responsive image with given configuration. It also support webp, media queries and art queries.
 *
 * Examples
 * ========
 *
 * .. code-block:: html
 *
 *      <picture>
 *          <lim:widget.responsiveImage
 *              image="{image}"
 *              enableWebPSupport="true"
 *              defaultOptions="{'fm': 'jpg'}"
 *              sourceSets="{
 *                  '(max-width: 767px)':
 *                  {
 *                      srcset: {0: {w:320},1: {w:480},2: {w:560},3: {w:640}},
 *                      sizes: 'calc(100vw - 30px)',
 *                      default: 1,
 *                      options: {fm: 'png'},
 *                      cropVariant: 'tablet'
 *                  },
 *                  '(min-width: 768px)':
 *                  {
 *                      srcset: {0: {w:640},1: {w:768},2: {w:900},3: {w:1024}},
 *                      sizes: '100vw',
 *                      cropVariant: 'smartphone'
 *                  }
 *              }"
 *          />
 *      </picture>
 *
 * Here an example if you have to use the src attribute.
 *
 * .. code-block:: html
 *
 *      <picture>
 *          <lim:widget.responsiveImage
 *              src="{image.uid}"
 *              enableWebPSupport="true"
 *              treatIdAsReference="1"
 *              class="css-class-on-image"
 *              objectFit="cover"
 *              objectPositionLeft="center"
 *              objectPositionTop="top"
 *              defaultOptions="{'fm': 'jpg', fit: 'crop'}"
 *              sourceSets="{
 *                  '(max-width: 767px)':
 *                  {
 *                      srcset: {0: {w:320,h:350,fm:'jpeg'},1: {w:480,h:525},2: {w:560,h:613},3: {w:640,h:700}},
 *                      sizes: 'calc(100vw - 30px)',
 *                      default: 1,
 *                      options: {fm: 'pjpg', crop: 'left,top'}
 *                  },
 *                  '(min-width: 768px) and (max-width: 1024px) and (min-height: 767px)':
 *                  {
 *                      srcset: {0: {w:640,h:475},1: {w:768,h:570},2: {w:900,h:668},3: {w:1024,h:760}},
 *                      sizes: '100vw',
 *                      options: {fm: 'pjpg', crop: 'right,bottom'}
 *                  }
 *              }"
 *          />
 *      </picture>
 *
 * .. attention::
 *
 *      Inline notation is also possible. But (!) the syntax can get messy if you have to deal with nested arrays in the inline notation markup.
 *      This ViewHelper makes heavy use of nested array for the `sourceSets` attribute. Consider it as best practice to store your needed sourceSets in fluid variable via `f:variable`.
 *
 *  .. code-block::
 *
 *      <picture>
 *          <f:variablen
 *              name="responsiveImageSourceSets"
 *              value="{
 *                  '(max-width: 767px)':
 *                  {
 *                      srcset: {0: {w:320,h:350,fm:'jpeg'},1: {w:480,h:525},2: {w:560,h:613},3: {w:640,h:700}},
 *                      sizes: 'calc(100vw - 30px)',
 *                      default: 1,
 *                      options: {fm: 'pjpg', crop: 'left,top'}
 *                  },
 *                  '(min-width: 768px) and (max-width: 1024px) and (min-height: 767px)':
 *                  {
 *                      srcset: {0: {w:640,h:475},1: {w:768,h:570},2: {w:900,h:668},3: {w:1024,h:760}},
 *                      sizes: '100vw',
 *                      options: {fm: 'pjpg', crop: 'right,bottom'}
 *                  }
 *              }"
 *          />
 *          {lim:widget.responsiveImage(src: image.uid, enableWebPSupport: 'true', treatIdAsReference: 'true', class: 'css-class-on-image' objectFit: 'cover', objectPositionLeft: 'center', objectPositionTop: 'top', defaultOptions: '{fm: 'jpg', fit: 'crop'}' sourceSets: responsiveImageSourceSets)}
 *      </picture>
 */
class ResponsiveImageViewHelper extends AbstractViewHelper
{
    use LiaContextTrait;

    protected $settings = [];

    /**
     * @var bool $escapeOutput
     */
    protected $escapeOutput = false;

    /**
     * Initialize this ViewHelper with all arguments.
     */
    public function initializeArguments(): void
    {
        $this->registerArgument(
            'customWidgetId',
            'string',
            'extend the widget identifier with a custom widget id',
            false,
            null
        );

        $this->registerArgument(
            'storeSession',
            'bool',
            'Store the widgets session (utilizing a cookie).',
            false,
            true
        );

        $this->registerArgument('src', 'string', 'a path to a file, a combined FAL identifier or an uid (int). If $treatIdAsReference is set, the integer is considered the uid of the sys_file_reference record. If you already got a FAL object, consider using the $image parameter instead');
        $this->registerArgument('treatIdAsReference', 'bool', 'If you use an UID in the `src` attribute you have to set this argument to `true` to get an result.');
        $this->registerArgument('image', 'object', 'a FAL object (\\TYPO3\\CMS\\Core\\Resource\\File or \\TYPO3\\CMS\\Core\\Resource\\FileReference)');
        $this->registerArgument('defaultOptions', 'array', 'Options applied to all source sets', false, []);
        $this->registerArgument('sourceSets', 'array', 'Source set configuration', false);
        $this->registerArgument('enableWebPSupport', 'bool', 'Enable webP support', false, true);
        $this->registerArgument('class', 'string', 'Class of <img />', false);
        $this->registerArgument('objectPositionLeft', 'string', 'Polyfill object position left', false, 'center');
        $this->registerArgument('objectPositionTop', 'string', 'Polyfill object position top', false, 'center');
        $this->registerArgument('objectFit', 'string', 'Polyfill object fit mode', false, '');
        $this->registerArgument('respectImageWidth', 'int', 'If set source-set definitions will be processed to ensure not to exceed the given image width', false, 0);
        $this->registerArgument('respectImageWidthClasses', 'array', 'If respectImageWidh is set, this classes will be applied to the IMG tag', false, ['staticimage', 'u-respect-image-width']);
        $this->registerArgument('loading', 'string', 'Native lazy-loading for images property. Can be "lazy", "eager" or "auto"', false);
        $this->registerArgument('dimensions', 'bool', 'Set height and width dynamically', false, false);
        $this->registerArgument('fetchPriority', 'string', 'A string representing the priority hint. Possible values are:. "high", "low" or "auto"', false);
        $this->registerLiaContextArgument();
    }

    /**
     * Default class constructor.
     */
    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
        private readonly ImageService $imageService,
    ) {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $extConfig = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT,
            'lia_imageserver'
        );
        $this->settings = $extConfig['plugin.']['tx_liaimageserver.']['settings.'] ?? [];
    }

    /**
     * @return array
     */
    protected function processCssClasses(): array
    {
        $cssClass = [];

        if ($this->arguments['class']) {
            $cssClass[] = $this->arguments['class'];
        }

        if ($this->arguments['objectFit']) {
            $cssClass[] = sprintf('u-%s u-cover-%s-%s', $this->arguments['objectFit'], $this->arguments['objectPositionLeft'], $this->arguments['objectPositionTop']);
        }

        if ($this->imageService->hasRespectImageWidth($this->arguments['respectImageWidth'])) {
            $cssClass[] = implode(' ', $this->arguments['respectImageWidthClasses']);
        }
        return $cssClass;
    }

    /**
     * @param string $loading
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getLazyLoading($loading): string
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
     * Render the image by using the standalone view.
     *
     * @return string
     */
    public function render(): string
    {
        if (empty($this->arguments['treatIdAsReference'])) {
            $this->arguments['treatIdAsReference'] = false;
        }

        if (empty($this->arguments['src']) && is_callable([$this->arguments['image'], 'getPublicUrl'])) {
            $this->arguments['src'] = $this->arguments['image']->getPublicUrl();
        }

        $image = $this->imageService->getImage($this->arguments['src'], $this->arguments['image'], $this->arguments['treatIdAsReference']);

        $processedConfig = [
            'meta' => [
                'alt' => $image->getProperty('alternative'),
                'title' => $image->getProperty('title'),
                'loading' => $this->getLazyLoading($this->arguments['loading']),
            ],
        ];

        $processingInstructions = $this->mergeLiaContextIntoInstructions($this->arguments['defaultOptions'], 0);

        if ($this->arguments['enableWebPSupport']) {
            $processedConfig = $this->imageService->processWebPSourceSets($image, $processedConfig, $this->arguments['sourceSets'], $processingInstructions, $this->arguments['respectImageWidth']);
        } else {
            $processedConfig = $this->imageService->processDefaultSourceSets($image, $processedConfig, $this->arguments['sourceSets'], $processingInstructions, $this->arguments['respectImageWidth']);
        }
        $processedConfig['class'] = implode(' ', $this->processCssClasses());

        if ($this->imageService->hasRespectImageWidth($this->arguments['respectImageWidth']) && isset($processedConfig['default'])) {
            $respectImageWidth = $this->arguments['respectImageWidth'];
            $processedConfig['default']['src'] = $respectImageWidth;
        }

        $view = $this->createCustomView();

        $view->assign('config', $processedConfig);
        $view->assign('widgetConfig', $this->arguments);

        if ($this->arguments['dimensions'] == true) {
            if ($image->hasProperty('width')) {
                $view->assign('width', $image->getProperty('width'));
            }
            if ($image->hasProperty('height')) {
                $view->assign('height', $image->getProperty('height'));
            }
        }

        if (Environment::getContext()->isDevelopment()) {
            $view->assign('devPublicUrl', $image->getPublicUrl());
        }

        if ($this->hasArgument('fetchPriority')) {
            $fetchPriority = in_array($this->arguments['fetchPriority'], ['low', 'high', 'auto']) ? $this->arguments['fetchPriority'] : 'auto';
            $view->assign('fetchPriority', $fetchPriority);
        }

        return $view->render();
    }

    /**
     * recieves a view with custom etemplate path
     *
     * @return ViewInterface|null
     */
    private function createCustomView(): ?ViewInterface
    {
        if (!$this->renderingContext->hasAttribute(ServerRequestInterface::class)) {
            return null;
        }
        $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
        $templatepath = $this->settings['responsiveImageViewHelperTemplatePath'] ?? null;

        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: [$templatepath],
            partialRootPaths: [],
            layoutRootPaths: [],
            request: $request,
        );
        return $this->viewFactory->create($viewFactoryData);
    }
}
