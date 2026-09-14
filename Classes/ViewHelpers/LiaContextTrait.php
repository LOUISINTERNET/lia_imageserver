<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\ViewHelpers;

use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Shared wiring for the `liaContext` ViewHelper argument: an opaque array
 * transported to ModifyProcessingInstructionsEvent listeners. The dispatch
 * helper in ImageService extracts and strips it before any backend sees
 * the instructions.
 */
trait LiaContextTrait
{
    protected function registerLiaContextArgument(): void
    {
        $this->registerArgument(
            'liaContext',
            'array',
            'Opaque context for ModifyProcessingInstructionsEvent listeners, e.g. {aiBadge: \'0\'}. Never forwarded to the image server.',
            false,
            [],
        );
    }

    /**
     * Merge the `liaContext` argument into an instruction set and inject
     * `_maxRenderWidth` (largest requested width of the rendering call).
     *
     * @param array $processingInstructions
     * @param int $maxRenderWidth largest requested width; 0 = unknown, the key stays absent
     *
     * @return array
     */
    protected function mergeLiaContextIntoInstructions(array $processingInstructions, int $maxRenderWidth): array
    {
        $liaContext = $processingInstructions['liaContext'] ?? [];
        ArrayUtility::mergeRecursiveWithOverrule($liaContext, $this->arguments['liaContext']);
        if ($maxRenderWidth > 0) {
            $liaContext['_maxRenderWidth'] = $maxRenderWidth;
        }
        if ($liaContext !== []) {
            $processingInstructions['liaContext'] = $liaContext;
        }
        return $processingInstructions;
    }
}
