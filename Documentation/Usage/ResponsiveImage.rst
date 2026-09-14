..  include:: /Includes.rst.txt

..  _usage-responsive-image:

==========================
lim:widget.responsiveImage
==========================

Renders a responsive :html:`<picture>` element from a source set
configuration. Supports WebP sources, media queries and art direction through
crop variants.

..  note::
    When a legacy external image server is used, this ViewHelper only supports
    files that are hosted on that external storage.

The complete argument reference is generated from the source code, see
:ref:`lia-liaimageserver-widget-responsiveimage`.

..  _usage-responsive-image-tag:

Tag usage
=========

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Partials/Image.html

    <html xmlns:lim="http://typo3.org/ns/LIA/LiaImageserver/ViewHelpers" data-namespace-typo3-fluid="true">
    <picture>
        <lim:widget.responsiveImage
            image="{image}"
            enableWebPSupport="true"
            defaultOptions="{'fm': 'jpg'}"
            sourceSets="{
                '(max-width: 767px)':
                {
                  srcset: {0: {w:320},1: {w:480},2: {w:560},3: {w:640}},
                  sizes: 'calc(100vw - 30px)',
                  default: 1,
                  options: {fm: 'png'},
                  cropVariant: 'tablet'
                },
                '(min-width: 768px)':
                {
                  srcset: {0: {w:640},1: {w:768},2: {w:900},3: {w:1024}},
                  sizes: '100vw',
                  cropVariant: 'smartphone'
                }
            }"
            fetchPriority="auto"
        />
    </picture>

    <picture>
        <lim:widget.responsiveImage
            src="{image.uid}"
            enableWebPSupport="true"
            treatIdAsReference="1"
            class="css-class-on-image"
            objectFit="cover"
            objectPositionLeft="center"
            objectPositionTop="top"
            defaultOptions="{'fm': 'jpg', fit: 'crop'}"
            sourceSets="{
                '(max-width: 767px)':
                {
                  srcset: {0: {w:320,h:350,fm:'jpeg'},1: {w:480,h:525},2: {w:560,h:613},3: {w:640,h:700}},
                  sizes: 'calc(100vw - 30px)',
                  default: 1,
                  options: {fm: 'pjpg', crop: 'left,top'}
                },
                '(min-width: 768px) and (max-width: 1024px) and (min-height: 767px)':
                {
                  srcset: {0: {w:640,h:475},1: {w:768,h:570},2: {w:900,h:668},3: {w:1024,h:760}},
                  sizes: '100vw',
                  options: {fm: 'pjpg', crop: 'right,bottom'}
                }
            }"
            fetchPriority="auto"
        />
    </picture>
    </html>

..  _usage-responsive-image-inline:

Inline usage with a variable
============================

Store the nested `sourceSets` array in a variable and pass it on. This keeps
the inline notation readable.

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Partials/Image.html

    <html xmlns:lim="http://typo3.org/ns/LIA/LiaImageserver/ViewHelpers" data-namespace-typo3-fluid="true">
    <picture>
        <f:variable
            name="responsiveImageSourceSets"
            value="{
                '(max-width: 767px)':
                {
                  srcset: {0: {w:320,h:350,fm:'jpeg'},1: {w:480,h:525},2: {w:560,h:613},3: {w:640,h:700}},
                  sizes: 'calc(100vw - 30px)',
                  default: 1,
                  options: {fm: 'pjpg', crop: 'left,top'}
                },
                '(min-width: 768px) and (max-width: 1024px) and (min-height: 767px)':
                {
                  srcset: {0: {w:640,h:475},1: {w:768,h:570},2: {w:900,h:668},3: {w:1024,h:760}},
                  sizes: '100vw',
                  options: {fm: 'pjpg', crop: 'right,bottom'}
                }
            }"
        />
        {lim:widget.responsiveImage(src: image.uid, enableWebPSupport: 'true', treatIdAsReference: 'true', class: 'css-class-on-image', objectFit: 'cover', objectPositionLeft: 'center', objectPositionTop: 'top', defaultOptions: '{fm: "jpg", fit: "crop"}', sourceSets: responsiveImageSourceSets, fetchPriority: 'auto')}
    </picture>
    </html>

..  _usage-responsive-image-arguments:

Arguments
=========

..  list-table::
    :header-rows: 1
    :widths: 25 55 20

    *   -   Argument
        -   Description
        -   Type
    *   -   `src`
        -   A path to a file, a combined FAL identifier or a uid. If
            `treatIdAsReference` is set, the integer is the uid of a
            `sys_file_reference` record. If you already have a FAL object,
            use `image` instead.
        -   string
    *   -   `image`
        -   A FAL object (:php:`File` or :php:`FileReference`).
        -   object
    *   -   `treatIdAsReference`
        -   Set to `true` when `src` holds the uid of a `sys_file_reference`
            record.
        -   bool
    *   -   `sourceSets`
        -   Source set configuration keyed by media query. Each entry may
            carry `srcset`, `sizes`, `default`, `options` and `cropVariant`.
            Always provide a default source set.
        -   array
    *   -   `defaultOptions`
        -   Options applied to all source sets. Which options exist depends on
            the image server in use; for imgix see
            `https://docs.imgix.com/apis/url <https://docs.imgix.com/apis/url>`__.
        -   array
    *   -   `enableWebPSupport`
        -   Add WebP sources. Default: `true`.
        -   bool
    *   -   `class`
        -   CSS class(es) for the :html:`<img>` element.
        -   string
    *   -   `objectPositionLeft`
        -   Object position left for the polyfill. Default: `center`.
        -   string
    *   -   `objectPositionTop`
        -   Object position top for the polyfill. Default: `center`.
        -   string
    *   -   `objectFit`
        -   Object fit mode for the polyfill.
        -   string
    *   -   `respectImageWidth`
        -   If set, source set definitions are processed so that they do not
            exceed the given image width. Default: `0`.
        -   int
    *   -   `respectImageWidthClasses`
        -   Classes applied to the :html:`<img>` element when
            `respectImageWidth` is set. Default: `staticimage`,
            `u-respect-image-width`.
        -   array
    *   -   `loading`
        -   Native lazy loading: `lazy`, `eager` or `auto`.
        -   string
    *   -   `dimensions`
        -   Set `height` and `width` attributes dynamically. Default: `false`.
        -   bool
    *   -   `fetchPriority`
        -   Priority hint: `high`, `low` or `auto`.
        -   string
    *   -   `customWidgetId`
        -   Extends the widget identifier with a custom id.
        -   string
    *   -   `storeSession`
        -   Store the widget session in a cookie. Default: `true`.
        -   bool
