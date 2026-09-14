..  include:: /Includes.rst.txt

:navigation-title: FAQ

..  _faq:

================================
Frequently Asked Questions (FAQ)
================================

..  accordion::
    :name: faq

    ..  accordion-item:: My hex color value is broken, what happened?
        :name: hex-color
        :header-level: 2
        :show:

        TYPO3 strips the leading zero from hex codes like `015069`. Prefix the
        value with `#`, for example
        `imageServerOptions="{blend-color: '#015069', blend-alpha: 80}"`.
        See :ref:`usage-uri-image-hex`.

    ..  accordion-item:: My inline ViewHelper call with source sets is unreadable.
        :name: source-sets-variable
        :header-level: 2

        Nested arrays in inline notation get messy fast. Store the
        `sourceSets` array in a Fluid variable with :html:`<f:variable>` and
        pass the variable to the ViewHelper. See :ref:`usage-best-practice`.

    ..  accordion-item:: How do I bypass the external image server for one image?
        :name: pass-through
        :header-level: 2

        Set `passThrough="true"` on :html:`<lim:image>` or
        :html:`<lim:uri.image>`. The URL then points to the file processed by
        TYPO3 itself. See :ref:`usage-image-arguments`.

    ..  accordion-item:: Where do I get help?
        :name: help
        :header-level: 2

        See chapter :ref:`help`.
