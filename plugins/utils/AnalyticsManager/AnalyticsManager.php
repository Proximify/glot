<?php

/**
 * File for class glot AnalyticsManager.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Utils;

/**
 * Interface-like class for all implementations of website analytics managers.
 */
class AnalyticsManager extends \Proximify\Glot\Plugin
{
    /**
     * Enable the collection of web analytics for the current page.
     *
     * @param array $options The analytics options are plugin dependent.
     * @return string|null The URL to use to submit web analytics or null if
     * the functionality is not available.
     */
    public function enablePageAnalytics(array $options = []): ?string
    {
        return null;
    }

    /**
     * Handle requests about web analytics.
     *
     * @param array $params The request options are plugin dependent.
     * @return string A JSON string with the results.
     */
    public function handleRequest(array $params): string
    {
        return '';
    }
}
