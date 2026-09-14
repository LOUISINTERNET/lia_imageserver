..  include:: /Includes.rst.txt

..  _usage-uri-image:

=============
lim:uri.image
=============

Based on the core ViewHelper :html:`<f:uri.image>`. Adds the argument
`imageServerOptions`, which is taken into account during processing, and
returns the URL of the processed image.

For the inherited arguments see the core documentation of `f:uri.image
<https://docs.typo3.org/permalink/t3viewhelper:typo3-fluid-uri-image>`__. The
complete argument reference of this ViewHelper is generated from the source
code, see :ref:`lia-liaimageserver-uri-image`.

..  _usage-uri-image-hex:

..  note::
    TYPO3 strips the leading zero from hex codes like `015069` in arguments
    such as `blend-color`. In these cases, deviate from the image server
    documentation and prefix the value with `#`:

    ..  code-block:: html
        :caption: Hex color with leading zero

        imageServerOptions="{blend-color: '#015069', blend-alpha: 80}"

..  _usage-uri-image-arguments:

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
    *   -   `passThrough`
        -   Skip the external image server. The URL points to the file
            processed by TYPO3.
        -   bool

..  _usage-uri-image-example:

Example
=======

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Partials/Image.html

    <picture>
        <lim:uri.image
            treatIdAsReference="true"
            src="{image.id}"
            width="600"
            height="400"
            cropVariant="tablet"
            imageServerOptions="{fm: 'pjpg', q: 'lossless'}"
            passThrough="true"
        />
    </picture>

To activate cropping, add the suffix `m` to `width` and `height`:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Partials/Image.html

    <picture>
        <lim:uri.image
            treatIdAsReference="true"
            src="{image.id}"
            width="600m"
            height="400m"
            cropVariant="tablet"
            imageServerOptions="{fm: 'pjpg', q: 'lossless'}"
            passThrough="true"
        />
    </picture>

Inline notation:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Partials/Image.html

    <picture>
        {lim:uri.image(treatIdAsReference: true, src: image.id, width: '600c', height: '400c', cropVariant: 'tablet', imageServerOptions: '{fm: "pjpg", q: "lossless"}', passThrough: true)}
    </picture>
