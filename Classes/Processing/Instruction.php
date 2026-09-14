<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Processing;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\SingletonInterface;

interface Instruction extends SingletonInterface
{
    /**
     * @param FileInterface $image
     * @param array $instructions
     * @param string $name
     * @param mixed $value
     *
     * @return array
     */
    public function process($image, $instructions, $name, $value): array;
}
