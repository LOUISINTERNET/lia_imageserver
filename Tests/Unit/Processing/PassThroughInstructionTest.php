<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Processing;

use LIA\LiaImageserver\Processing\PassThroughInstruction;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Hardening: non-scalar instruction values must never be stringified into
 * the parameter string (an array would become the literal "key=Array") —
 * they are skipped with a structured log warning instead.
 */
final class PassThroughInstructionTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function scalarValueIsAppendedAsKeyValueString(): void
    {
        $instruction = new PassThroughInstruction();

        $instructions = $instruction->process(self::createStub(FileInterface::class), [], 'q', '80');

        self::assertSame(['q' => 'q=80'], $instructions);
    }

    #[Test]
    public function existingKeyIsNotOverwritten(): void
    {
        $instruction = new PassThroughInstruction();

        $instructions = $instruction->process(self::createStub(FileInterface::class), ['q' => 'q=70'], 'q', '80');

        self::assertSame(['q' => 'q=70'], $instructions);
    }

    #[Test]
    public function nullValueIsSkippedWithoutLogging(): void
    {
        $this->registerLoggerExpectingWarnings(0);
        $instruction = new PassThroughInstruction();

        $instructions = $instruction->process(self::createStub(FileInterface::class), [], 'ar', null);

        self::assertSame([], $instructions);
    }

    #[Test]
    public function arrayValueIsDroppedAndLogged(): void
    {
        $this->registerLoggerExpectingWarnings(1);
        $instruction = new PassThroughInstruction();

        $instructions = $instruction->process(
            self::createStub(FileInterface::class),
            ['w' => 'w=400'],
            'overlay',
            ['imageIdentifier' => '/_ai_badges/abc/ai.svg'],
        );

        self::assertSame(['w' => 'w=400'], $instructions);
    }

    private function registerLoggerExpectingWarnings(int $expectedWarnings): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::exactly($expectedWarnings))->method('warning');
        $logManager = self::createStub(LogManager::class);
        $logManager->method('getLogger')->willReturn($logger);
        GeneralUtility::setSingletonInstance(LogManager::class, $logManager);
    }
}
