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
        -   Native lazy loading attribute: `lazy`, `eager` or `auto`.
        -   string
    *   -   `additionalAttributes`
        -   Additional tag attributes, added directly to the resulting HTML
            tag.
        -   array
    *   -   `fetchPriority`
        -   Priority hint: `high`, `low` or `auto`.
        -   string

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
