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
     * @var array<string, class-string<Instruction>>
     */
    protected array $instructionRegister = [
        'default' => PassThroughInstruction::class,
        'width' => WidthInstruction::class,
        'height' => HeightInstruction::class,
        'crop' => CropInstruction::class,
        'maxHeight' => MaxHeightInstruction::class,
        'maxWidth' => MaxWidthInstruction::class,
    ];

    /**
     * Other extensions extend the register by mapping an instruction key to
     * an Instruction class name in
     * $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['instructionRegister'];
     * external registrations win over the defaults.
     */
    public function __construct()
    {
        $additionalInstructionRegister = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['instructionRegister'] ?? [];
        $this->instructionRegister = array_replace($this->instructionRegister, $additionalInstructionRegister);
    }

    /**
     * {@inheritDoc}
     */
    public function process($image, $instructions, $name, $value): array
    {
        $className = $this->instructionRegister[$name] ?? $this->instructionRegister['default'];
        $instruction = GeneralUtility::makeInstance($className);
        return $instruction->process($image, $instructions, $name, $value);
    }
}
