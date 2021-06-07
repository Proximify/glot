<?php

/**
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 */

namespace Proximify\Glot;

/**
 * A generic, stoppable event class based loosely based on the PSR-14 specs.
 */
class Event
{
    /** The event name for the loading of non-JSON pages. */
    public const PAGE_LOAD = 'PageLoadEvent';

    /** The event name for the exporting of static dependencies. */
    public const SITE_EXPORT = 'SiteExportEvent';

    /** @var mixed Mutable response property. */
    public $response;

    /** @var string Event name. */
    private $name;

    /** @var array Event parameters. */
    private $params;

    /** @var bool Event propagation status. */
    private $stopped = false;

    public function __construct(string $name, array $params = [])
    {
        $this->name = $name;
        $this->params = $params;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
