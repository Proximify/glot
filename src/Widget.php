<?php

/**
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 */

namespace Proximify\Glot;

/**
 * The base class for all GLOT widgets.
 *
 * It is meant to be a simple base class in order to avoid polluting the
 * method namespace of widgets. Rich interaction with the Renderer
 * is offered via the site() object providing access to selected core plugins.
 */
abstract class Widget
{
    /** @var Renderer The singleton engine used to render GLOT markup. */
    protected $renderer;

    /** @var array|null Configuration options for the component. */
    protected $options;

    /**
     * Create a generic Widget.
     *
     * This based constructor cannot be overridden by extended classes.
     *
     * @param Renderer $renderer The caller website renderer.
     * @param array $options An optional key 'holderId' if the component is to
     * render content for a DOM object with a known unique HTML 'id' attribute.
     * All other keys depend on each component type and the caller.
     */
    final public function __construct(Renderer $renderer, array $options)
    {
        $this->renderer = $renderer;
        $this->options = $options;
    }

    /**
     * Create GLOT or HTML markup to instantiate a widget on a webpage.
     *
     * @param array|string|null $data The contents to render.
     * @param array $params The widget-defined rendering parameters and the
     * user-selected values for them.
     * @return array|string|null The GLOT or HTML markup of the widget.
     */
    abstract public function render($data, $params);

    /**
     * Declare the requests that the widget can handle. Useful for dynamic
     * page=level widgets.
     *
     * @param array $params The widget-defined rendering parameters and the
     * user-selected values for them.
     * @return array List if URL that the widget accepts (is this right?).
     */
    public static function generateRequests(array $params): array
    {
        return [];
    }

    /**
     * Get the options provided at construction time.
     *
     * @return array
     */
    final public function options(): array
    {
        return $this->options;
    }

    /**
     * Get the ID of the HTML element wrapping the widget contents.
     *
     * @return string|null
     */
    final public function holderId(): ?string
    {
        return $this->options['holderId'] ?? null;
    }

    /**
     * Make HTML markup from GLOT elements.
     *
     * @param array|string $elements
     * @return string
     */
    final public function renderMarkup($elements): string
    {
        return $this->renderer->renderMarkup($elements);
    }

    /**
     * Get an object for interacting with the renderer and get information about
     * the current page and the whole website.
     *
     * Example code for a widget's render() method.
     *
     * render(...) {
     *    $site = $this->site();
     *    $x = $site->assets->getXYZ();
     *    $y = $site->page->getXYZ();
     * }
     *
     * @return stdClass A generic object with an the following properties:
     * assets, page, finder, analytics.
     */
    final protected function site(): \stdClass
    {
        return $this->renderer->site();
    }

    /**
     * Require a system of plugin and trigger the plugin action based on
     * given options.
     *
     * @param string $class The class name of the plugin. It can be a local
     * namespace relative to the local Plugin folder, or an absolute one
     * that is loaded by a registered autoload function.
     * @param array $options Named arguments for the plugin's constructor.
     * @return mixed
     */
    final protected function require(string $class, array $options = [])
    {
        return $this->renderer->require($class, $options);
    }

    /**
     * Initialize the JavaScript class of the widget by adding all necessary
     * code in the "document ready" event of the webpage.
     *
     * @param array $params the user parameters received by the widget.
     * @param string $methodName The name of a method to call or empty to
     * call no method. An persistent object will be created first if the method
     * is not static or if $methodName is empty. Otherwise, the given static
     * method is called without creating an object.
     * @return bool true if initialization code was added, and false otherwise.
     */
    final protected function initJavaScriptWidget(
        array $params,
        string $methodName = 'render'
    ): bool {
        $target = [
            'holderId' => $this->holderId(),
            'class' => get_called_class(),
            'method' => $methodName,
            'options' => $this->options
        ];

        return $this->renderer->initJavaScriptWidget($params, $target);
    }
}
