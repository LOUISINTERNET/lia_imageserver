<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Service;

use LIA\LiaImageserver\Tests\Unit\Fixtures\InstructionProbeImageService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * D8 contract for the widget source-set generator: every srcset variant of
 * one rendering call carries the SAME `liaContext._maxRenderWidth` (largest
 * requested `w` across ALL source sets), so the badge decision is identical
 * for all candidates of one <picture>.
 */
final class ImageServiceSourceSetContextTest extends UnitTestCase
{
    private const SOURCE_SETS = [
        '(max-width: 767px)' => [
            'srcset' => [['w' => 320], ['w' => 640]],
            'sizes' => 'calc(100vw - 30px)',
            'default' => 1,
        ],
        '(min-width: 768px)' => [
            'srcset' => [['w' => 900], ['w' => 1024]],
            'sizes' => '100vw',
        ],
    ];

    #[Test]
    public function defaultSourceSetsCarryMaxRenderWidthAcrossAllVariants(): void
    {
        $service = new InstructionProbeImageService();

        $service->processDefaultSourceSets(
            self::createStub(FileInterface::class),
            [],
            self::SOURCE_SETS,
            ['fm' => 'jpg', 'liaContext' => ['aiBadge' => '1']],
            0,
        );

        $recordedInstructionSets = $service->recordedInstructionSets->getArrayCopy();
        self::assertCount(4, $recordedInstructionSets);
        foreach ($recordedInstructionSets as $instructions) {
            self::assertSame(1024, $instructions['liaContext']['_maxRenderWidth']);
            self::assertSame('1', $instructions['liaContext']['aiBadge']);
        }
        self::assertSame(320, $recordedInstructionSets[0]['w']);
        self::assertSame(1024, $recordedInstructionSets[3]['w']);
    }

    #[Test]
    public function webPSourceSetsCarryMaxRenderWidthAcrossAllVariants(): void
    {
        $service = new InstructionProbeImageService();

        $service->processWebPSourceSets(
            self::createStub(FileInterface::class),
            [],
            self::SOURCE_SETS,
            ['fm' => 'jpg'],
            0,
        );

        $recordedInstructionSets = $service->recordedInstructionSets->getArrayCopy();
        self::assertCount(4, $recordedInstructionSets);
        foreach ($recordedInstructionSets as $instructions) {
            self::assertSame(['_maxRenderWidth' => 1024], $instructions['liaContext']);
            self::assertSame('webp', $instructions['fm']);
        }
    }
}
