# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.3.8] - 2026-09-14

### Changed

- Raise the minimum supported TYPO3 v14 version to 14.3. The conditional
  `readonly` class declaration in `ImageService` already switched at 14.3.0,
  where the Extbase parent became a `readonly class`; the composer constraint
  now matches that threshold instead of claiming `^14.0`. `ext_emconf.php`
  keeps its single contiguous range `13.4.0-14.99.99`, which cannot express
  the gap — composer is authoritative.

### Added

- Guard in `ImageService` that throws on TYPO3 v14.0 to v14.2 with a message
  naming both the extension and the version constraint it violates, instead of
  letting PHP abort at class load with an unattributed "Non-readonly class
  cannot extend readonly class" fatal error.

## [2.3.7] - 2026-07-30

Port of the 2.2.7 changes (TYPO3 v13.4 maintenance line) to the TYPO3 v13.4 + v14 line.

### Added

- `ModifyProcessingInstructionsEvent` (PSR-14): fired once per rendering call
  at the top of `ImageServiceTrait::applyProcessingInstructions()` and
  `ImageServiceTrait::applyProcessingInstructionsLia()` — after the
  `getOriginalFile()` unwrap, before `ensureFitWithinExactDimensions()`,
  instruction transformation and backend branching, so every exit path
  (image-server clone, skip, passThrough, local processing) sees listener
  modifications. Listeners can modify the processing instructions, e.g. add
  an abstract `overlay` instruction.
- `Processing\Overlay` and `Processing\OverlayAlignment`: the shared contract for
  the `overlay` instruction — composite a second image onto this one. It lives
  here because every delivery backend has to agree on it (a URL backend maps it
  onto watermark parameters, a local compositor onto pixel operations) and a
  producer must not have to guess which spelling a particular backend expects.
  On the wire it stays a plain array, because it is handed to parameter mapping
  on one path and folded into a `ProcessedFile` checksum on the other; the object
  is the validating boundary at both ends (`fromInstruction()` /
  `toInstruction()`), not the transport.
  Two asset references travel, and carrying only one of them is legitimate:
  `imageIdentifier` is storage-relative for a backend that fetches by URL,
  `imagePath` is an `EXT:` path for one that opens the file. Each backend uses
  its own and skips the overlay when that one is absent.
  A third field, `assetVersion`, is rendered by nobody and carries the caching
  side of the contract: a locally compositing backend finds its rendering
  through a checksum over this instruction, and `imagePath` is stable — so
  replacing the asset behind it leaves the instruction byte-identical, the old
  rendering keeps being served and the compositor is never even asked. A URL
  backend does not have that problem when its identifier is content-addressed,
  which is precisely why this cannot be left to the backends: the asymmetry is
  in their caches, the knowledge is at the producer, and the checksum is formed
  before any backend runs. Any string will do as long as it changes with the
  content; it may be omitted.
  The renderable range for `widthRatio` (`MINIMUM_WIDTH_RATIO`,
  `MAXIMUM_WIDTH_RATIO`) is published rather than private: a producer that derives
  its ratio from something else — an asset's proportions, a height it wants to hit
  — has to be able to honour the range instead of guessing at it and getting an
  exception.
  `OverlayAlignment` covers the full anchor grid and exposes both axes
  (`vertical()`, `horizontal()`), so no backend parses the value itself.
  Malformed instructions are rejected rather than coerced — a backend that
  guesses at one turns a producer's bug into a rendering that is subtly wrong
  instead of loudly broken. A reference that is present but blank (`''`,
  whitespace) is rejected as well, with code `1785715206`: backends tell
  "absent" from "given" by asking for `null`, so an empty identifier would reach
  URL building and cost the whole image — imgix answers 421 when it cannot fetch
  a watermark — instead of costing only the overlay.
- Generic `liaContext` argument on `lim:image`, `lim:uri.image` and
  `lim:widget.responsiveImage`: an opaque array transported read-only to
  event listeners (`$event->getContext()`) and stripped from the
  instructions before any backend sees them.
- `liaContext._maxRenderWidth`: both srcset generators
  (`ImageViewHelper::generateSrcsets()` and
  `ImageServiceTrait::processDefaultSourceSets()/processWebPSourceSets()`)
  and the single-image rendering paths record the largest requested width of
  a rendering call, so listeners can take one consistent decision for all
  srcset candidates of a call.
- `ProcessingFactory` instruction register is extensible via
  `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['instructionRegister']`;
  external registrations win over the defaults.
- PHPUnit harness (`Build/phpunit/UnitTests.xml`) and unit tests for the new
  behavior; `typo3/testing-framework` widened to `^8.0 || ^9.0` so the suite
  runs on the TYPO3 v14 core (PHPUnit 12).

### Changed

- `PassThroughInstruction` no longer stringifies non-scalar instruction
  values (an array became the literal `key=Array` in the parameter string);
  such values are skipped with a structured log warning.

### Fixed

- `ImageServiceTrait::transformProcessingInstructions()` no longer dies on a
  rendering that omits an optional instruction. The branch taken when a storage
  has `skipImageServer` set read `ar`, `fit`, `crop`, `w` and `h` without
  checking whether they were there; under PHP 8 each missing key is a warning,
  and an installation that escalates warnings turns that into an exception — so
  a rendering with nothing but a width took the whole page down. The branch had
  never been executed, because the flag had never been set anywhere.
- Known, deliberately left alone: the aspect-ratio case assigns the computed
  width and height to `$localProcessingInstructions`, a variable that is never
  read again, so an `ar` instruction has no effect. Fixing it would change
  behaviour and the intent is not documented anywhere.

## [2.3.5] - 2026-05-19

### Fixed

- `ImageServiceTrait::transformProcessingInstructions()` no longer crashes
  with `TypeError: substr_count(): Argument #1 ($haystack) must be of type
  string, int given` when Fluid passes a numeric `width`/`height` template
  argument through as `int`. Both values are now cast to `string` before the
  `m`/`c` suffix checks, preserving the existing behaviour for string inputs
  (e.g. `'800m'`, `'800c-25'`).
- `ImageViewHelper::generateSrcsets()` now also translates the Imgix-style
  `w`/`h` keys to TYPO3 core's `width`/`height` keys when the file lives on
  local storage (non-S3) while `lia_middleware_imgix` is loaded. Previously
  the translation only ran when the middleware was missing
  (`isSkipImageServer`), so srcset entries for local FAL images bypassed core
  processing and all responsive variants pointed to the unprocessed source
  file — the browser then stretched the original to the `<img>` element's
  width/height attributes. The `crop` Area from the FAL crop variant is
  honoured natively by core once the right keys arrive.
- `ImageViewHelper::generateSrcsets()` now derives a proportional `h` per
  source-set entry when the source set carries only `w` and the ViewHelper
  call specifies numeric `width` and `height`. This restores the editor
  intent that all responsive variants share the main `src` aspect ratio,
  without each `sourceSets` entry having to spell out both dimensions.
- `ImageServiceTrait::ensureFitWithinExactDimensions()` (new) normalises
  bare numeric `width`/`height` to the `m` (max constraint) suffix before
  falling through to TYPO3 core's image processor. TYPO3 core's default
  behaviour for `width=320 height=460` (without suffix) is to
  **scale-stretch** the cropped source rectangle into the exact target
  box — visibly distorting any image whose source aspect ratio does not
  match the requested one. Appending `m` switches core to fit-within
  semantics (analogous to CSS `object-fit: contain`): the entire source
  is preserved, scaled to fit, with the actual processed dimensions
  reflecting the source aspect ratio. `lim:image` already writes those
  actual dimensions into `<img width="…" height="…">`, so the browser
  displays the image without geometric distortion. Filling the leftover
  space in the container is a CSS concern, not an image-processing one.
  The normaliser is a no-op when either dimension is missing, already
  carries a `c`/`m` suffix, or is non-numeric — so existing templates
  that explicitly opt into crop (`width="320c"`) keep their behaviour.

### Changed

- `ImageServiceTrait::transformProcessingInstructions()` now auto-applies
  `fit=crop` when both `width` and `height` are specified, matching the
  behaviour already present in `processSourceSet()` for srcset entries.
  Previously the main `src` URL rendered without crop while the responsive
  srcset variants did, causing the browser to stretch the delivered image
  to the explicit `width`/`height` attributes set on the `<img>` tag. The
  opt-out is the same as before — use only one dimension, or set `fit`
  explicitly via `imageServerOptions`.

## [2.3.3] - 2026-05-11

### Fixed

- `ImageViewHelper::generateSrcsets()` no longer forwards `null` from
  `getCropValue()` into the Imgix URL builder, which raised a PHP 8.1+
  deprecation in `rawurlencode()` (TYPO3 dev-mode error handler elevates the
  deprecation to a 500). Applies to both the external image path and the FAL
  image path.
