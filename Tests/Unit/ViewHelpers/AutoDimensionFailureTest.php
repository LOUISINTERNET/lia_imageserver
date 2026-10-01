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
use LIA\LiaImageserver\Service\AutoDimensionException;
use LIA\LiaImageserver\Tests\Unit\Fixtures\AutoDimensionRejectingImageService;
use LIA\LiaImageserver\Tests\Unit\Fixtures\ImageServiceDoubleRegistration;
use LIA\LiaImageserver\ViewHelpers\ImageViewHelper;
use LIA\LiaImageserver\ViewHelpers\Uri\ImageViewHelper as UriImageViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * A width or height `auto` that cannot be derived is a template error: development shows it,
 * production logs it and renders the page without the image. It used to land in the catch
 * for a missing storage, whose logger could not even be created.
 */
final class AutoDimensionFailureTest extends UnitTestCase
{
    use ImageServiceDoubleRegistration;

    protected bool $resetSingletonInstances = true;

    protected bool $backupEnvironment = true;

    #[Test]
    public function imageThrowsInDevelopment(): void
    {
        $this->setApplicationContext('Development');
        $this->registerImageServiceDouble(new AutoDimensionRejectingImageService($this->createFile()));

        $this->expectException(AutoDimensionException::class);

        $this->imageViewHelper(['src' => '42', 'treatIdAsReference' => true, 'width' => 'auto', 'height' => 'auto'])->render();
    }

    #[Test]
    public function imageRendersWithoutTheImageInProduction(): void
    {
        $this->setApplicationContext('Production');
        $this->registerImageServiceDouble(new AutoDimensionRejectingImageService($this->createFile()));

        self::assertSame(
            '<img />',
            $this->imageViewHelper(['src' => '42', 'treatIdAsReference' => true, 'width' => 'auto', 'height' => 'auto'])->render(),
        );
    }

    #[Test]
    public function uriThrowsInDevelopment(): void
    {
        $this->setApplicationContext('Development');
        $this->registerImageServiceDouble(new AutoDimensionRejectingImageService($this->createFile()));

        $this->expectException(AutoDimensionException::class);

        $this->uriViewHelper(['src' => '42', 'treatIdAsReference' => true, 'width' => 'auto', 'height' => 'auto'])->render();
    }

    #[Test]
    public function uriIsEmptyInProduction(): void
    {
        $this->setApplicationContext('Production');
        $this->registerImageServiceDouble(new AutoDimensionRejectingImageService($this->createFile()));

        self::assertSame(
            '',
            $this->uriViewHelper(['src' => '42', 'treatIdAsReference' => true, 'width' => 'auto', 'height' => 'auto'])->render(),
        );
    }

    #[Test]
    public function externalImageThrowsInDevelopment(): void
    {
        $this->setApplicationContext('Development');

        $this->expectException(AutoDimensionException::class);
        $this->expectExceptionCode(1790842803);

        $this->imageViewHelper(['src' => 'https://example.com/example.jpg', 'width' => '600', 'height' => 'auto'])->render();
    }

    /**
     * The external image is still delivered, with the side it was given a number for.
     */
    #[Test]
    public function externalImageRendersWithoutTheAutoSideInProduction(): void
    {
        $this->setApplicationContext('Production');

        self::assertSame(
            '<img src="https://example.com/example.jpg" width="600" />',
            $this->imageViewHelper(['src' => 'https://example.com/example.jpg', 'width' => '600', 'height' => 'auto'])->render(),
        );
    }

    private function setApplicationContext(string $context): void
    {
        Environment::initialize(
            new ApplicationContext($context),
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX',
        );
    }

    private function imageViewHelper(array $arguments): ImageViewHelper
    {
        $viewHelper = new ImageViewHelper();
        $viewHelper->setArguments($this->arguments($arguments));
        $viewHelper->initialize();

        return $viewHelper;
    }

    private function uriViewHelper(array $arguments): UriImageViewHelper
    {
        $viewHelper = new UriImageViewHelper();
        $viewHelper->setArguments($this->arguments($arguments));

        return $viewHelper;
    }

    private function arguments(array $overrides): array
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
