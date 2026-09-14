<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Service;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Service\ImageService as ExtbaseImageService;

/*
 * Dual TYPO3 v13 + v14 compatibility:
 *   v14: parent ExtbaseImageService is `readonly class` → subclass MUST be readonly
 *   v13: parent is a regular class                      → subclass MUST NOT be readonly
 * PHP forbids both directions, so the class is declared conditionally.
 *
 * The 14.3.0 threshold is the declared lower bound for v14 in composer.json
 * ("^13.4 || ^14.3"); v14.0 to v14.2 are deliberately out of support, because
 * the parent became readonly in 14.3 and the conditional cannot straddle that
 * inside a single major.
 */
$typo3Version = (new Typo3Version())->getVersion();

/*
 * Precondition, not a fallback: on v14.0 to v14.2 the parent is already readonly
 * while the branch below would declare a non-readonly subclass. PHP then aborts
 * with "Non-readonly class ... cannot extend readonly class", which names neither
 * this extension nor the version constraint it violates.
 */
if (version_compare($typo3Version, '14.0.0', '>=') && version_compare($typo3Version, '14.3.0', '<')) {
    throw new \RuntimeException(
        'EXT:lia_imageserver supports TYPO3 v13.4 or v14.3 and above, but v' . $typo3Version . ' is installed. '
        . 'The Extbase ImageService became a readonly class in v14.3; a subclass cannot span that change.',
        1789336800
    );
}

if (version_compare($typo3Version, '14.3.0', '>=')) {
    readonly class ImageService extends ExtbaseImageService
    {
        use ImageServiceTrait;
    }
} else {
    class ImageService extends ExtbaseImageService
    {
        use ImageServiceTrait;
    }
}
