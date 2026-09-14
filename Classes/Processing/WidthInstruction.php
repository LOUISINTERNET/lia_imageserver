<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Processing;

class WidthInstruction implements Instruction
{
    /**
     * {@inheritDoc}
     */
    public function process($image, $instructions, $name, $value): array
    {
        if (!isset($instructions['w']) && !empty($value)) {
            $instructions['w'] = "w=$value";
        }
        return $instructions;
    }
}
