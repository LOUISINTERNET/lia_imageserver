..  include:: /Includes.rst.txt

..  _developer-events:

======
Events
======

..  _developer-events-modify-processing-instructions:

ModifyProcessingInstructionsEvent
=================================

:php:`LIA\LiaImageserver\Event\ModifyProcessingInstructionsEvent` fires once
per rendering call: before the processing instructions are transformed or
routed to a delivery backend, and on every exit path (image server URL, skip,
`passThrough`, local core processing). Listeners can modify the instruction
set, for example add an abstract `overlay` instruction that delivery backends
map to their own mechanism, see :ref:`developer-overlay`.

The event exposes:

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   -   Method
        -   Description
    *   -   :php:`getImage()`
        -   The (unwrapped) :php:`FileInterface` being rendered.
    *   -   :php:`getInstructions()` / :php:`setInstructions(array)`
        -   The processing instructions (read / replace).
    *   -   :php:`getContext()`
        -   Read-only opaque context from the `liaContext` ViewHelper
            argument. `_maxRenderWidth` carries the largest requested width
            of the call.

..  important::
    Listeners MUST be idempotent (set or overwrite keys, never append): the
    event can fire more than once for the same rendering. The `liaContext`
    key itself never reaches a backend. It is stripped before dispatch and
    only available via :php:`getContext()`.

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/AddOverlayToProcessingInstructions.php

    <?php

    declare(strict_types=1);

    namespace Vendor\MyExtension\EventListener;

    use LIA\LiaImageserver\Event\ModifyProcessingInstructionsEvent;
    use LIA\LiaImageserver\Processing\Overlay;
    use LIA\LiaImageserver\Processing\OverlayAlignment;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    #[AsEventListener]
    final class AddOverlayToProcessingInstructions
    {
        public function __invoke(ModifyProcessingInstructionsEvent $event): void
        {
            if (($event->getContext()['aiBadge'] ?? null) === '0') {
                return;
            }
            $instructions = $event->getInstructions();
            $instructions[Overlay::INSTRUCTION_NAME] = (new Overlay(
                imageIdentifier: '/_badges/example.png',
                alignment: OverlayAlignment::BottomLeft,
            ))->toInstruction();
            $event->setInstructions($instructions);
        }
    }

..  _developer-events-instruction-register:

Register custom instruction classes
===================================

Every key of the instruction set is processed by an :php:`Instruction` class.
The defaults cover `width`, `height`, `crop`, `maxWidth` and `maxHeight`;
unknown keys are passed through. Register a class for a custom key in
:file:`ext_localconf.php`. External registrations win over the defaults.

..  code-block:: php
    :caption: EXT:my_extension/ext_localconf.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lia_imageserver']['instructionRegister']['myKey']
        = \Vendor\MyExtension\Processing\MyInstruction::class;
