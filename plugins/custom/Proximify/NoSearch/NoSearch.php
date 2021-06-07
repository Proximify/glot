<?php

/**
 * File for plugin class NoSearch.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify;

/**
 * Trivial plugin class to disable the default search functionality.
 * 
 * When constructing a renderer (or in the settings file), set 
 * {
 *    Renderer:ENV => [
 *       'systemPlugins' => [ 
 *          'Async\ContentFinder' => '\Proximify\NoSearch'
 *      ]
 *    ]
 * }
 */
class ContentFinder extends \Proximify\Glot\Plugin
{
    /**
     * @inheritDoc
     */
    public function enableWebsiteSearch(array $options = []): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function handleRequest(array $params): string
    {
        return '';
    }
}
