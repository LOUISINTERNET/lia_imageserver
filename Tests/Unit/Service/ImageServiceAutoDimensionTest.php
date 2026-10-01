<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Service;

use LIA\LiaImageserver\Domain\Model\File;
use LIA\LiaImageserver\Helper;
use LIA\LiaImageserver\Service\ImageService;
use LIA\LiaImageserver\Tests\Unit\Fixtures\HelperFake;
use LIA\LiaImageserver\Tests\Unit\Fixtures\RecordingEventDispatcher;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * `height: 'auto'` and `width: 'auto'` keep the ratio of the editor's crop (or of the whole
 * image) at the requested other side, so free or legacy crop ratios are not cut to the
 * template's shape.
 */
final class ImageServiceAutoDimensionTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @var list<array>
     */
    private array $processedConfigurations = [];

    #[Test]
    public function derivesTheHeightFromTheCropAreaForImageServerFiles(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $result = $service->applyProcessingInstructionsLia(
            $this->createFile(width: 4000, height: 3000),
            ['width' => '1055', 'height' => 'auto', 'crop' => new Area(100, 200, 1528, 1868)],
        );

        self::assertInstanceOf(File::class, $result);
        self::assertSame('1290', $result->getProcessingInstructions()['height']);
        self::assertSame('1055', $result->getProcessingInstructions()['width']);
    }

    #[Test]
    public function derivesTheHeightFromTheWholeImageWithoutACrop(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $result = $service->applyProcessingInstructionsLia(
            $this->createFile(width: 2000, height: 1500),
            ['width' => '800', 'height' => 'auto', 'crop' => null],
        );

        self::assertInstanceOf(File::class, $result);
        self::assertSame('600', $result->getProcessingInstructions()['height']);
    }

    #[Test]
    public function derivesTheHeightForLocallyProcessedFiles(): void
    {
        $service = $this->createImageService(isImageServerImage: false, isSkipImageServer: false);

        $service->applyProcessingInstructionsLia(
            $this->createFile(width: 2000, height: 1000),
            ['width' => '740', 'height' => 'auto'],
        );

        self::assertCount(1, $this->processedConfigurations);
        self::assertSame('740m', $this->processedConfigurations[0]['width']);
        self::assertSame('370m', $this->processedConfigurations[0]['height']);
    }

    #[Test]
    public function leavesANumericHeightUntouched(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $result = $service->applyProcessingInstructionsLia(
            $this->createFile(width: 4000, height: 3000),
            ['width' => '1055', 'height' => '593', 'crop' => new Area(100, 200, 1528, 1868)],
        );

        self::assertInstanceOf(File::class, $result);
        self::assertSame('593', $result->getProcessingInstructions()['height']);
    }

    #[Test]
    public function throwsWhenTheWidthIsNotNumeric(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1790842801);

        $service->applyProcessingInstructionsLia(
            $this->createFile(width: 2000, height: 1000),
            ['width' => '600c', 'height' => 'auto'],
        );
    }

    #[Test]
    public function throwsWhenTheImageHasNoDimensions(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1790842802);

        $service->applyProcessingInstructionsLia(
            $this->createFile(width: 0, height: 0),
            ['width' => '600', 'height' => 'auto'],
        );
    }

    #[Test]
    public function derivesTheWidthFromTheCropAreaForImageServerFiles(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $result = $service->applyProcessingInstructionsLia(
            $this->createFile(width: 4000, height: 3000),
            ['width' => 'auto', 'height' => '1290', 'crop' => new Area(100, 200, 1528, 1868)],
        );

        self::assertInstanceOf(File::class, $result);
        self::assertSame('1055', $result->getProcessingInstructions()['width']);
        self::assertSame('1290', $result->getProcessingInstructions()['height']);
    }

    #[Test]
    public function derivesTheWidthFromTheWholeImageWithoutACrop(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $result = $service->applyProcessingInstructionsLia(
            $this->createFile(width: 2000, height: 1500),
            ['width' => 'auto', 'height' => '600', 'crop' => null],
        );

        self::assertInstanceOf(File::class, $result);
        self::assertSame('800', $result->getProcessingInstructions()['width']);
    }

    #[Test]
    public function derivesTheWidthForLocallyProcessedFiles(): void
    {
        $service = $this->createImageService(isImageServerImage: false, isSkipImageServer: false);

        $service->applyProcessingInstructionsLia(
            $this->createFile(width: 2000, height: 1000),
            ['width' => 'auto', 'height' => '370'],
        );

        self::assertCount(1, $this->processedConfigurations);
        self::assertSame('740m', $this->processedConfigurations[0]['width']);
        self::assertSame('370m', $this->processedConfigurations[0]['height']);
    }

    #[Test]
    public function throwsWhenTheHeightIsNotNumeric(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1790842801);

        $service->applyProcessingInstructionsLia(
            $this->createFile(width: 2000, height: 1000),
            ['width' => 'auto', 'height' => '400c'],
        );
    }

    #[Test]
    public function throwsWhenBothSidesAreAuto(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1790842804);

        $service->applyProcessingInstructionsLia(
            $this->createFile(width: 2000, height: 1000),
            ['width' => 'auto', 'height' => 'auto'],
        );
    }

    private function createImageService(bool $isImageServerImage, bool $isSkipImageServer): ImageService
    {
        GeneralUtility::addInstance(Helper::class, new HelperFake($isImageServerImage, $isSkipImageServer));
        GeneralUtility::addInstance(EventDispatcherInterface::class, new RecordingEventDispatcher());

        return new ImageService(self::createStub(ResourceFactory::class));
    }

    private function createFile(int $width, int $height): File
    {
        $storage = self::createStub(ResourceStorage::class);
        $storage->method('processFile')->willReturnCallback(
            function (FileInterface $file, $context, array $configuration): ProcessedFile {
                $this->processedConfigurations[] = $configuration;
                return $this->createStub(ProcessedFile::class);
            },
        );

        return new File(
            ['uid' => 42, 'identifier' => '/user_upload/example.jpg', 'name' => 'example.jpg', 'extension' => 'jpg', 'width' => $width, 'height' => $height],
            $storage,
            ['title' => 'Example'],
        );
    }
}
