<?php

/**
 * Router file for handling AJAX requests.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

// The name of the parent folder, 'api', is used by the renderer to infer that
// the request is from an AJAX call and that it expects a JSON response.
// The 'accepts' property of request's header is also considered by the handlers.
require_once '../index.php';
