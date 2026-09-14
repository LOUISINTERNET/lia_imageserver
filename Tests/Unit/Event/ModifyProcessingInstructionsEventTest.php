<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Event;

use LIA\LiaImageserver\Event\ModifyProcessingInstructionsEvent;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ModifyProcessingInstructionsEventTest extends UnitTestCase
{
    #[Test]
    public function exposesImageInstructionsAndContext(): void
    {
        $image = self::createStub(FileInterface::class);
        $instructions = ['w' => '400', 'fm' => 'webp'];
        $context = ['aiBadge' => '0'];

        $event = new ModifyProcessingInstructionsEvent($image, $instructions, $context);

        self::assertSame($image, $event->getImage());
        self::assertSame($instructions, $event->getInstructions());
        self::assertSame($context, $event->getContext());
    }

    #[Test]
    public function setInstructionsReplacesTheInstructionSet(): void
    {
        $event = new ModifyProcessingInstructionsEvent(
            self::createStub(FileInterface::class),
            ['w' => '400'],
            [],
        );

        $event->setInstructions(['w' => '400', 'overlay' => ['align' => 'bottom-left']]);

        self::assertSame(['w' => '400', 'overlay' => ['align' => 'bottom-left']], $event->getInstructions());
    }
}
