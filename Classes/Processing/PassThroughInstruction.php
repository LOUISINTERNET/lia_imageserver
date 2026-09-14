<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Processing;

use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PassThroughInstruction implements Instruction
{
    /**
     * {@inheritDoc}
     */
    public function process($image, $instructions, $name, $value): array
    {
        if ($value !== null && !is_scalar($value)) {
            GeneralUtility::makeInstance(LogManager::class)
                ->getLogger(self::class)
                ->warning('Dropped non-scalar processing instruction: only scalar values can be passed through to the image server.', [
                    'instruction' => $name,
                    'valueType' => get_debug_type($value),
                ]);
            return $instructions;
        }
        if (!isset($instructions[$name]) && !empty($value)) {
            // Do not URL-encode the value here: the imgix UrlBuilder already
            // rawurlencodes every query value, so pre-encoding causes a
            // double-encode (e.g. "#" -> "%23" -> "%2523"), which the image
            // server rejects (breaks blend-color, auto=format,compress, etc.).
            $instructions[$name] = $name . '=' . $value;
        }
        return $instructions;
    }
}
