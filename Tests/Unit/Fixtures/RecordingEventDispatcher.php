<?php

declare(strict_types=1);

/*
* This file is part of the "lia_imageserver" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*/

namespace LIA\LiaImageserver\Tests\Unit\Fixtures;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * In-memory dispatcher fake: records every dispatched event and invokes the
 * configured listeners in order, mirroring the PSR-14 contract.
 */
final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var list<object>
     */
    public array $dispatchedEvents = [];

    /**
     * @var list<callable(object): void>
     */
    private array $listeners;

    public function __construct(callable ...$listeners)
    {
        $this->listeners = $listeners;
    }

    public function dispatch(object $event): object
    {
        $this->dispatchedEvents[] = $event;
        foreach ($this->listeners as $listener) {
            $listener($event);
        }
        return $event;
    }
}
