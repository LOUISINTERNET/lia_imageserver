<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Processing;

use LIA\LiaImageserver\Processing\Overlay;
use LIA\LiaImageserver\Processing\OverlayAlignment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The shared overlay contract.
 *
 * It lives here rather than in a backend because both of them have to agree on it:
 * the imgix backend turns an overlay into watermark parameters, a local compositor
 * turns it into pixel operations. Neither owns the vocabulary, and a producer must
 * not have to guess which spelling a particular backend expects.
 *
 * The instruction travels as a plain array — it ends up in imgix parameter mapping on
 * one side and in a ProcessedFile checksum on the other, so the wire format has to
 * stay serialisable. This object is the validating boundary at both ends, not the
 * transport itself.
 */
final class OverlayTest extends UnitTestCase
{
    private const INSTRUCTION = [
        'imageIdentifier' => '/_ai_badges/65dc2851fcf0/ai-modified--white.png',
        'imagePath' => 'EXT:lia_ai_badge/Resources/Public/Badges/ai-modified--white.svg',
        'assetVersion' => '65dc2851fcf0',
        'align' => 'bottom-left',
        'padding' => 25,
        'widthRatio' => 0.3,
    ];

    /**
     * Producers and backends sit in different packages and different releases, so the
     * array they exchange has to survive the object unchanged — key names and all.
     */
    #[Test]
    public function instructionSurvivesARoundTripUnchanged(): void
    {
        self::assertSame(
            self::INSTRUCTION,
            Overlay::fromInstruction(self::INSTRUCTION)->toInstruction(),
        );
    }

    /**
     * Omitted rather than emitted as null: backends check with isset(), and on the
     * local path the array becomes part of the ProcessedFile checksum — null keys
     * would change that checksum without changing the rendering.
     *
     * Position and padding are NOT among the omissions: they carry contract defaults
     * and are always written out (see below).
     */
    #[Test]
    public function absentOptionalsAreOmittedInsteadOfEmittedAsNull(): void
    {
        $overlay = new Overlay(imageIdentifier: '/watermarks/logo.png');

        self::assertArrayNotHasKey('imagePath', $overlay->toInstruction());
        self::assertArrayNotHasKey('assetVersion', $overlay->toInstruction());
        self::assertArrayNotHasKey('widthRatio', $overlay->toInstruction());
    }

    /**
     * The field nothing renders: a changed fingerprint has to change the instruction, or
     * a locally composited rendering keeps its old asset — same instruction, same
     * checksum, cached rendering served, compositor never called. Why the contract
     * carries it at all: {@see Overlay::__construct()}.
     */
    #[Test]
    public function assetVersionChangesTheInstructionSoACachedRenderingIsInvalidated(): void
    {
        $before = new Overlay(imagePath: 'EXT:x/badge.png', assetVersion: '65dc2851fcf0');
        $after = new Overlay(imagePath: 'EXT:x/badge.png', assetVersion: '0f3a91b7c2d4');

        self::assertNotSame($before->toInstruction(), $after->toInstruction());
    }

    /**
     * A value is either absent or usable as written. "Present but empty" is a producer
     * bug, and reading it as "absent" would hide it where it costs the most: a backend
     * asks for null before building a URL, so an empty identifier reaches URL building
     * and the delivery backend fails the whole image instead of the rendering losing
     * just its overlay. Padding is rejected rather than trimmed, because these values
     * end up in signed URLs and in a ProcessedFile checksum, where accepting `" x "`
     * and `"x"` as the same thing means two renderings of one asset.
     *
     * @param string $key the field a producer left unusable
     */
    #[Test]
    #[DataProvider('unusableStringProvider')]
    public function stringThatIsPresentButUnusableIsRejected(string $key, string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785715206);

        Overlay::fromInstruction([$key => $value] + self::INSTRUCTION);
    }

    public static function unusableStringProvider(): array
    {
        return [
            'imageIdentifier' => ['imageIdentifier', ''],
            'imagePath' => ['imagePath', ''],
            'assetVersion' => ['assetVersion', ''],
            'whitespace only' => ['imagePath', " \t"],
            'padded' => ['imageIdentifier', ' /watermarks/logo.png '],
        ];
    }

    /**
     * The same rule for an object built in code as for one read off the wire — the
     * constructor is the single boundary, `fromInstruction()` only reads types.
     */
    #[Test]
    public function unusableReferenceIsRejectedWhenTheObjectIsBuiltDirectly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785715206);

        new Overlay(imageIdentifier: '', imagePath: 'EXT:x/badge.png');
    }

    /**
     * Every backend has to place the overlay somewhere, and they all have to place it
     * in the same spot when the producer did not say. Leaving that to each backend's
     * own default is how one instruction ends up rendering differently in two places,
     * so the contract decides and always writes the decision out.
     */
    #[Test]
    public function positionAndPaddingFallBackToTheContractAndAreAlwaysEmitted(): void
    {
        $overlay = Overlay::fromInstruction(['imageIdentifier' => '/watermarks/logo.png']);

        self::assertSame(OverlayAlignment::BottomRight, $overlay->alignment);
        self::assertSame(5, $overlay->padding);
        self::assertSame(
            ['imageIdentifier' => '/watermarks/logo.png', 'align' => 'bottom-right', 'padding' => 5],
            $overlay->toInstruction(),
        );
    }

    /**
     * An absent ratio means the overlay keeps its own size — it is composited as it is,
     * not stretched to the width of the image it lands on.
     */
    #[Test]
    public function absentWidthRatioMeansUnscaled(): void
    {
        self::assertNull(Overlay::fromInstruction(['imagePath' => 'EXT:x/logo.png'])->widthRatio);
    }

    /**
     * Each backend reads the reference it can use and skips the overlay when its own
     * is absent, so an overlay only a local compositor could render is legitimate.
     * Only one that NO backend could render is a contract violation.
     */
    #[Test]
    public function overlayCarryingOnlyALocalAssetIsAccepted(): void
    {
        $overlay = Overlay::fromInstruction(['imagePath' => 'EXT:lia_ai_badge/…/badge.svg']);

        self::assertNull($overlay->imageIdentifier);
        self::assertSame('EXT:lia_ai_badge/…/badge.svg', $overlay->imagePath);
    }

    #[Test]
    public function overlayWithoutAnyAssetReferenceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785715201);

        new Overlay(alignment: OverlayAlignment::BottomLeft);
    }

    #[Test]
    public function contractDefaultsAreTheDocumentedImgixDefaults(): void
    {
        self::assertSame(OverlayAlignment::BottomRight, Overlay::DEFAULT_ALIGNMENT);
        self::assertSame(5, Overlay::DEFAULT_PADDING);
    }

    #[Test]
    public function instructionThatIsNotAnArrayIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785715200);

        Overlay::fromInstruction('bottom-left');
    }

    #[Test]
    public function assetReferenceThatIsNotAStringIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785715205);

        Overlay::fromInstruction(['imageIdentifier' => ['/watermarks/logo.png']]);
    }

    /**
     * The message names the allowed values because the caller is in another package
     * and cannot see this enum from where the mistake was made.
     */
    #[Test]
    public function unknownAlignmentIsRejectedAndTheAllowedValuesAreNamed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785715202);
        $this->expectExceptionMessageMatches('/middle-center/');

        Overlay::fromInstruction(['align' => 'centre'] + self::INSTRUCTION);
    }

    #[Test]
    #[DataProvider('rejectedWidthRatioProvider')]
    public function widthRatioOutsideTheRenderableRangeIsRejected(mixed $widthRatio): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785715204);

        Overlay::fromInstruction(['widthRatio' => $widthRatio] + self::INSTRUCTION);
    }

    public static function rejectedWidthRatioProvider(): array
    {
        return [
            'nothing to render' => [0.0],
            'negative' => [-0.3],
            'wider than the image' => [1.01],
            'not a number' => ['0.3'],
        ];
    }

    /**
     * A whole-width overlay is unusual but renderable, and an integer 1 is what a
     * template writes for it.
     */
    #[Test]
    public function fullWidthRatioIsAcceptedFromAnInteger(): void
    {
        $overlay = Overlay::fromInstruction(['widthRatio' => 1] + self::INSTRUCTION);

        self::assertSame(1.0, $overlay->widthRatio);
    }

    #[Test]
    #[DataProvider('rejectedPaddingProvider')]
    public function paddingThatCannotBeRenderedIsRejected(mixed $padding): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1785715203);

        Overlay::fromInstruction(['padding' => $padding] + self::INSTRUCTION);
    }

    public static function rejectedPaddingProvider(): array
    {
        return [
            'negative' => [-1],
            'not a number' => ['25'],
            'fractional pixels' => [25.5],
        ];
    }

    /**
     * Both axes are offered separately so that no backend parses the value itself:
     * imgix wants "bottom,left", a compositor wants coordinates.
     */
    #[Test]
    #[DataProvider('alignmentAxisProvider')]
    public function alignmentExposesBothAxes(
        OverlayAlignment $alignment,
        string $vertical,
        string $horizontal,
    ): void {
        self::assertSame($vertical, $alignment->vertical());
        self::assertSame($horizontal, $alignment->horizontal());
    }

    public static function alignmentAxisProvider(): array
    {
        return [
            'top-left' => [OverlayAlignment::TopLeft, 'top', 'left'],
            'top-center' => [OverlayAlignment::TopCenter, 'top', 'center'],
            'top-right' => [OverlayAlignment::TopRight, 'top', 'right'],
            'middle-left' => [OverlayAlignment::MiddleLeft, 'middle', 'left'],
            'middle-center' => [OverlayAlignment::MiddleCenter, 'middle', 'center'],
            'middle-right' => [OverlayAlignment::MiddleRight, 'middle', 'right'],
            'bottom-left' => [OverlayAlignment::BottomLeft, 'bottom', 'left'],
            'bottom-center' => [OverlayAlignment::BottomCenter, 'bottom', 'center'],
            'bottom-right' => [OverlayAlignment::BottomRight, 'bottom', 'right'],
        ];
    }

    /**
     * The instruction name is published here so that neither a producer nor a backend
     * spells the array key by hand.
     */
    #[Test]
    public function instructionNameIsPublished(): void
    {
        self::assertSame('overlay', Overlay::INSTRUCTION_NAME);
    }
}
