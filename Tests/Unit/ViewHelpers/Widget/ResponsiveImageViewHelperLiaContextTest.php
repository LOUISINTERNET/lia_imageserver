<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\ViewHelpers\Widget;

use LIA\LiaImageserver\Domain\Model\File;
use LIA\LiaImageserver\Tests\Unit\Fixtures\RecordingImageService;
use LIA\LiaImageserver\ViewHelpers\Widget\ResponsiveImageViewHelper;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * D7 wiring of `lim:widget.responsiveImage`: the ViewHelper has NO
 * imageServerOptions argument — the `liaContext` argument is merged into
 * the instruction arrays flowing through its defaultOptions chain.
 */
final class ResponsiveImageViewHelperLiaContextTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private const SOURCE_SETS = [
        '(min-width: 768px)' => [
            'srcset' => [['w' => 640], ['w' => 1024]],
            'sizes' => '100vw',
            'default' => 1,
        ],
    ];

    #[Test]
    public function webPChainReceivesDefaultOptionsMergedWithLiaContext(): void
    {
        $service = new RecordingImageService($this->createFile());
        $viewHelper = $this->createViewHelper($service);
        $viewHelper->setArguments($this->buildArguments([
            'enableWebPSupport' => true,
            'liaContext' => ['aiBadge' => '0'],
        ]));

        $viewHelper->render();

        self::assertSame(
            [['fm' => 'jpg', 'liaContext' => ['aiBadge' => '0']]],
            $service->webPSourceSetInstructions->getArrayCopy(),
        );
        self::assertSame([], $service->defaultSourceSetInstructions->getArrayCopy());
    }

    #[Test]
    public function defaultChainReceivesDefaultOptionsMergedWithLiaContext(): void
    {
        $service = new RecordingImageService($this->createFile());
        $viewHelper = $this->createViewHelper($service);
        $viewHelper->setArguments($this->buildArguments([
            'enableWebPSupport' => false,
            'liaContext' => ['aiBadge' => '0'],
        ]));

        $viewHelper->render();

        self::assertSame(
            [['fm' => 'jpg', 'liaContext' => ['aiBadge' => '0']]],
            $service->defaultSourceSetInstructions->getArrayCopy(),
        );
    }

    #[Test]
    public function emptyLiaContextLeavesDefaultOptionsUntouched(): void
    {
        $service = new RecordingImageService($this->createFile());
        $viewHelper = $this->createViewHelper($service);
        $viewHelper->setArguments($this->buildArguments([
            'enableWebPSupport' => false,
        ]));

        $viewHelper->render();

        self::assertSame([['fm' => 'jpg']], $service->defaultSourceSetInstructions->getArrayCopy());
    }

    private function createViewHelper(RecordingImageService $service): ResponsiveImageViewHelper
    {
        $configurationManager = self::createStub(ConfigurationManager::class);
        $configurationManager->method('getConfiguration')->willReturn([]);
        GeneralUtility::setSingletonInstance(ConfigurationManager::class, $configurationManager);

        $view = self::createStub(ViewInterface::class);
        $view->method('render')->willReturn('');
        $viewFactory = self::createStub(ViewFactoryInterface::class);
        $viewFactory->method('create')->willReturn($view);

        $renderingContext = self::createStub(RenderingContextInterface::class);
        $renderingContext->method('hasAttribute')->willReturn(true);
        $renderingContext->method('getAttribute')->willReturn(self::createStub(ServerRequestInterface::class));

        $viewHelper = new ResponsiveImageViewHelper($viewFactory, $service);
        $viewHelper->setRenderingContext($renderingContext);
        return $viewHelper;
    }

    private function buildArguments(array $overrides): array
    {
        return array_replace([
            'customWidgetId' => null,
            'storeSession' => true,
            'src' => '42',
            'treatIdAsReference' => true,
            'image' => null,
            'defaultOptions' => ['fm' => 'jpg'],
            'sourceSets' => self::SOURCE_SETS,
            'enableWebPSupport' => false,
            'class' => null,
            'objectPositionLeft' => 'center',
            'objectPositionTop' => 'center',
            'objectFit' => '',
            'respectImageWidth' => 0,
            'respectImageWidthClasses' => ['staticimage', 'u-respect-image-width'],
            'loading' => null,
            'dimensions' => false,
            'fetchPriority' => null,
            'liaContext' => [],
        ], $overrides);
    }

    private function createFile(): File
    {
        return new File(
            ['uid' => 42, 'identifier' => '/user_upload/example.jpg', 'name' => 'example.jpg', 'extension' => 'jpg', 'width' => 1200, 'height' => 800],
            self::createStub(ResourceStorage::class),
            ['title' => 'Example', 'alternative' => 'Example alternative text'],
        );
    }
}
