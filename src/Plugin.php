<?php

/**
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 */

namespace Proximify\Glot;

/**
 * The base class for all GLOT plugins.
 * 
 * It is meant to be a simple base class in order to avoid polluting the 
 * method namespace of plugins.
 */
class Plugin
{
    /** @var Renderer The singleton engine used to render GLOT markup. */
    protected $renderer;

    /** @var array|null Configuration options for the component. */
    protected $options;

    /**
     * Create a generic Plugin.
     * 
     * This based constructor cannot be overridden by extended classes.
     *
     * @param Renderer $renderer The caller website renderer.
     * @param array $options All keys in the array depend on each plugin type.
     */
    final public function __construct(Renderer $renderer, array $options)
    {
        $this->renderer = $renderer;
        $this->options = $options;
    }

    /**
     * Handle asynchronous requests (e.g. ajax requests).
     *
     * @param array $params
     * @return string
     */
    public function handleRequest(array $params): string
    {
        return '';
    }
}
