..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

..  _configuration-extension:

Extension configuration
=======================

The settings live in :guilabel:`Admin Tools > Settings > Extension
Configuration > lia_imageserver`.

..  confval:: allowedFileExtensions
    :name: ext-conf-allowedfileextensions
    :type: string
    :Default: (empty)
    :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['allowedFileExtensions']

    Comma-separated list of file extensions that are handed to the image
    server. When empty, the extension falls back to
    :php:`$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']`. A value set in
    the image server configuration of a file storage takes precedence over
    this global setting.

..  confval:: additionalParameter
    :name: ext-conf-additionalparameter
    :type: string
    :Default: (empty)
    :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['additionalParameter']

    Additional parameter that is copied into the image server configuration of
    every file storage. The image server adapter decides how the value is
    used.

..  note::
    The configuration form also shows the field :guilabel:`Image Quality`
    (`imageQuality`). This extension does not read that value.

..  _configuration-template:

Override the responsive image template
======================================

:html:`<lim:widget.responsiveImage>` renders the template file set by the
TypoScript constant
:typoscript:`plugin.tx_liaimageserver.settings.responsiveImageViewHelperTemplatePath`.
The constant expects the path to the template file.

#.  Copy
    :file:`EXT:lia_imageserver/Resources/Private/Templates/ViewHelpers/Widget/ResponsiveImage/Index.html`
    into your site package and adjust it.

#.  Point the constant to the new file:

    ..  code-block:: typoscript
        :caption: EXT:my_sitepackage/Configuration/TypoScript/constants.typoscript

        plugin.tx_liaimageserver.settings {
            responsiveImageViewHelperTemplatePath = EXT:my_sitepackage/Resources/Private/Templates/ViewHelpers/Widget/ResponsiveImage/Index.html
        }

#.  Make sure the static template :guilabel:`LIA Imageserver` and your own
    TypoScript are included, see :ref:`installation-typoscript`.
