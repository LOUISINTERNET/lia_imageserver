..  include:: /Includes.rst.txt

..  _start:

===============
LIA Imageserver
===============

:Extension key:
    lia_imageserver

:Package name:
    lia/lia_imageserver

:Version:
    |release|

:Language:
    en

:Author:
    LOUIS TYPO3 Developers

:License:
    This document is published under the
    `GNU General Public License v2.0 or later <https://spdx.org/licenses/GPL-2.0-or-later.html>`__.

:Rendered:
    |today|

----

Responsive images for TYPO3 Fluid: :html:`<picture>` and `srcset` rendering
with crop variants, WebP sources, lazy loading and fetch priority. Works with
TYPO3's own image processing out of the box. An external image server (for
example imgix via `lia/lia_middleware_imgix`) can take over processing and
delivery through the same ViewHelpers.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Installation <installation>`

        How to install the extension in Composer-based and Classic TYPO3
        installations.

    ..  card:: :ref:`Configuration <configuration>`

        Extension settings and how to override the responsive image template.

    ..  card:: :ref:`Usage <usage>`

        Examples and arguments of the three ViewHelpers.

    ..  card:: :ref:`Developer corner <developer>`

        The PSR-14 event, custom instructions and the overlay contract.

    ..  card:: :ref:`ViewHelper reference <viewhelpers>`

        Complete argument reference generated from the source code.

    ..  card:: :ref:`Frequently Asked Questions (FAQ) <faq>`

        These questions have been frequently asked.

    ..  card:: :ref:`How to get help <help>`

        Where to get help and how to report issues you found.

..  toctree::
    :maxdepth: 5
    :titlesonly:
    :hidden:

    Installation/Index
    Configuration/Index
    Usage/Index
    Developer/Index
    ViewHelpers/Index
    Faq/Index
    GetHelp/Index
