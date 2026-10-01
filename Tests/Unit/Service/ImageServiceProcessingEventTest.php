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
use LIA\LiaImageserver\Event\ModifyProcessingInstructionsEvent;
use LIA\LiaImageserver\Helper;
use LIA\LiaImageserver\Service\ImageService;
use LIA\LiaImageserver\Tests\Unit\Fixtures\HelperFake;
use LIA\LiaImageserver\Tests\Unit\Fixtures\RecordingEventDispatcher;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Contract of the ModifyProcessingInstructionsEvent dispatch (D14):
 * the event fires once per rendering call at the TOP of BOTH
 * applyProcessingInstructions() and applyProcessingInstructionsLia(),
 * covers all four exit paths, and owns the liaContext strip.
 */
final class ImageServiceProcessingEventTest extends UnitTestCase
{
    private const OVERLAY = [
        'imageIdentifier' => '/_ai_badges/abc123/ai--black-transparent.svg',
        'align' => 'bottom-left',
    ];

    protected bool $resetSingletonInstances = true;

    /**
     * @var list<array>
     */
    private array $processedConfigurations = [];

    #[Test]
    public function dispatchesEventOnImgixClonePathAndAppliesListenerMutations(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);
        $file = $this->createFile();
        $dispatcher = $this->queueDispatcher($this->createOverlayListener());

        $result = $service->applyProcessingInstructionsLia($file, ['w' => '400', 'liaContext' => ['aiBadge' => '0']]);

        self::assertCount(1, $dispatcher->dispatchedEvents);
        self::assertInstanceOf(ModifyProcessingInstructionsEvent::class, $dispatcher->dispatchedEvents[0]);
        self::assertInstanceOf(File::class, $result);
        self::assertNotSame($file, $result);
        $cloneInstructions = $result->getProcessingInstructions();
        self::assertSame(self::OVERLAY, $cloneInstructions['overlay']);
        self::assertArrayNotHasKey('liaContext', $cloneInstructions);
        self::assertNull($file->getProcessingInstructions());
    }

    #[Test]
    public function dispatchesEventOnSkipToLocalPath(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: true);
        $file = $this->createFile();
        $dispatcher = $this->queueDispatcher($this->createOverlayListener());

        $result = $service->applyProcessingInstructionsLia(
            $file,
            ['w' => '400', 'h' => null, 'ar' => null, 'fit' => null, 'liaContext' => ['aiBadge' => '0']],
        );

        self::assertCount(1, $dispatcher->dispatchedEvents);
        self::assertInstanceOf(ProcessedFile::class, $result);
        self::assertCount(1, $this->processedConfigurations);
        $configuration = $this->processedConfigurations[0];
        self::assertSame(self::OVERLAY, $configuration['overlay']);
        self::assertArrayNotHasKey('liaContext', $configuration);
    }

    #[Test]
    public function dispatchesEventOnPassThroughToLocalPath(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);
        $file = $this->createFile();
        $dispatcher = $this->queueDispatcher($this->createOverlayListener());

        $result = $service->applyProcessingInstructionsLia(
            $file,
            ['w' => '400', 'liaContext' => ['aiBadge' => '0']],
            true,
        );

        self::assertCount(1, $dispatcher->dispatchedEvents);
        self::assertInstanceOf(ProcessedFile::class, $result);
        self::assertCount(1, $this->processedConfigurations);
        $configuration = $this->processedConfigurations[0];
        self::assertSame(self::OVERLAY, $configuration['overlay']);
        self::assertArrayNotHasKey('liaContext', $configuration);
        self::assertNull($file->getProcessingInstructions());
    }

    #[Test]
    public function dispatchesEventOnNonImageServerPathOfLiaMethod(): void
    {
        $service = $this->createImageService(isImageServerImage: false, isSkipImageServer: false);
        $file = $this->createFile();
        $dispatcher = $this->queueDispatcher($this->createOverlayListener());

        $service->applyProcessingInstructionsLia($file, ['w' => '400', 'liaContext' => ['aiBadge' => '0']]);

        self::assertCount(1, $dispatcher->dispatchedEvents);
        self::assertCount(1, $this->processedConfigurations);
        $configuration = $this->processedConfigurations[0];
        self::assertSame(self::OVERLAY, $configuration['overlay']);
        self::assertArrayNotHasKey('liaContext', $configuration);
    }

    #[Test]
    public function dispatchesEventOnImageServerPathOfApplyProcessingInstructions(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);
        $file = $this->createFile();
        $dispatcher = $this->queueDispatcher($this->createOverlayListener());

        $result = $service->applyProcessingInstructions($file, ['w' => '400', 'liaContext' => ['aiBadge' => '0']]);

        self::assertCount(1, $dispatcher->dispatchedEvents);
        self::assertInstanceOf(ProcessedFile::class, $result);
        self::assertCount(1, $this->processedConfigurations);
        $configuration = $this->processedConfigurations[0];
        self::assertSame(self::OVERLAY, $configuration['overlay']);
        self::assertArrayNotHasKey('liaContext', $configuration);
        $attachedInstructions = $file->getProcessingInstructions();
        self::assertSame(self::OVERLAY, $attachedInstructions['overlay']);
        self::assertArrayNotHasKey('liaContext', $attachedInstructions);
    }

    #[Test]
    public function dispatchesEventOnNonImageServerPathOfApplyProcessingInstructions(): void
    {
        $service = $this->createImageService(isImageServerImage: false, isSkipImageServer: false);
        $file = $this->createFile();
        $dispatcher = $this->queueDispatcher($this->createOverlayListener());

        $service->applyProcessingInstructions($file, ['w' => '400', 'liaContext' => ['aiBadge' => '0']]);

        self::assertCount(1, $dispatcher->dispatchedEvents);
        self::assertCount(1, $this->processedConfigurations);
        $configuration = $this->processedConfigurations[0];
        self::assertSame(self::OVERLAY, $configuration['overlay']);
        self::assertArrayNotHasKey('liaContext', $configuration);
    }

    #[Test]
    public function exposesLiaContextToListenersReadOnly(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);
        $file = $this->createFile();
        $capturedContext = null;
        $this->queueDispatcher(static function (ModifyProcessingInstructionsEvent $event) use (&$capturedContext): void {
            $capturedContext = $event->getContext();
        });

        $service->applyProcessingInstructionsLia($file, ['w' => '400', 'liaContext' => ['aiBadge' => '0']]);

        self::assertSame(['aiBadge' => '0'], $capturedContext);
    }

    #[Test]
    public function providesEmptyContextWhenNoLiaContextIsGiven(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);
        $file = $this->createFile();
        $capturedContext = null;
        $this->queueDispatcher(static function (ModifyProcessingInstructionsEvent $event) use (&$capturedContext): void {
            $capturedContext = $event->getContext();
        });

        $service->applyProcessingInstructionsLia($file, ['w' => '400']);

        self::assertSame([], $capturedContext);
    }

    #[Test]
    public function throwsOnNonArrayLiaContext(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);
        $file = $this->createFile();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785394800);

        $service->applyProcessingInstructionsLia($file, ['w' => '400', 'liaContext' => 'aiBadge=0']);
    }

    #[Test]
    public function unwrapsFileReferenceBeforeDispatch(): void
    {
        $service = $this->createImageService(isImageServerImage: false, isSkipImageServer: false);
        $file = $this->createFile();
        $fileReference = self::createStub(FileReference::class);
        $fileReference->method('getOriginalFile')->willReturn($file);
        $capturedImage = null;
        $this->queueDispatcher(static function (ModifyProcessingInstructionsEvent $event) use (&$capturedImage): void {
            $capturedImage = $event->getImage();
        });

        $service->applyProcessingInstructionsLia($fileReference, ['w' => '400']);

        self::assertSame($file, $capturedImage);
    }

    #[Test]
    public function dispatchesBeforeInstructionTransformation(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: true);
        $file = $this->createFile();
        $capturedInstructions = null;
        $this->queueDispatcher(static function (ModifyProcessingInstructionsEvent $event) use (&$capturedInstructions): void {
            $capturedInstructions = $event->getInstructions();
        });

        $service->applyProcessingInstructionsLia(
            $file,
            ['w' => '400', 'h' => null, 'ar' => null, 'fit' => null],
        );

        self::assertIsArray($capturedInstructions);
        self::assertArrayNotHasKey('width', $capturedInstructions);
        self::assertSame('400', $this->processedConfigurations[0]['width']);
    }

    #[Test]
    public function toleratesRepeatedDispatchForTheSameRendering(): void
    {
        $service = $this->createImageService(isImageServerImage: true, isSkipImageServer: false);
        $file = $this->createFile();
        $dispatcher = new RecordingEventDispatcher($this->createOverlayListener());
        GeneralUtility::addInstance(EventDispatcherInterface::class, $dispatcher);
        GeneralUtility::addInstance(EventDispatcherInterface::class, $dispatcher);

        $firstResult = $service->applyProcessingInstructionsLia($file, ['w' => '400', 'liaContext' => ['aiBadge' => '1']]);
        self::assertInstanceOf(File::class, $firstResult);
        $secondResult = $service->applyProcessingInstructionsLia($file, $firstResult->getProcessingInstructions());

        self::assertCount(2, $dispatcher->dispatchedEvents);
        self::assertInstanceOf(File::class, $secondResult);
        self::assertSame($firstResult->getProcessingInstructions(), $secondResult->getProcessingInstructions());
        self::assertSame(self::OVERLAY, $secondResult->getProcessingInstructions()['overlay']);
    }

    private function createImageService(bool $isImageServerImage, bool $isSkipImageServer): ImageService
    {
        GeneralUtility::addInstance(Helper::class, new HelperFake($isImageServerImage, $isSkipImageServer));

        return new ImageService(self::createStub(ResourceFactory::class));
    }

    private function createFile(): File
    {
        $storage = self::createStub(ResourceStorage::class);
        $storage->method('processFile')->willReturnCallback(
            function (FileInterface $file, $context, array $configuration): ProcessedFile {
                $this->processedConfigurations[] = $configuration;
                return $this->createStub(ProcessedFile::class);
            },
        );

        return new File(
            ['uid' => 42, 'identifier' => '/user_upload/example.jpg', 'name' => 'example.jpg', 'extension' => 'jpg'],
            $storage,
            ['title' => 'Example'],
        );
    }

    private function queueDispatcher(callable ...$listeners): RecordingEventDispatcher
    {
        $dispatcher = new RecordingEventDispatcher(...$listeners);
        GeneralUtility::addInstance(EventDispatcherInterface::class, $dispatcher);
        return $dispatcher;
    }

    /**
     * @return callable(ModifyProcessingInstructionsEvent): void
     */
    private function createOverlayListener(): callable
    {
        return static function (ModifyProcessingInstructionsEvent $event): void {
            $instructions = $event->getInstructions();
            $instructions['overlay'] = self::OVERLAY;
            $event->setInstructions($instructions);
        };
    }
}
