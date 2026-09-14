<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Processing;

/**
 * Where an overlay sits on the image it is composited onto.
 *
 * The vocabulary lives here, not in a backend, because every backend has to agree on
 * it: a URL backend translates it into its own syntax, a local compositor into pixel
 * coordinates. A producer is free to offer fewer values than these — restricting an
 * editor to the corners is a user-interface decision, not a change to the contract.
 */
enum OverlayAlignment: string
{
    case TopLeft = 'top-left';
    case TopCenter = 'top-center';
    case TopRight = 'top-right';
    case MiddleLeft = 'middle-left';
    case MiddleCenter = 'middle-center';
    case MiddleRight = 'middle-right';
    case BottomLeft = 'bottom-left';
    case BottomCenter = 'bottom-center';
    case BottomRight = 'bottom-right';

    /**
     * The two axes are exposed separately so that no backend parses the value itself:
     * imgix wants "bottom,left", a compositor wants coordinates.
     */
    public function vertical(): string
    {
        return match ($this) {
            self::TopLeft, self::TopCenter, self::TopRight => 'top',
            self::MiddleLeft, self::MiddleCenter, self::MiddleRight => 'middle',
            self::BottomLeft, self::BottomCenter, self::BottomRight => 'bottom',
        };
    }

    public function horizontal(): string
    {
        return match ($this) {
            self::TopLeft, self::MiddleLeft, self::BottomLeft => 'left',
            self::TopCenter, self::MiddleCenter, self::BottomCenter => 'center',
            self::TopRight, self::MiddleRight, self::BottomRight => 'right',
        };
    }
}
