<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Event;

use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Fired once per rendering call before the processing instructions are
 * transformed or routed to a delivery backend. Listeners may modify the
 * instruction set (e.g. add an abstract `overlay` instruction).
 *
 * Listeners MUST be idempotent: the event can fire more than once for the
 * same rendering (set/overwrite keys, never append).
 *
 * The context carries the opaque `liaContext` ViewHelper argument and is
 * read-only; it is never part of the instruction set handed to backends.
 */
final class ModifyProcessingInstructionsEvent
{
    public function __construct(
        private readonly FileInterface $image,
        private array $instructions,
        private readonly array $context,
    ) {}

    public function getImage(): FileInterface
    {
        return $this->image;
    }

    public function getInstructions(): array
    {
        return $this->instructions;
    }

    public function setInstructions(array $instructions): void
    {
        $this->instructions = $instructions;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
