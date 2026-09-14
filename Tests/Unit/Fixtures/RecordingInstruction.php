<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Fixtures;

use LIA\LiaImageserver\Processing\Instruction;

/**
 * Externally registered instruction fake: marks the instruction set so a
 * test can prove the register dispatched to THIS class.
 */
final class RecordingInstruction implements Instruction
{
    public function process($image, $instructions, $name, $value): array
    {
        $instructions[$name] = 'handled-by-recording-instruction';
        return $instructions;
    }
}
