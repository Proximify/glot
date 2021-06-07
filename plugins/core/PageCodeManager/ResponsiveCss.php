<?php

/**
 * File for class ResponsiveCss.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

use Proximify\Glot\Renderer;
use Proximify\Glot\ComponentPackage;

/**
 * Undocumented class
 */
class ResponsiveCss
{
    const GLUE = "\n";
    const DEFAULT_SIZE = 'generic';

    const DEV_CODE = 'customCode';
    const DEV_CSS = 'customCss';
    const DEV_SCSS = 'customSCSS';
    const CLASSES = 'classes';

    /** @var Renderer The parent Glot renderer. */
    protected $renderer;

    /** @var array Default themes of widgets */
    protected $defaultStyles = [];

    /** @var array inline responsive css */
    public $inlineResponsiveStyle = [];

    /** @var array Themes from widget packages */
    protected $widgetThemes = [];

    /** @var array CSS themes collected from widgets */
    protected $comboStyle = [];

    /** @var array Custom css codes */
    protected $devStyle = [];

    /**
     * Undocumented function
     *
     * @param Renderer $renderer
     */
    public function __construct(Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function initWidgetClassStyles(string $class)
    {
        $wp = $this->renderer->loadWidgetPackage($class);

        $this->collectWidgetThemes($wp);

        $this->loadDefaultStyle($class);
    }

    /**
     * Get website breakpoints.
     *
     * @return array
     */
    public function getBreakpoints(): array
    {
        // Take the breakpoints from one of the three configuration levels.
        $list = $this->renderer->getOption(
            Renderer::WEB_SERVICES,
            'breakpoints'
        );

        return $list;
        /** 
         * Ignoring the first breakpoint because it's the default case
         * @todo Is this correct? It seems that we are  expecting a GUI
         * related "screen" first option. We have to deal with that differently.
         * Also, it might make more sense to have the breakpoints defined as 
         * {size: {...}, size: {...}, ...} instead of [...].
         */
        // if ($list) {
        //     array_shift($list);
        // }

        // $breakpoints = [];

        // Expecting sizes: ['sm', 'md', 'lg', 'xl'];
        // foreach ($list as $value) {
        //     $size = $value['value'] ?? false;
        //     $pt = $value['breakpoint'] ?? false;

        //     if ($size && $pt) {
        //         $breakpoints[$size] = $pt;
        //     }
        // }

        // return $breakpoints;
    }

    /**
     * This function generates a style tag which set some css variables for
     * different breakpoints. These variables can be used in widget package.
     *
     * @return string
     */
    public function generateVariables(): string
    {
        $breakpoints = $this->getBreakpoints();

        $str = '';

        foreach ($breakpoints as $sz => $val) {
            $str .= "--page-env-breakpoint-$sz:{$val}px;";
        }

        if (!$str)
            return '';

        $str = ":root { $str }";

        return $this->renderer->isStaticRendering() ? $str : "<style class='glot_variables'>$str</style>";
    }

    /**
     * This function generates style tags for customized css code.
     * Related property in the json file(page) is "customCSS".
     *
     * @param array $devCss css code in different size.
     * @return string
     */
    public function generateCustomCSS(?array $devCss = null): string
    {
        if ($devCss === null) {
            $devCss = $this->devStyle;
        }

        $result = '';

        // Need to add one by one to make sure the size is added by right order;
        if (isset($devCss['generic'])) {
            $querySelector = $this->generateResponsiveQuery('generic');

            $result .= implode(self::GLUE, $devCss['generic']);
        }

        $sizes = ['sm', 'md', 'lg', 'xl'];

        foreach ($sizes as $value) {
            if (isset($devCss[$value])) {
                $querySelector = $this->generateResponsiveQuery($value);

                $result .= $querySelector . implode(self::GLUE, $devCss[$value]) . '}';
            }
        }

        return $result;
    }

    /**
     * This function collects the default style of the widget which is
     * defined in the website. The one we collect in another function
     * collectWidgetThemes is to collect default style which is defined in
     * the widget itself.
     *
     * @param string|null The name of widget.
     * @return void
     */
    public function loadDefaultStyle(string $widgetClass): void
    {
        $wp = $this->renderer->loadWidgetPackage($widgetClass);

        $style = $wp->getDefaultStyle();

        $settings = $wp->getWidgetSettings();

        $name = $settings['name'] ?? $wp->getShortClassName();

        $selector = ".$name";

        $styleContent = [];

        $style = $style ?: [];
        $this->buildWidgetStylings($style, $selector, $name, $styleContent, true);

        $this->defaultStyles[$name] = $styleContent;
    }

    /**
     * When render widget markup, we collect widget themes
     * Related property, widgetStyles
     *
     * @param array $content
     * @param string $selector selector of css rules
     * @param string $widget The name of the widget
     * @param array $responsiveCSS
     * @param boolean $isStyle
     * @return void
     */
    public function buildWidgetStylings(
        array $content,
        string $selector,
        string $widget,
        array &$responsiveCSS,
        bool $isStyle = false
    ) {
        unset($content[self::DEV_SCSS]);
        $devCSS = $this->getAndUnset($content, self::DEV_CSS, '');

        if (!isset($responsiveCSS[self::DEV_CSS])) {
            $responsiveCSS[self::DEV_CSS] = [];
        }

        if ($devCSS) {
            $this->collectCustomCSS($devCSS, $responsiveCSS[self::DEV_CSS]);
        }

        $sizes = ['sm', 'md', 'lg', 'xl', ''];

        foreach ($sizes as $value) {
            $properties = $value ? $this->getAndUnset($content, $value, []) : $content;

            $value = $value ?: 'generic';

            if ($properties) {
                $this->generateCSSProperties(
                    $properties,
                    $selector,
                    $widget,
                    $responsiveCSS,
                    $value,
                    $isStyle
                );
            }
        }
    }

    /**
     * Collect css rules for custom code
     *
     * @param array|string $css
     * @param array $devCSS
     * @return void
     */
    public function collectCustomCSS($css)
    {
        if (is_array($css)) {
            foreach ($css as $size => $value) {
                $this->devStyle[$size][] = $value;
            }
        } elseif ($css) {
            $this->devStyle['generic'][] = $css;
        }
    }

    /**
     * Merge media contents into a single string based on a given sprintf format string.
     *
     * @param string $format $format An sprintf format with three "%s" variables. For example,
     * "<style name='name' classType='type' widget='%s' size='%s'>%s</style>
     * @param string $widget The widget class name.
     * @param array $content Array with media sizes mapped to string data.
     * @return string The merged string.
     */
    public function mergeResponsive(string $format, string $widget, array $content): string
    {
        $sizes = [self::DEFAULT_SIZE, 'sm', 'md', 'lg', 'xl', 'dev'];
        $result = '';

        /**
         * Need to include the empty cases, because in developing mode,
         * we need to put css rules into the right position.
         * */
        foreach ($sizes as $size) {
            if (($data = $content[$size] ?? '') && is_array($data)) {
                $data = implode(self::GLUE, $data);
            }

            if ($this->renderer->isStaticRendering())
                $result .= sprintf($format, $data);
            else
                $result .= sprintf($format, $widget, $size, $data);
        }

        return $result;
    }

    /**
     * This function generates style tags for default css styles which
     * are collected by the above function defaultStyles.
     *
     * @return string
     */
    public function generateDefaultStyles(): string
    {
        //handle exporting mode
        if ($this->renderer->isStaticRendering()) {
            $format = "%s";

            $str = '';

            foreach ($this->defaultStyles as $widget => $content) {
                // Process custom case as the 'dev' breakpoint
                $content['dev'] = empty($content['customCss']) ? '' :
                    $this->generateCustomCSS($content['customCss']);

                $str .= $this->mergeResponsive($format, $widget, $content);
            }
            // $result = '<style class="defaultClasses">' .
            //     $str .
            //     '</style>';
            $result = $str;
        } else {
            $format =
                "<style isDefaultStyle=true widget='%s' size='%s'>%s</style>";

            $result = '';

            foreach ($this->defaultStyles as $widget => $content) {
                // Process custom case as the 'dev' breakpoint
                $content['dev'] = empty($content['customCss']) ? '' :
                    $this->generateCustomCSS($content['customCss']);

                $result .= $this->mergeResponsive($format, $widget, $content);
            }
        }

        return $result;
    }

    /**
     * This function generates style tags for css rules which
     * are set from GLOT.
     * Related property in the json file(page) is "css"
     *
     * @return string
     */
    public function generateGenericCSSStyles(): string
    {
        $content = $this->inlineResponsiveStyle;
        $sizes = ['sm', 'md', 'lg', 'xl'];

        if ($this->renderer->isStaticRendering()) {
            $result = $this->generateGenericCSSContents($content);
        } else {
            if (
                property_exists($this->renderer, 'editMode') &&
                $this->renderer->editMode
            ) {
                if ($data = $content[self::DEFAULT_SIZE] ?? '') {
                    $data = implode(self::GLUE, $data);
                }

                $result = '<style class="generic">' . $data . '</style>';

                foreach ($sizes as $size) {
                    if ($data = $content[$size] ?? '') {
                        $data = implode(self::GLUE, $data);
                    }

                    /**
                     * Need to include the empty cases, because in developing mode,
                     * we need to put css rules into the right position.
                     * */
                    $result .= '<style class="genericResponsive" size="' . $size . '">' .
                        $data . '</style>';
                }
            } else {
                $data = $this->generateGenericCSSContents($this->inlineResponsiveStyle);
                $result = $data ? '<style class="generic">' . $data . '</style>' : '';
            }
        }

        return $result;
    }

    /**
     * Generates css style tags
     *
     * @return string
     */
    public function generateGenericCSSContents($content): string
    {
        $sizes = ['sm', 'md', 'lg', 'xl'];

        if ($data = $content[self::DEFAULT_SIZE] ?? '') {
            $data = implode(self::GLUE, $data);
        }

        foreach ($sizes as $size) {
            if ($extra = $content[$size] ?? false) {
                $data .= implode(self::GLUE, $extra);
            }
        }

        return $data;
    }

    /**
     * This function generates style tags for other css styles which
     * are collected when rendered widgets.
     * 
     * Related property in the json file(page) is "widgetStyles".
     *
     * @return string
     */
    public function generateWidgetClasses(): string
    {
        $result = '';

        foreach ($this->comboStyle as $widgetName => $styles) {
            $wp = $this->renderer->loadWidgetPackage($widgetName);

            $widgetClasses = $this->renderer->readJSONFile($wp->getCustomClasses());

            if (is_array($widgetClasses)) {
                foreach ($widgetClasses as $style => $classContent) {
                    if (isset($styles[$widgetName . '_' . $style])) {
                        $value = $styles[$widgetName . '_' . $style];

                        $type = $value['type'];
                        $name = $value['name'];
                        $widget = $value['widget'] ?? '';

                        $content = $value['content'];

                        // Process custom case as the 'dev' breakpoint
                        $content['dev'] = empty($content['customCss']) ? '' :
                            $this->generateCustomCSS($content['customCss']);

                        if ($this->renderer->isStaticRendering() || !$this->renderer->editMode) {
                            $format = "%s";
                            // $result .= '<style class="widgetClasses">' .
                            //     $this->mergeResponsive($format, $widget, $content) .
                            //     '</style>';
                            $result .= $this->mergeResponsive($format, $widget, $content);
                        } else {
                            $format = "<style name='$name' classType='$type' widget='%s' size='%s'>%s</style>";
                            $result .= $this->mergeResponsive($format, $widget, $content);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * This function generates the style tags which contains styles
     * that defined in widget package.
     *
     * @return string
     */
    public function generateWidgetThemes(): string
    {
        $result = '';

        $sizes = ['sm', 'md', 'lg', 'xl'];

        foreach ($this->widgetThemes as $value) {
            foreach ($sizes as $size) {
                if (isset($value[$size])) {
                    $querySelector = $this->generateResponsiveQuery($size);

                    $result .= $querySelector . $value[$size] . '}';
                }
            }
        }

        return $result;
    }

    /**
     * Generate class name based on widget themes
     *
     * @param array $style the information of the theme
     * @param string $widget
     * @return string
     */
    public function generateWidgetClassName(array $style, string $widget): string
    {
        if (is_array($style) && isset($style['type'])) {
            $type = $style['type'];
            $name = $style['name'];

            return $type == 'generic' || $type == 'predefined' ? $name : $widget . '_' . $name;
        }
    }

    /**
     * When render widget markup, we collect widget themes
     * Related property, widgetStyles
     *
     * @param array|null $styles widget styles
     * @param string $className the name of widget
     * @return void
     */
    public function buildWidgetThemes(?array $styles, string $className)
    {
        $addedClasses = &$this->comboStyle;

        foreach ($styles as $style) {
            if (is_array($style) && isset($style['type'])) {
                $type = $style['type'];
                $name = $style['name'];

                $key = $this->generateWidgetClassName($style, $className);

                if (isset($addedClasses[$className][$key]) || $type == 'predefined') {
                    continue;
                }

                $content = $this->getWidgetClassContent($style, $className);

                $selector = $type == 'generic' ? ".$name" : ".$key";

                $styleContent = [];

                $this->buildWidgetStylings($content, $selector, $className, $styleContent, true);

                $addedClasses[$className][$key] = [
                    'type' => $type,
                    'name' => $name,
                    'widget' => $className,
                    'content' => $styleContent
                ];
            }
        }
    }

    /**
     * Note: The widget classes are loaded in a static way, so
     * all website objects share them. That means we can
     * also keep track of their styles(themes) in a static way.
     * Styles include the default style that defined in the widget itself
     * and those ones that defined in the website.
     *
     * @param ComponentPackage $wp The WidgetPackage object of the widget
     * @return void
     */
    public function collectWidgetThemes(ComponentPackage $cp)
    {
        //Collect default style that defined in the widget package
        $default = $cp->getPredefinedDefaultStyle();
        $className = $cp->getShortClassName();

        $this->collectThemesContent($default, $className);

        //Collect styles that defined in the website
        $themes = $cp->getWidgetClasses();

        foreach ($themes as $key => $value) {
            $this->collectThemesContent($value, $key);
        }
    }

    /**
     * Collect css rules for different breakpoints.
     *
     * @param array $content
     * @param string $selector
     * @param string $widget
     * @param array $responsiveCSS
     * @param string $size
     * @param boolean $isStyle
     * @return void
     */
    protected function collectCSSProperty(
        array $content,
        string $selector,
        string $widget,
        array &$responsiveCSS,
        string $size = 'generic',
        bool $isStyle
    ) {
        $cssSelector = $this->makeResponsiveSelector($size, $selector);

        $responsiveCSS[$size][] = $cssSelector;

        $pseudos = [];

        foreach ($content as $attr => $attrValue) {
            $first = substr($attr, 0, 1);

            if (in_array($first, [':', '>', '~', '+', ' '])) {
                $pseudoSelector = $selector . $attr;
                $pseudoValue = $attrValue;

                $pseudos[$pseudoSelector] = $pseudoValue;
            } else {
                if ($attr == 'background-image') {
                    $attrValue = $this->makeBackgroundImage($attrValue, $widget, $isStyle);
                } elseif ($attr == 'font-family') {
                    $this->renderer->getFonts()->collectWebsiteFont($attrValue);
                }

                if ($attr != 'classes' && $attr != 'hover' && !is_array($attr) && !is_array($attrValue)) {
                    $responsiveCSS[$size][] = $attr . ':' . $attrValue . ';';
                }
            }
        }

        $bracket = $size == 'generic' ? '}' : '} }';

        $responsiveCSS[$size][] = $bracket;

        foreach ($pseudos as $key => $value) {
            $this->collectCSSProperty($value, $key, $widget, $responsiveCSS, $size, $isStyle);
        }
    }

    /**
     * This function returns the url for background image.
     *
     * @param string $value the name of asset
     * @param string $widget
     * @param bool $isStyle
     * @return string
     */
    protected function makeBackgroundImage(string $value, string $widget, bool $isStyle): string
    {
        $result = $value;

        if ($value != 'none' && $value) {
            if ($this->renderer->isStaticRendering()) {
                if ($value != 'none' && $value) {
                    $result = "url('../../assets/" . $value . "')";
                }
            } else {
                $result = "url('" . $this->renderer->getAssets()
                    ->makeAssetUrl($value, $widget) . "')";
            }
        }

        return $result;
    }

    /**
     * This function generates media query based on size.
     *
     * @param string $size sm, md, lg, xl
     * @return string
     */
    private function generateResponsiveQuery(string $size): string
    {
        $breakpoints = $this->getBreakpoints();

        if ($size && $size !== 'generic') {
            $sizeVal = $breakpoints[$size];
            $result = "@media screen and (min-width: {$sizeVal}px) {";
        } else {
            $result = '';
        }

        return $result;
    }

    /**
     * Build css rules for different pseudo selectors.
     *
     * @param array $content
     * @param string $selector
     * @param string $widget
     * @param array $responsiveCSS
     * @param string $size
     * @param boolean $isStyle
     * @return void
     */
    private function generateCSSProperties(
        array $content,
        string $selector,
        string $widget,
        array &$responsiveCSS,
        string $size = 'generic',
        bool $isStyle = false
    ) {
        $propertyLang = $this->getAndUnset($content, 'propertyLang', []);
        if ($propertyLang) {
            foreach ($propertyLang as $lang => $cssProperty) {
                $langSelector = $selector . ":lang($lang)";
                $this->collectCSSProperty(
                    $cssProperty,
                    $langSelector,
                    $widget,
                    $responsiveCSS,
                    $size,
                    $isStyle
                );
            }
        }

        $this->collectCSSProperty($content, $selector, $widget, $responsiveCSS, $size, $isStyle);
    }

    /**
     * Make the responsive selector.
     *
     * @param string $size
     * @param string $selector
     * @return string
     */
    private function makeResponsiveSelector(string $size, string $selector): string
    {
        $breakpoints = $this->getBreakpoints();

        if ($size && $size !== 'generic') {
            $result = "@media screen and (min-width: {$breakpoints[$size]}px) { $selector {";
        } else {
            $result = "$selector {";
        }

        return $result;
    }

    /**
     * Get content of the widget theme
     *
     * @param array $style
     * @param string $widget
     * @return array
     */
    private function getWidgetClassContent(array $style, string $widget): array
    {
        if (is_array($style) && isset($style['type'])) {
            $type = $style['type'];
            $name = $style['name'];

            if ($type == 'custom') {
                $wp = $this->renderer->loadWidgetPackage($widget);
                $content = $this->renderer->readJSONFile($wp->getCustomClasses())[$name] ?? [];
            } elseif ($type == 'predefined') {
                $content = $this->renderer->loadWidgetPackage($widget)->getWidgetClasses($name, 'rb');
            } else {
                $fileName = $this->renderer->getClientFolder() . self::CLASSES . "/$name.json";

                $content = $this->renderer->readJSONFile($fileName);
            }

            return $content;
        }
    }

    /**
     * This function generates and collects the css code from the style content.
     * The collected css code will be wrapped inside a style tag and included
     * to the head of the page.
     *
     * @param array $content The piece of style. Eg,
     *     {
     *        "color":"red",
     *        "font-size":"16px"
     *     }
     * @param string $selector The style name and it will be the selector of css rules.
     * @return void
     */
    private function collectThemesContent(array $content, string $selector)
    {
        $result = [];

        $sizes = ['sm', 'md', 'lg', 'xl'];

        if ($content) {
            foreach ($sizes as $size) {
                $str = '';

                if (!empty($content[$size])) {
                    $str = '.' . $selector . '{';

                    foreach ($content[$size] as $key => $value) {
                        $str .= $key . ': ' . $value . ';';
                    }

                    $str .= '}';
                }

                if (!empty($content['customCss'][$size])) {
                    $str .= $content['customCss'][$size];
                }

                if ($str) {
                    $result[$size] = $str;
                }
            }
        }

        if ($result) {
            $this->widgetThemes[] = $result;
        }
    }

    /**
     * This function get the value of the key in the given array and unset the given key.
     *
     * @param array $array
     * @param string $key
     * @param string|array $default
     * @return string|array
     */
    private static function getAndUnset(array &$array, string $key, $default = null)
    {
        if (isset($array[$key])) {
            $value = $array[$key];
            unset($array[$key]);
        } else {
            $value = $default;
        }

        return $value;
    }
}
