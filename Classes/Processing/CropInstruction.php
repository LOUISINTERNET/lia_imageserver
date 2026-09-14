<?php

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Processing;

class CropInstruction implements Instruction
{
    /**
     * {@inheritDoc}
     */
    public function process($image, $instructions, $name, $value): array
    {
        if (is_array(@$value['cropIS'])) {
            foreach ($value['cropIS'] as $keyF => $valueF) {
                $instructions[$keyF] = "$keyF=$valueF";
            }
        }
        if (@$value['crop'] === null) {
            return $instructions;
        }

        if (is_string($value['crop'])) {
            $instructions['crop'] = 'crop=' . $value['crop'];
            $instructions['fit'] = 'fit=crop';
            return $instructions;
        }

        if (is_array($value['crop'])) {
            foreach ($value['crop'] as $keyF => $valueF) {
                $instructions[$keyF] = "$keyF=$valueF";
            }
            return $instructions;
        }

        if (is_object($value['crop']) && !$value['crop']->isEmpty()) {
            $cropConfig = $value['crop']->asArray();
            $left = round($cropConfig['x']);
            $top = round($cropConfig['y']);
            $width = round($cropConfig['width']);
            $height = round($cropConfig['height']);

            $instructions['rect'] = sprintf('rect=%s,%s,%s,%s', $left, $top, $width, $height);
        }
        return $instructions;
    }
}
