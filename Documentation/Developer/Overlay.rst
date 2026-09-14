..  include:: /Includes.rst.txt

..  _developer-overlay:

====================
The overlay contract
====================

:php:`LIA\LiaImageserver\Processing\Overlay` describes one instruction:
*composite a second image onto this one*. It lives in this extension rather
than in a delivery backend because every backend has to agree on it. An
imgix-style backend maps it onto watermark parameters; a local compositor
turns it into pixel operations. Neither owns the vocabulary, and a producer
must not have to guess which spelling a particular backend expects.

..  _developer-overlay-fields:

Fields
======

..  list-table::
    :header-rows: 1
    :widths: 20 25 55

    *   -   Field
        -   Type
        -   Meaning
    *   -   `imageIdentifier`
        -   :php:`?string`
        -   Storage-relative path, for a backend that fetches the overlay by
            URL.
    *   -   `imagePath`
        -   :php:`?string`
        -   `EXT:` path, for a backend that opens the file locally.
    *   -   `assetVersion`
        -   :php:`?string`
        -   Opaque fingerprint of the asset's **content**; nothing renders it.
    *   -   `alignment`
        -   :php:`OverlayAlignment`
        -   Full anchor grid: `top` / `middle` / `bottom` × `left` / `center`
            / `right`. Default: :php:`OverlayAlignment::BottomRight`.
    *   -   `padding`
        -   :php:`int`
        -   Pixels from the edge. Default: `5`.
    *   -   `widthRatio`
        -   :php:`?float`
        -   Fraction of the **base** image width, `0.01` to `1.0`. Absent
            means the overlay keeps its own size.

At least one asset reference is required, and carrying only one is
legitimate: each backend uses the reference it can work with and skips the
overlay when its own is absent. A reference that is present but blank is
rejected: backends tell "absent" from "given" by asking for :php:`null`, so an
empty string would reach URL building and cost the whole image rather than the
overlay. Producers build the object and hand over :php:`toInstruction()`;
backends receive the array and call :php:`fromInstruction()`.

..  code-block:: php
    :caption: Producer and backend side

    use LIA\LiaImageserver\Processing\Overlay;
    use LIA\LiaImageserver\Processing\OverlayAlignment;

    // Producer
    $instructions[Overlay::INSTRUCTION_NAME] = (new Overlay(
        imageIdentifier: '/watermarks/logo.png',
        imagePath: 'EXT:my_extension/Resources/Public/logo.svg',
        assetVersion: '65dc2851fcf0',
        alignment: OverlayAlignment::BottomRight,
        padding: 20,
        widthRatio: 0.2,
    ))->toInstruction();

    // Backend
    $overlay = Overlay::fromInstruction($value);
    $overlay->alignment->vertical();    // 'bottom'
    $overlay->alignment->horizontal();  // 'right'

..  _developer-overlay-transport:

Why the instruction stays an array
==================================

On the wire the instruction stays a plain array. That is not incidental: it is
handed to parameter mapping on one path and folded into a :php:`ProcessedFile`
checksum on the other, so it has to remain serialisable. This is also why
absent values are omitted rather than emitted as :php:`null`. The object is
the validating boundary at both ends, not the transport.

..  _developer-overlay-asset-version:

Why assetVersion exists
=======================

`assetVersion` exists because of that second path. A locally compositing
backend reaches its rendering through a :php:`ProcessedFile` looked up by a
checksum over this instruction, and `imagePath` is a stable `EXT:` path:
replace the file behind it and the instruction stays byte-identical, so the
old rendering keeps being served and the compositor is never asked. A URL
backend does not have that problem when its `imageIdentifier` is
content-addressed, which is exactly why this cannot be left to the backends.
The asymmetry sits in their caches, the knowledge sits at the producer, and
the checksum is formed before any backend runs. Any string will do as long as
it changes with the content; producers whose asset cannot change may omit it.

..  _developer-overlay-errors:

Errors and sizing
=================

A malformed instruction throws rather than being coerced or dropped: a backend
that guesses at one turns a producer's bug into a rendering that is subtly
wrong instead of loudly broken.

`widthRatio` is measured against the base image, so the resolution an overlay
asset needs is the largest rendered base width times the ratio. A backend
requests the asset as stored and does not cap it.
