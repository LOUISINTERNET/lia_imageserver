<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Processing;

/**
 * The `overlay` processing instruction: composite a second image onto this one.
 *
 * The contract lives here rather than in a backend because every backend has to agree
 * on it — a URL backend maps it onto watermark parameters, a local compositor onto
 * pixel operations — and because a producer must not have to guess which spelling a
 * particular backend expects.
 *
 * On the wire it stays a plain array: it is handed to imgix parameter mapping on one
 * path and folded into a ProcessedFile checksum on the other, so it has to remain
 * serialisable. This object is the validating boundary at both ends, not the transport.
 * Producers build it and call {@see toInstruction()}; backends receive the array and
 * call {@see fromInstruction()}.
 *
 * Two asset references, because the backends need different things: `imageIdentifier`
 * is storage-relative for a backend that fetches by URL, `imagePath` is an `EXT:` path
 * for one that opens the file. Each backend uses its own and skips the overlay when
 * that one is absent, so carrying only one of them is legitimate.
 *
 * A third field, `assetVersion`, is rendered by nobody — see the constructor for why a
 * caching contract needs it anyway.
 */
final readonly class Overlay
{
    /**
     * Published so that neither a producer nor a backend spells the key by hand.
     */
    public const INSTRUCTION_NAME = 'overlay';

    /**
     * Position and padding have contract defaults rather than staying empty, because
     * every backend has to place the overlay SOMEWHERE and they must all place it in
     * the same spot when the producer did not say. Leaving that to each backend's own
     * default is how the same instruction ends up rendering differently.
     *
     * The values are imgix's documented defaults (`bottom,right` and 5 px, verified
     * 2026-08-03) — not because imgix owns the vocabulary, but because adopting them
     * means nothing changes for renderings that relied on them.
     */
    public const DEFAULT_ALIGNMENT = OverlayAlignment::BottomRight;
    public const DEFAULT_PADDING = 5;

    /**
     * An overlay narrower than a hundredth of the image is not renderable, and one
     * wider than the image itself is a mistake rather than an intention.
     *
     * Published, because a producer that derives its ratio from something else — an
     * asset's proportions, a height it wants to hit — has to be able to honour the
     * renderable range instead of guessing at it and getting an exception.
     */
    public const MINIMUM_WIDTH_RATIO = 0.01;
    public const MAXIMUM_WIDTH_RATIO = 1.0;

    public function __construct(
        public ?string $imageIdentifier = null,
        public ?string $imagePath = null,
        /**
         * An opaque fingerprint of the asset's CONTENT, which no backend renders and
         * every backend has to carry regardless.
         *
         * A locally compositing backend reaches its rendering through a ProcessedFile,
         * and that file is looked up by a checksum over this very instruction. With
         * `imagePath` being a stable `EXT:` path, replacing the asset behind it leaves
         * the instruction byte-identical: the stale rendering keeps being served, and
         * the compositor is never even asked. A URL backend does not have that problem
         * — a content-addressed `imageIdentifier` invalidates itself — which is exactly
         * why this cannot be left to the backends: the asymmetry is in their caches,
         * the knowledge is at the producer, and the checksum is formed before any
         * backend runs.
         *
         * Absent is allowed: an overlay whose asset cannot change (or whose producer
         * accepts that a replacement will not reach cached renderings) needs nothing
         * here. Any string will do as long as it changes with the content.
         */
        public ?string $assetVersion = null,
        public OverlayAlignment $alignment = self::DEFAULT_ALIGNMENT,
        public int $padding = self::DEFAULT_PADDING,
        /**
         * Absent means the overlay keeps its own size — the asset is composited as it
         * is, not scaled to the base image.
         */
        public ?float $widthRatio = null,
    ) {
        self::rejectUnusableString('imageIdentifier', $imageIdentifier);
        self::rejectUnusableString('imagePath', $imagePath);
        self::rejectUnusableString('assetVersion', $assetVersion);
        if ($imageIdentifier === null && $imagePath === null) {
            throw new \InvalidArgumentException(
                'An overlay needs at least one asset reference: "imageIdentifier" for a'
                . ' delivery backend that fetches by URL, "imagePath" for one that'
                . ' composites locally.',
                1785715201
            );
        }
        if ($padding < 0) {
            throw new \InvalidArgumentException(
                sprintf('Overlay padding must not be negative, got %d.', $padding),
                1785715203
            );
        }
        if ($widthRatio !== null
            && ($widthRatio < self::MINIMUM_WIDTH_RATIO || $widthRatio > self::MAXIMUM_WIDTH_RATIO)
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Overlay widthRatio must be between %s and %s, got %s.',
                    self::MINIMUM_WIDTH_RATIO,
                    self::MAXIMUM_WIDTH_RATIO,
                    $widthRatio,
                ),
                1785715204
            );
        }
    }

    /**
     * Reads the instruction as it arrived from another package.
     *
     * Everything is rejected rather than coerced: a backend that guesses at a
     * malformed instruction turns a producer's bug into a rendering that is subtly
     * wrong instead of loudly broken. The one widening allowed is an integer where a
     * ratio is expected, because a template writes `widthRatio: 1`, not `1.0`.
     */
    public static function fromInstruction(mixed $instruction): self
    {
        if (!is_array($instruction)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The "%s" instruction must be an array, got %s.',
                    self::INSTRUCTION_NAME,
                    get_debug_type($instruction),
                ),
                1785715200
            );
        }

        return new self(
            self::readString($instruction, 'imageIdentifier'),
            self::readString($instruction, 'imagePath'),
            self::readString($instruction, 'assetVersion'),
            self::readAlignment($instruction),
            self::readPadding($instruction),
            self::readWidthRatio($instruction),
        );
    }

    /**
     * @return array<string, string|int|float>
     */
    public function toInstruction(): array
    {
        $instruction = [
            'imageIdentifier' => $this->imageIdentifier,
            'imagePath' => $this->imagePath,
            // Travels with the references it fingerprints, so that a backend which folds
            // the instruction into a cache key gets the asset's identity for free.
            'assetVersion' => $this->assetVersion,
            // Always written out, defaults included, so that no backend has to fall back
            // to one of its own and every backend places the overlay identically.
            'align' => $this->alignment->value,
            'padding' => $this->padding,
            'widthRatio' => $this->widthRatio,
        ];

        /*
         * Absent values are dropped rather than emitted as null: backends check with
         * isset(), and on the local path this array becomes part of the ProcessedFile
         * checksum, where a null key would force a reprocess without changing anything
         * about the rendering.
         */
        return array_filter($instruction, static fn(mixed $value): bool => $value !== null);
    }

    /**
     * A string is either absent or usable as written — no blanks, no surrounding
     * whitespace.
     *
     * Reading a present-but-blank value as "absent" would hide a producer bug where it
     * costs the most: backends tell the two apart by asking for null, so an empty
     * identifier reaches URL building and requests a watermark for nothing, which costs
     * the whole image rather than the overlay. Padding is rejected in the same breath
     * instead of being trimmed away: these values go into signed URLs and into a
     * ProcessedFile checksum, where silently accepting " x " and "x" as the same thing
     * yields two renderings of one asset.
     */
    private static function rejectUnusableString(string $key, ?string $value): void
    {
        if ($value !== null && ($value === '' || trim($value) !== $value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Overlay "%s" must either be absent or name something without surrounding'
                    . ' whitespace, got "%s".',
                    $key,
                    $value,
                ),
                1785715206
            );
        }
    }

    /**
     * Type only — what makes a value acceptable is decided in one place, the
     * constructor, so that an instruction read off the wire and an object built in code
     * are held to the same rules.
     */
    private static function readString(array $instruction, string $key): ?string
    {
        $value = $instruction[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                sprintf('Overlay "%s" must be a string, got %s.', $key, get_debug_type($value)),
                1785715205
            );
        }

        return $value;
    }

    private static function readAlignment(array $instruction): OverlayAlignment
    {
        $value = $instruction['align'] ?? null;
        if ($value === null) {
            return self::DEFAULT_ALIGNMENT;
        }

        $alignment = is_string($value) ? OverlayAlignment::tryFrom($value) : null;
        if ($alignment === null) {
            /*
             * The allowed values are named in the message because the caller sits in
             * another package and cannot see this enum from where the mistake was made.
             */
            throw new \InvalidArgumentException(
                sprintf(
                    'Unknown overlay alignment %s. Expected one of: %s.',
                    is_string($value) ? '"' . $value . '"' : get_debug_type($value),
                    implode(', ', array_column(OverlayAlignment::cases(), 'value')),
                ),
                1785715202
            );
        }

        return $alignment;
    }

    private static function readPadding(array $instruction): int
    {
        $value = $instruction['padding'] ?? null;
        if ($value === null) {
            return self::DEFAULT_PADDING;
        }
        if (!is_int($value)) {
            throw new \InvalidArgumentException(
                sprintf('Overlay padding must be an integer number of pixels, got %s.', get_debug_type($value)),
                1785715203
            );
        }

        return $value;
    }

    private static function readWidthRatio(array $instruction): ?float
    {
        $value = $instruction['widthRatio'] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException(
                sprintf('Overlay widthRatio must be a number, got %s.', get_debug_type($value)),
                1785715204
            );
        }

        return (float)$value;
    }
}
