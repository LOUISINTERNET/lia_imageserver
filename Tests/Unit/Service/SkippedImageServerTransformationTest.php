<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Service;

use LIA\LiaImageserver\Tests\Unit\Fixtures\SkippingImageService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Instruction transformation when a storage has the image server switched off.
 *
 * This branch had never been executed: the per-storage `skipImageServer` flag was set
 * for the first time on 2026-08-03, and the very first rendering died. It read five
 * instruction keys without checking whether they were there, and under PHP 8 each
 * missing key is a warning that TYPO3's error handler escalates into an exception —
 * so a rendering that simply had no aspect ratio took the whole page down.
 *
 * The instruction sets below are the realistic ones: what `lim:image` actually hands
 * over. None of them carries `ar`, `fit`, `w` or `h`.
 */
final class SkippedImageServerTransformationTest extends UnitTestCase
{
    /**
     * Missing keys must be absent conditions, not errors.
     */
    #[Test]
    #[DataProvider('instructionsWithoutOptionalKeysProvider')]
    public function instructionsWithoutTheOptionalKeysAreTransformedWithoutError(array $instructions): void
    {
        $transformed = $this->imageService()->transformForLocalProcessing($instructions, $this->image());

        // Untouched: nothing in these sets asks for a transformation.
        self::assertSame($instructions, $transformed);
    }

    public static function instructionsWithoutOptionalKeysProvider(): array
    {
        return [
            'nothing at all' => [[]],
            'a plain width' => [['width' => 295]],
            'what lim:image sends for a gallery column' => [[
                'auto' => 'compress,format',
                'q' => 70,
                'width' => 295,
                'height' => 196.0,
                'minWidth' => null,
                'minHeight' => null,
                'maxWidth' => null,
                'maxHeight' => null,
                'crop' => null,
            ]],
            'with an overlay instruction alongside' => [[
                'width' => 295,
                'overlay' => [
                    'imageIdentifier' => '/_ai_badges/65dc2851fcf0/ai-generated--white-transparent.png',
                    'imagePath' => 'EXT:lia_ai_badge/Resources/Public/Badges/ai-generated--white-transparent.png',
                    'align' => 'bottom-left',
                    'padding' => 25,
                    'widthRatio' => 0.3,
                ],
            ]],
        ];
    }

    /**
     * The imgix short forms are translated into the core vocabulary, because local
     * processing only understands width and height.
     */
    #[Test]
    public function imgixShorthandWidthAndHeightAreTranslated(): void
    {
        $transformed = $this->imageService()->transformForLocalProcessing(
            ['w' => 295, 'h' => 196],
            $this->image(),
        );

        self::assertSame(295, $transformed['width']);
        self::assertSame(196, $transformed['height']);
    }

    private function imageService(): SkippingImageService
    {
        return new SkippingImageService();
    }

    private function image(): File
    {
        $image = self::createStub(File::class);
        $image->method('getProperty')->willReturnMap([
            ['width', 3000],
            ['height', 2000],
        ]);

        return $image;
    }
}
