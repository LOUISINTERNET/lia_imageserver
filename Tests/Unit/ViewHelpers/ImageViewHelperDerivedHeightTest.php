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
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Since 2.3.5 generateSrcsets() derives `h` from the ViewHelper's width/height, so every
 * candidate keeps the ratio of the main src. How the image server fits the image into that
 * size is the decision of the backend middleware (lia_middleware_imgix requests fit=crop):
 * this extension passes the requested size only and adds no fit of its own.
 */
final class ImageViewHelperDerivedHeightTest extends UnitTestCase
{
    use ImageServiceDoubleRegistration;

    protected bool $resetSingletonInstances = true;

    #[Test]
    public function derivedHeightIsPassedWithoutAFitForImageServerFiles(): void
    {
        $instructions = $this->generateSrcsetInstructions(isImageServerImage: true, imageServerOptions: []);

        self::assertSame(750, $instructions['h']);
        self::assertArrayNotHasKey('fit', $instructions);
    }

    #[Test]
    public function explicitFitFromTheTemplateIsKept(): void
    {
        $instructions = $this->generateSrcsetInstructions(isImageServerImage: true, imageServerOptions: ['fit' => 'max']);

        self::assertSame(750, $instructions['h']);
        self::assertSame('max', $instructions['fit']);
    }

    #[Test]
    public function locallyProcessedFilesGetTheDerivedHeightWithoutAFit(): void
    {
        $instructions = $this->generateSrcsetInstructions(isImageServerImage: false, imageServerOptions: []);

        self::assertSame(750, $instructions['height']);
        self::assertArrayNotHasKey('fit', $instructions);
    }

    #[Test]
    public function derivedHeightIsPassedWithoutAFitForExternalImages(): void
    {
        $service = $this->registerRecordingImageService();
        $viewHelper = $this->createViewHelper(imageServerOptions: []);

        $viewHelper->callGenerateSrcsetsForExternalImage(Area::createEmpty(), 'https://example.com/portrait.jpg');

        $externalImageArguments = $service->externalImageArguments->getArrayCopy();
        self::assertCount(1, $externalImageArguments);
        self::assertSame(750, $externalImageArguments[0]['h']);
        self::assertArrayNotHasKey('fit', $externalImageArguments[0]);
    }

    private function generateSrcsetInstructions(bool $isImageServerImage, array $imageServerOptions): array
    {
        $service = $this->registerRecordingImageService();
        GeneralUtility::addInstance(Helper::class, new HelperFake($isImageServerImage, false));
        $viewHelper = $this->createViewHelper($imageServerOptions);

        $viewHelper->callGenerateSrcsets(Area::createEmpty(), $this->createFile());

        $liaInstructionSets = $service->liaInstructionSets->getArrayCopy();
        self::assertCount(1, $liaInstructionSets);
        return $liaInstructionSets[0];
    }

    private function registerRecordingImageService(): RecordingImageService
    {
        $service = new RecordingImageService();
        $this->registerImageServiceDouble($service);
        return $service;
    }

    private function createViewHelper(array $imageServerOptions): ImageViewHelperFixture
    {
        $viewHelper = new ImageViewHelperFixture();
        $viewHelper->setArguments([
            'width' => '400',
            'height' => '300',
            'imageServerOptions' => $imageServerOptions,
            'sourceSets' => [['w' => 1000]],
            'respectImageWidth' => 0,
            'passThrough' => false,
            'absolute' => false,
            'liaContext' => [],
        ]);
        return $viewHelper;
    }

    private function createFile(): File
    {
        return new File(
            ['uid' => 42, 'identifier' => '/user_upload/portrait.jpg', 'name' => 'portrait.jpg', 'extension' => 'jpg', 'width' => 800, 'height' => 1200],
            self::createStub(ResourceStorage::class),
            ['title' => 'Portrait', 'alternative' => 'Portrait alternative text'],
        );
    }
}
