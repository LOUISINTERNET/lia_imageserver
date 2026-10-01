<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Service;

use LIA\LiaImageserver\Helper;
use LIA\LiaImageserver\Service\ImageService;
use LIA\LiaImageserver\Tests\Unit\Fixtures\HelperFake;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\ServiceProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Core builds the Extbase ImageService in its ServiceProvider. That factory wins over any
 * Services.yaml definition of the id and resolves only SYS/Objects overrides, and it is how
 * f:image, f:media and f:uri.image obtain the service. 2.3.0-2.3.9 registered the override in
 * Services.yaml only, so these ViewHelpers silently rendered through the plain core service.
 */
final class ExtbaseImageServiceOverrideTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private array $typo3ConfVarsBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->typo3ConfVarsBackup = $GLOBALS['TYPO3_CONF_VARS'];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->typo3ConfVarsBackup;
        GeneralUtility::flushInternalRuntimeCaches();
        parent::tearDown();
    }

    #[Test]
    public function extbaseServiceProviderBuildsTheImageserverService(): void
    {
        require __DIR__ . '/../../../ext_localconf.php';
        GeneralUtility::flushInternalRuntimeCaches();
        GeneralUtility::addInstance(Helper::class, new HelperFake(imageServerImage: true, skipImageServer: false));
        $container = self::createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            [ResourceFactory::class, self::createStub(ResourceFactory::class)],
        ]);

        self::assertInstanceOf(ImageService::class, ServiceProvider::getImageService($container));
    }
}
