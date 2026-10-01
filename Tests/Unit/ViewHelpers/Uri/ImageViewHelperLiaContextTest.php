<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\ViewHelpers\Uri;

use LIA\LiaImageserver\Domain\Model\File;
use LIA\LiaImageserver\Tests\Unit\Fixtures\ImageServiceDoubleRegistration;
use LIA\LiaImageserver\Tests\Unit\Fixtures\RecordingImageService;
use LIA\LiaImageserver\ViewHelpers\Uri\ImageViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * D7/D8 wiring of `lim:uri.image`: `liaContext` argument merged into the
 * instruction set, `_maxRenderWidth` = requested width (absent without one).
 */
final class ImageViewHelperLiaContextTest extends UnitTestCase
{
    use ImageServiceDoubleRegistration;

    protected bool $resetSingletonInstances = true;

    #[Test]
    public function renderingCarriesLiaContextWithRequestedWidth(): void
    {
        $service = new RecordingImageService($this->createFile());
        $this->registerImageServiceDouble($service);
        $viewHelper = new ImageViewHelper();
        $viewHelper->setArguments($this->buildArguments([
            'width' => '400',
            'liaContext' => ['aiBadge' => '0'],
        ]));

        $uri = $viewHelper->render();

        self::assertSame('/processed/example.jpg', $uri);
        $liaInstructionSets = $service->liaInstructionSets->getArrayCopy();
        self::assertCount(1, $liaInstructionSets);
        self::assertSame(
            ['aiBadge' => '0', '_maxRenderWidth' => 400],
            $liaInstructionSets[0]['liaContext'],
        );
    }

    #[Test]
    public function renderingWithoutLiaContextAndWidthAddsNoContextKey(): void
    {
        $service = new RecordingImageService($this->createFile());
        $this->registerImageServiceDouble($service);
        $viewHelper = new ImageViewHelper();
        $viewHelper->setArguments($this->buildArguments([]));

        $viewHelper->render();

        $liaInstructionSets = $service->liaInstructionSets->getArrayCopy();
        self::assertCount(1, $liaInstructionSets);
        self::assertArrayNotHasKey('liaContext', $liaInstructionSets[0]);
    }

    private function buildArguments(array $overrides): array
    {
        return array_replace([
            'src' => '42',
            'image' => null,
            'treatIdAsReference' => true,
            'crop' => null,
            'cropVariant' => 'default',
            'fileExtension' => null,
            'width' => null,
            'height' => null,
            'minWidth' => null,
            'minHeight' => null,
            'maxWidth' => null,
            'maxHeight' => null,
            'absolute' => false,
            'imageServerOptions' => [],
            'passThrough' => false,
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
