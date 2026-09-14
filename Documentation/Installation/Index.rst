..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Install with Composer
=====================

Install the extension via Composer:

..  code-block:: bash
    :caption: Terminal

    composer req lia/lia_imageserver

See also `Installing extensions, TYPO3 Getting started
<https://docs.typo3.org/permalink/t3start:installing-extensions>`__.

..  _installation-classic:

Install in Classic Mode
=======================

Download the extension from the TYPO3 Extension Repository at
`https://extensions.typo3.org/extension/lia_imageserver
<https://extensions.typo3.org/extension/lia_imageserver>`__ and install it in
the Extension Manager.

..  _installation-typoscript:

Include the TypoScript
======================

Include the static template :guilabel:`LIA Imageserver` in your site template
(:guilabel:`Site Management > TypoScript`, tab :guilabel:`Advanced Options`).
It provides the constant for the responsive image template path, see
:ref:`configuration-template`.
