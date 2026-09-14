..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

The extension ships three Fluid ViewHelpers:

*   :ref:`lim:widget.responsiveImage <usage-responsive-image>` renders a
    complete :html:`<picture>` element with media queries, source sets and
    optional WebP sources.
*   :ref:`lim:image <usage-image>` extends :html:`<f:image>` with source sets,
    WebP support and image server options.
*   :ref:`lim:uri.image <usage-uri-image>` extends :html:`<f:uri.image>` with
    image server options.

..  _usage-namespace:

Namespace
=========

The namespace `lim` is registered globally in :file:`ext_localconf.php`. To get
IDE support, declare it on top of your template:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Templates/Example.html

    <html xmlns:lim="http://typo3.org/ns/LIA/LiaImageserver/ViewHelpers"
          data-namespace-typo3-fluid="true">

..  _usage-best-practice:

Best practice: source sets as variables
=======================================

Inline notation is always possible in Fluid. The syntax gets messy as soon as
you deal with nested arrays, and `sourceSets` is a nested array. Store the
source sets in a Fluid variable with :html:`<f:variable>` and pass the variable
to the ViewHelper. See :ref:`usage-responsive-image-inline` for an example.

..  toctree::
    :titlesonly:

    ResponsiveImage
    Image
    UriImage
