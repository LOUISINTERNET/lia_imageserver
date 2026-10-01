<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Service;

/**
 * A width or height `auto` that cannot be derived: an error of the template, which the
 * ViewHelpers show in development and log elsewhere — never one of the file lookups they
 * otherwise catch.
 */
final class AutoDimensionException extends \InvalidArgumentException {}
