<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\ViewHelpers;

use LIA\LiaImageserver\Domain\Model\File;
use LIA\LiaImageserver\Helper;
use LIA\LiaImageserver\Tests\Unit\Fixtures\HelperFake;
use LIA\LiaImageserver\Tests\Unit\Fixtures\ImageServiceDoubleRegistration;
use LIA\LiaImageserver\Tests\Unit\Fixtures\ImageViewHelperFixture;
use LIA\LiaImageserver\Tests\Unit\Fixtures\RecordingImageService;
use LIA\LiaImageserver\ViewHelpers\ImageViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * D7/D8 wiring of `lim:image`: the `liaContext` argument reaches the
 * instruction set, and `_maxRenderWidth` reflects the largest requested
 * width of the call (sourceSets maximum in generateSrcsets(), the width
 * argument on the single-image path, absent without a width).
 */
final class ImageViewHelperLiaContextTest extends UnitTestCase
{
    use ImageServiceDoubleRegistration;

    protected bool $resetSingletonInstances = true;

    #[Test]
    public function srcsetGenerationInjectsCallWideMaxRenderWidth(): void
    {
        $sourceSets = [['w' => 320], ['w' => 1050], ['w' => 490]];
        $service = new RecordingImageService();
        $instancesNeeded = count($sourceSets);
        while ($instancesNeeded-- > 0) {
            $this->registerImageServiceDouble($service);
            GeneralUtility::addInstance(Helper::class, new HelperFake(true, false));
        }
        $viewHelper = new ImageViewHelperFixture();
        $viewHelper->setArguments($this->buildArguments([
            'sourceSets' => $sourceSets,
            'liaContext' => ['aiBadge' => '0'],
        ]));

        $srcSetUris = $viewHelper->callGenerateSrcsets(Area::createEmpty(), $this->createFile());

        self::assertCount(3, $srcSetUris);
        $liaInstructionSets = $service->liaInstructionSets->getArrayCopy();
        self::assertCount(3, $liaInstructionSets);
        foreach ($liaInstructionSets as $instructions) {
            self::assertSame(1050, $instructions['liaContext']['_maxRenderWidth']);
            self::assertSame('0', $instructions['liaContext']['aiBadge']);
        }
        self::assertSame(320, $liaInstructionSets[0]['w']);
    }

    #[Test]
    public function singleImageRenderingCarriesRequestedWidthAsMaxRenderWidth(): void
    {
        $service = new RecordingImageService($this->createFile());
        $this->registerImageServiceDouble($service);
        $viewHelper = new ImageViewHelper();
        $viewHelper->setArguments($this->buildArguments([
            'src' => '42',
            'treatIdAsReference' => true,
            'width' => '600c',
            'liaContext' => ['aiBadge' => '0'],
        ]));
        $viewHelper->initialize();

        $viewHelper->render();

        $liaInstructionSets = $service->liaInstructionSets->getArrayCopy();
        self::assertCount(1, $liaInstructionSets);
        self::assertSame(['aiBadge' => '0', '_maxRenderWidth' => 600], $liaInstructionSets[0]['liaContext']);
    }

    #[Test]
    public function renderingWithoutWidthLeavesMaxRenderWidthAbsent(): void
    {
        $service = new RecordingImageService($this->createFile());
        $this->registerImageServiceDouble($service);
        $viewHelper = new ImageViewHelper();
        $viewHelper->setArguments($this->buildArguments([
            'src' => '42',
            'treatIdAsReference' => true,
            'liaContext' => ['aiBadge' => '0'],
        ]));
        $viewHelper->initialize();

        $viewHelper->render();

        $liaInstructionSets = $service->liaInstructionSets->getArrayCopy();
        self::assertCount(1, $liaInstructionSets);
        self::assertSame(['aiBadge' => '0'], $liaInstructionSets[0]['liaContext']);
    }

    #[Test]
    public function renderingWithoutLiaContextAndWidthAddsNoContextKey(): void
    {
        $service = new RecordingImageService($this->createFile());
        $this->registerImageServiceDouble($service);
        $viewHelper = new ImageViewHelper();
        $viewHelper->setArguments($this->buildArguments([
            'src' => '42',
            'treatIdAsReference' => true,
        ]));
        $viewHelper->initialize();

        $viewHelper->render();

        $liaInstructionSets = $service->liaInstructionSets->getArrayCopy();
        self::assertCount(1, $liaInstructionSets);
        self::assertArrayNotHasKey('liaContext', $liaInstructionSets[0]);
    }

    private function buildArguments(array $overrides): array
    {
        return array_replace([
            'src' => '',
            'image' => null,
            'treatIdAsReference' => false,
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
            'base64' => false,
            'fetchPriority' => null,
            'imageServerOptions' => [],
            'objectFit' => null,
            'objectPosition' => null,
            'sizes' => [],
            'sourceSets' => [],
            'enableWebPSupport' => false,
            'respectImageWidth' => 0,
            'respectImageWidthClasses' => ['staticimage', 'u-respect-image-width'],
            'passThrough' => false,
            'loading' => 'lazy',
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
