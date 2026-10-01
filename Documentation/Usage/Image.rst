..  include:: /Includes.rst.txt

..  _usage-image:

=========
lim:image
=========

Based on the core ViewHelper :html:`<f:image>`. Adds `sourceSets`, WebP
support and the argument `imageServerOptions`, which is taken into account
during processing.

For the inherited arguments see the core documentation of `f:image
<https://docs.typo3.org/permalink/t3viewhelper:typo3-fluid-image>`__. The
complete argument reference of this ViewHelper is generated from the source
code, see :ref:`lia-liaimageserver-image`.

..  _usage-image-arguments:

Additional arguments
====================

..  list-table::
    :header-rows: 1
    :widths: 25 55 20

    *   -   Argument
        -   Description
        -   Type
    *   -   `imageServerOptions`
        -   Options passed to the image server. Any valid image server option
            is allowed. These options overrule all other attributes.
        -   array
    *   -   `enableWebPSupport`
        -   Add WebP sources. Default: `false`.
        -   bool
    *   -   `cropVariant`
        -   Name of the crop variant configured in the backend. Default:
            `default`.
        -   string
    *   -   `objectFit`
        -   Object fit mode for the polyfill.
        -   string
    *   -   `objectPosition`
        -   Object position for the polyfill.
        -   string
    *   -   `sizes`
        -   `sizes` entries for the :html:`<img>` element, one per source set
            index.
        -   array
    *   -   `sourceSets`
        -   Source set configuration. Always provide a default source set.
        -   array
    *   -   `respectImageWidth`
        -   If set, source set definitions are processed so that they do not
            exceed the given image width. Default: `0`.
        -   int
    *   -   `respectImageWidthClasses`
        -   Classes applied to the :html:`<img>` element when
            `respectImageWidth` is set. Default: `staticimage`,
            `u-respect-image-width`.
        -   array
    *   -   `passThrough`
        -   Skip the external image server. The URL points to the file
            processed by TYPO3.
        -   bool
    *   -   `loading`
        -   Loading attribute: `none`, `eager` or `lazy`. Default: `lazy`.
        -   string
    *   -   `additionalAttributes`
        -   Additional tag attributes, added directly to the resulting HTML
            tag.
        -   array
    *   -   `fetchPriority`
        -   Priority hint: `high`, `low` or `auto`.
        -   string
    *   -   `liaContext`
        -   Opaque context for :ref:`ModifyProcessingInstructionsEvent
            <developer-events>` listeners, for example `{aiBadge: '0'}`. Never
            forwarded to the image server. `_maxRenderWidth` (the largest
            requested width of the call) is injected automatically.
        -   array

..  note::

    A `sourceSets` entry without `h` inherits the ratio of the numeric
    `width` and `height` arguments. How the image is fitted into that size
    is decided by the image server backend (lia_middleware_imgix crops it,
    unless the source set or `imageServerOptions` set `fit`). Locally
    processed files fit within the box.

..  note::

    `height="auto"` derives the height from the ratio of the crop variant at
    the numeric `width`, or from the whole image when no crop is stored;
    `width="auto"` derives the width at the numeric `height` the same way, e.g.
    for rows of images with a fixed height. Use
    it for crop variants that allow a free ratio, or whose stored crops have
    different ratios: a fixed height would cut those images to the
    template's shape once the backend crops to width and height. The rendered
    `width`/`height` attributes keep the crop's ratio, and `sourceSets`
    entries carry only `w`. Supported for FAL images only: an external `src`
    with `auto`, `auto` on both sides or an image without dimensions throws in
    development and is logged otherwise.

..  _usage-image-example:

Example
=======

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Partials/Image.html

    <picture>
        <lim:image
            treatIdAsReference="true"
            src="{image.id}"
            alt="{image.alternative}"
            enableWebPSupport="true"
            width="600"
            height="400"
            loading="lazy"
            cropVariant="tablet"
            additionalAttributes="{itemprop: 'test'}"
            imageServerOptions="{fm: 'pjpg', q: 'lossless'}"
            sizes="{
              0: '100vw',
              1: '(min-width: 850px) 350px',
              2: '(min-width: 1024px) calc((100vw - 130px) / 2)'
            }"
            sourceSets="{
              0: {w:320},
              1: {w:350},
              2: {w:490},
              3: {w:1050}
            }"
            passThrough="true"
            fetchPriority="auto"
        />
    </picture>

Inline notation with variables:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Partials/Image.html

    <picture>
        <f:variable name="sizes" value="{
          0: '100vw',
          1: '(min-width: 850px) 350px',
          2: '(min-width: 1024px) calc((100vw - 130px) / 2)'
        }" />
        <f:variable name="srcSet" value="{0: {w:320}, 1: {w:350}, 2: {w:490}, 3: {w:1050}}" />

        {lim:image(treatIdAsReference: true, src: image.id, alt: image.alternative, width: '600c', height: '400c', cropVariant: 'tablet', imageServerOptions: '{fm: "pjpg", q: "lossless"}', sizes: sizes, sourceSets: srcSet, passThrough: true, loading: 'lazy', fetchPriority: 'auto')}
    </picture>
