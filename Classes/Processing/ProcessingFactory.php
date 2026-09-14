<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Processing;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ProcessingFactory implements Instruction
{
    /**
     * @var array $register
     */
    protected $register;

    /**
     * Default class constructor.
     */
    public function __construct()
    {
        $this->register = [
            'default' => PassThroughInstruction::class,
            'width' => WidthInstruction::class,
            'height' => HeightInstruction::class,
            'crop' => CropInstruction::class,
            'maxHeight' => MaxHeightInstruction::class,
            'maxWidth' => MaxWidthInstruction::class,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function process($image, $instructions, $name, $value): array
    {
        if (isset($this->register[$name])) {
            $instruction = GeneralUtility::makeInstance($this->register[$name]);
        } else {
            $instruction = GeneralUtility::makeInstance($this->register['default']);
        }
        return $instruction->process($image, $instructions, $name, $value);
    }
}
