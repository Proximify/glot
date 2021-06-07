<?php

/**
 * Router file for the Renderer class.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

require_once __DIR__ . '/../src/Renderer.php';

// Both the constructor and the request handler accept options as arguments
// for customizing their default behaviour. The response might be an HTML page
// or JSON data, depending on the request header.
(new Proximify\Glot\Renderer())->outputResponse();
