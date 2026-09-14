<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Processing;

use LIA\LiaImageserver\Processing\ProcessingFactory;
use LIA\LiaImageserver\Tests\Unit\Fixtures\RecordingInstruction;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Extensible instruction register: other extensions register instruction
 * classes for new keys (e.g. `overlay`) via
 * $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['instructionRegister'].
 */
final class ProcessingFactoryTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function externallyRegisteredInstructionHandlesItsKey(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['instructionRegister'] = [
            'overlay' => RecordingInstruction::class,
        ];
        $factory = new ProcessingFactory();

        $instructions = $factory->process(
            self::createStub(FileInterface::class),
            [],
            'overlay',
            ['imageIdentifier' => '/_ai_badges/abc/ai.svg'],
        );

        self::assertSame(['overlay' => 'handled-by-recording-instruction'], $instructions);
    }

    #[Test]
    public function externalRegistrationWinsOverDefaultRegister(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['instructionRegister'] = [
            'width' => RecordingInstruction::class,
        ];
        $factory = new ProcessingFactory();

        $instructions = $factory->process(self::createStub(FileInterface::class), [], 'width', '400');

        self::assertSame(['width' => 'handled-by-recording-instruction'], $instructions);
    }

    #[Test]
    public function unknownScalarKeysStillPassThrough(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['instructionRegister']);
        $factory = new ProcessingFactory();

        $instructions = $factory->process(self::createStub(FileInterface::class), [], 'q', '80');

        self::assertSame(['q' => 'q=80'], $instructions);
    }
}
