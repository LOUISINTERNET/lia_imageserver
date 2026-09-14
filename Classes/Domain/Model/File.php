<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Domain\Model;

class File extends \TYPO3\CMS\Core\Resource\File
{
    /**
     * @var ?mixed
     */
    protected $processingInstructions;

    /**
     * @var bool
     */
    protected $isS3File = false;

    /**
     * @return mixed
     */
    public function getProcessingInstructions(): mixed
    {
        return $this->processingInstructions;
    }

    /**
     * @param mixed $processingInstructions
     */
    public function setProcessingInstructions($processingInstructions): void
    {
        $this->processingInstructions = $processingInstructions;
    }

    /**
     * @return mixed
     */
    public function getisS3File(): bool
    {
        return $this->isS3File;
    }

    /**
     * @param bool $isS3File
     */
    public function setIsS3File(bool $isS3File): void
    {
        $this->isS3File = $isS3File;
    }
}
