<?php

/**
 * File for class PageCodeManager.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

use Proximify\Glot\ComponentPackage as CP;

require_once __DIR__ . '/LibraryManager.php';
require_once __DIR__ . '/ResponsiveCss.php';

/**
 * Manager of webpage code, including internal and external JS and CSS.
 */
class PageCodeManager extends \Proximify\Glot\Plugin
{
    private const GENERIC_LIB = 'generic';
    private const WIDGET_STYLE = 'widgetStyles';
    private const DEV_CODE = 'customCode';
    private const DEV_CSS = 'customCss';
    protected const EXPORT_STYLES = 'styles';

    /** @var LibraryManager Manager of library dependencies. */
    protected $libManager;

    /** @var ResponsiveCss Manager of CSS media queries. */
    protected $responsiveCss;

    /**
     * @var array JavaScript files which was collected from widgets
     * and would be included to the body of the page.
     * */
    protected $jsFiles = [];

    /** @var array CSS files which was collected from widgets
     * and would be included to the head of the page. */
    protected $cssFiles = [];

    /** @var array External css libraries. */
    protected $cssExternal = [];

    /** @var array External JS libraries. */
    protected $jsExternal = [];

    /** @var array Polyfill links which was collected from widgets
     * and would be included to the head of the page. */
    protected $polyfills = [];

    protected $corePolyfills = [];

    /** @var array JavaScript codes which was collected from widgets
     * and will put to a script tag and be included to the body of the page. */
    protected $jsInline = [];

    /** @var array Similar like JsInline, it will put to the document ready block. */
    protected $jsOnReady = [];

    /** @var array Boolean status of loaded widgets on the page. */
    protected $loadedWidgets = [];

    protected $widgetExtendedLibs = [];

    /** @var array Markup that will be included in the head of the page. */
    protected $headHtml = [];

    /** @var array Collection of head widgets. */
    protected $headWidgets;

    /** @var array Track which widgets requested the addition of JS code. */
    protected $hasJSCode = [];

    /**
     * Undocumented function
     *
     * @return LibraryManager
     */
    protected function getLibManager(): LibraryManager
    {
        return $this->libManager ?? $this->libManager = new LibraryManager();
    }

    /**
     * Undocumented function
     *
     * @return ResponsiveCss
     */
    protected function getResponsiveCss(): ResponsiveCss
    {
        return $this->responsiveCss ??
            $this->responsiveCss = new ResponsiveCss($this->renderer);
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getViewPortMeta(): string
    {
        $content = 'width=device-width, initial-scale=1, shrink-to-fit=no';

        return '<meta name="viewport" content="' . $content . '">';
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function renderPageHead(): array
    {
        if ($this->renderer->isStaticRendering())
            return $this->renderStaticPageHead();

        $globalStyle = implode("\n", $this->getHeadHtml());

        if ($this->renderer->getOption('renderingParams', 'skipHead'))
            return [
                '<meta charset="utf-8">',
                $this->getViewPortMeta(),
                $globalStyle
            ];

        $responsiveCss = $this->getResponsiveCss();

        $themes = $responsiveCss->generateWidgetThemes();

        /** @todo Do we always need to add this? */
        //This is used for solving the css environment variable problems.
        //If we can compile css environment variables correctly, we only need
        //this for non-static mode. 
        $widgetThemes = $themes ? "<style widgetTheme=true>$themes</style>" : '';

        /**
         * Resolve dependencies between libraries requested by different
         * widget classes.
         */
        $genericLibs = $this->getGenericLibs('css');

        $genericTag = $this->makeOnlineCSSLinks($genericLibs);

        $customCss = $responsiveCss->generateCustomCSS();

        // Make the css properties coming from customCSS
        $devTag = $customCss ? "<style class=\"dev\">$customCss</style>" : '';

        $fontCss = $this->renderer->getFonts();

        $this->renderer->initFolderUrl(
            __DIR__ . '/css',
            $this->renderer->getClientRootUrl() . 'css'
        );

        $cssFiles = array_column($this->getCSSFiles(), 'url');

        $head = [
            // '<meta charset="utf-8">',
            // $this->getViewPortMeta(),
            $this->makeCSSLinks($this->getPageCSS()),
            $genericTag,
            $this->makeCSSLinks($cssFiles),
            $globalStyle,
            $widgetThemes,
            $devTag,
            $responsiveCss->generateVariables(),
            $responsiveCss->generateDefaultStyles(),
            $responsiveCss->generateWidgetClasses(),
            $responsiveCss->generateGenericCSSStyles(),
            implode("\n", $fontCss->generateFontFaces()['fonts']),
            $fontCss->generateWidgetFonts()
        ];

        return array_filter($head);
    }

    public function renderStaticPageHead(): array
    {
        $headTags = implode("\n", $this->getHeadHtml());

        //For search
        if ($this->renderer->getOption('renderingParams', 'skipHead'))
            return [
                '<meta charset="utf-8">',
                $this->getViewPortMeta(),
                $headTags
            ];

        $responsiveInfo = $this->responsiveCss;

        $variables = $responsiveInfo->generateVariables();
        $defaultStyles = $responsiveInfo->generateDefaultStyles();

        $widgetClasses = $responsiveInfo->generateWidgetClasses();

        $fonts = $this->renderer->getFonts();
        $activeFonts = $fonts->generateFontFaces();

        // $preloads = $activeFonts['preloads'] ?? [];

        $widgetFonts = $fonts->generateWidgetFonts();

        $exportTag = '';

        $genericLibs = $this->getGenericLibs('css');

        $onlineCSS = $this->makeOnlineCSSLinks($genericLibs);

        $uniqCSS = $this->getCSSFiles();

        $widgetCSS = '';
        $widgetCSSBundle = '';

        if ($this->renderer->getOption('renderingParams', 'bundle') ?? true) {
            $cssFiles = array_column($uniqCSS, 'file');

            foreach ($cssFiles as $file) {
                $widgetCSSBundle .= $this->renderer->readFile($file) . "\n";
            }
        } else {
            $widgetCSS = $this->makeCSSLinks(array_column($uniqCSS, 'url'));
        }

        $hrefLangs = $this->makeHrefLangs($this->renderer->getLangSettings());

        $content = $variables . "\n" .
            $this->makeHeadStyles() . "\n" .
            $widgetCSSBundle . "\n" .
            $defaultStyles . "\n" .
            $widgetClasses . "\n" .
            implode("\n", $activeFonts['fonts'] ?? []) . "\n" .
            $responsiveInfo->generateGenericCSSStyles() . "\n" .
            $responsiveInfo->generateCustomCSS();

        $href = '';

        if ($content) {
            $href = $this->createPageCssFile($content);

            $exportTag = '<link rel="stylesheet" type="text/css" href="' . $href . '">';
        }

        return [
            '<meta charset="utf-8">',
            $this->getViewPortMeta(),
            $headTags,
            $this->makeCSSLinks($this->getPageCSS(), true),
            implode("\n", $hrefLangs),
            // implode("\n", $preloads),
            $widgetFonts,
            // $variables,
            $onlineCSS,
            $widgetCSS,
            // $this->makeCSSLinks($uniqCSS),
            // $headHTML,
            // $defaultStyles,
            // $widgetClasses,
            $exportTag
        ];
    }

    function makeHeadStyles()
    {
        $headHTML = implode("\n", $this->getHeadHtml());

        $domString = "<!DOCTYPE html>\n<html>\n<head>" .
            $headHTML . "\n</head>\n" . "\n</html>";

        $dom = $this->renderer->getPreRenderer()->makeDom($domString);

        $styles = $dom->getElementsByTagName('style');

        $headStyles = '';
        foreach ($styles as $node) {
            $headStyles .= $node->textContent;
        }

        return $headStyles;
    }

    /**
     * Generates the hreflang headers for all active languages.
     *
     * Note: override this function and return the empty string if
     * the hreflang don't need to be set (eg, in workspace mode).
     *
     * @param array $info Information about the languages of a website.
     * @return array List of '<link>' statements with the hreflang set.
     */
    public function makeHrefLangs(array $info): array
    {
        $langs = empty($info['languages']) ? [] : $info['languages'];
        $hrefs = [];
        $page = $this->renderer->pageName();
        $generics = [];

        foreach ($langs as $lang) {
            $name = $lang;

            $url = $page . "&lang=$name";
            $href = $this->renderer->getLocalizer()->parseHRefParam($url);

            $hrefs[] = '<link rel="alternate" href="' . $href .
                '" hreflang="' . $name . '" />';

            // Keep track of the generic-regions languages that we have
            $pos = strpos($name, '-');

            // There is a region...
            $code = $pos ? substr($name, 0, $pos) : $name;

            if ($pos) {
                // ... but if it is the first language we see, we take it
                if (!isset($generics[$code])) {
                    $generics[$code] = $name;
                }
            } else {
                // This language has no region, so it's generic
                $generics[$code] = false;
            }
        }

        // Make sure that there is a generic-region version for each language
        // If there isn't, choose the first region-specific one as the generic region
        foreach ($generics as $code => $name) {
            if ($name) {
                $url = $page . "&lang=$name";
                $href = $this->renderer->getLocalizer()->parseHRefParam($url);

                $hrefs[] = '<link rel="alternate" href="' . $href .
                    '" hreflang="' . $code . '" />';
            }
        }

        if (!empty($info['mainLang'])) {
            $url = $page . "&lang=" . $info['mainLang'];
            $href = $this->renderer->getLocalizer()->parseHRefParam($url);

            $hrefs[] = '<link rel="alternate" href="' . $href .
                '" hreflang="x-default" />';
        }

        return $hrefs;
    }

    /**
     * For static rendering, we save multiple css rules into one file.
     *
     * @param string $content
     * @return string
     */
    public function createPageCssFile(string $content): string
    {
        $pageName = $this->renderer->pageName();

        if (strpos($pageName, '/') === false) {
            $page = explode('.', $pageName)[0];
        } else {
            $a = explode('/', $pageName);
            $last = end($a);
            $page = explode('.', $last)[0];
        }

        $style = self::EXPORT_STYLES;
        $folder = $this->renderer->getRenderingDir() . "$style/pages/";

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $cssFile = "$folder{$page}.css";
        file_put_contents($cssFile, $content);

        $href = $this->renderer->getClientRootUrl() . "$style/pages/$page.css";
        return $href;
    }

    /**
     * Undocumented function
     *
     * @param string $id
     * @param string $widgetClass
     * @param array $attrs
     * @return array
     */
    public function getWidgetCssClasses(string $id, string $widgetClass, array $attrs): array
    {
        $responsiveCss = $this->getResponsiveCss();
        $classes = [CP::normalizeClientClass($widgetClass)];

        //Collect css properties which will be included into the head of the page
        //collect styles
        if ($widgetStyles = $attrs[self::WIDGET_STYLE]) {
            $responsiveCss->buildWidgetThemes(
                $widgetStyles,
                $widgetClass
            );

            //add style class name to the markup
            foreach ($widgetStyles as $value) {
                $cssClass = $responsiveCss->generateWidgetClassName(
                    $value,
                    $widgetClass
                );

                $classes[] = $cssClass;
            }
        }

        if ($cssSpecs = $attrs['css']) {
            $responsiveCss->buildWidgetStylings(
                $cssSpecs,
                ".$widgetClass#$id",
                $widgetClass,
                $responsiveCss->inlineResponsiveStyle
                /** @todo This is wrong! */
            );
        }

        if ($devCss = $attrs[self::DEV_CODE][self::DEV_CSS] ?? false) {
            $responsiveCss->collectCustomCSS($devCss);
        }

        return $classes;
    }

    /**
     * Collect the  css, styles, js, polyfills, and so on that the widget needs.
     * All of those files will be included to the head or body of the page.
     *
     * @param string $class The class name of the widget.
     * @return void
     */
    public function initWidgetClass(string $class)
    {
        if (isset($this->loadedWidgets[$class])) {
            return;
        }

        $this->loadedWidgets[$class] = true;

        $renderer = $this->renderer;

        // The widget package should already be in the cache since it is
        // actually read when the widget class is auto loaded.
        $wp = $renderer->loadWidgetPackage($class);

        $shortName = $wp->getShortClassName();

        $path = $renderer->parseClientPath($class);

        $widgetFolder = $renderer->getClientFolder() . "widgets/$path";

        // $cssFile = $widgetFolder . "/css/$shortName.css";
        $cssFile = $wp->getMainCSSFilePath();

        /** @todo We might get the time stamp in a different way. */
        $suffix = $renderer->isVirtualWebsite() ? '&t=' . uniqid() : '';

        $version = $wp->getWidgetVersion();

        if (file_exists($cssFile) && !$this->isCSSFileEmpty($cssFile)) {
            $cssFolderPath = $wp->getSchemaRules()[CP::CSS]['folder'];

            $cssUrl = $renderer->getClientWidgetUrl($path . '/'
                . $cssFolderPath);

            $renderer->initFolderUrl($widgetFolder . "/$cssFolderPath", $cssUrl);

            $this->cssFiles[] = [
                'url' => "$cssUrl/$shortName.css?v=$version" . $suffix,
                'file' => $cssFile
            ];

            /** @todo Decide if this is the right place for this call. */
            // $this->getResponsiveCss()->collectWidgetThemes($wp);
        }

        $customLibs = $this->getExtendedLibraries($class, CP::CUSTOM_LIBS);
        $externalLibs = $this->getExtendedLibraries($class, CP::EXTERNAL_LIBS);

        $files = $customLibs[CP::JS];

        $jsFolderPath = $wp->getSchemaRules()[CP::JS]['folder'];
        $jsUrl = $renderer->getClientWidgetUrl($path . "/$jsFolderPath");

        foreach ($files as $fname) {
            if (!$fname)
                continue;

            if (file_exists("$widgetFolder/$jsFolderPath/$fname")) {
                $this->jsFiles[] = "$jsUrl/$fname?v=$version" . $suffix;
            } else {
                $file = $this->getLibFile($class, $fname, $jsFolderPath);

                if ($file) {
                    $this->jsFiles[] = $file;
                }
            }
        }

        $code = $wp->getOnReadyCode();

        if ($code) {
            $this->jsOnReady[] = $code;
        }

        /**
         * @todo If necessary, we could call a static method of the widget
         * class to get the code from the PHP code of the widget. eg,
         * $class::getOnReadyCode()
         */

        $externalCss = $externalLibs[CP::CSS];

        $libManager = $this->getLibManager();

        $libManager->setLibDependency(
            $externalCss,
            'css',
            self::GENERIC_LIB
        );

        foreach ($externalCss as $value) {
            $value['widgetPath'] = $path;
            $this->cssExternal[] = $value;
        }

        // $polyfills = $externalLibs[CP::POLYFILL];
        $polyfills = $wp->getFromPolyfillIO();

        $this->polyfills = array_merge($this->polyfills, $polyfills);

        $externalJs = $externalLibs[CP::JS];

        $libManager->setLibDependency(
            $externalJs,
            'js',
            self::GENERIC_LIB
        );

        foreach ($externalJs as $value) {
            $value['widgetPath'] = $path;
            $this->jsExternal[] = $value;
        }

        // $code = $widget->getJSCode();

        // if ($code)
        //     $this->jsInline[] = $code;

        /** @todo This is the second reference to responsiveCss */
        // $this->getResponsiveCss()->loadDefaultStyle($class);
        $this->getResponsiveCss()->initWidgetClassStyles($class);

        $this->initCorePolyfills($wp);
    }

    function initCorePolyfills($wp)
    {
        // $settings = $wp->getWidgetSettings();

        // $polyfills = $settings['polyfills'] ?? [];
        $polyfills = $wp->getPolyfills() ?? [];
        $polyfills = array_keys($polyfills);

        $this->corePolyfills = array_merge($this->corePolyfills, $polyfills);
    }

    /**
     * Get website breakpoints.
     *
     * @return array
     */
    public function getBreakpoints(): array
    {
        return $this->getResponsiveCss()->getBreakpoints();
    }

    /**
     * Add custom JavaScript codes to the website head.
     *
     * @param string $code Custom JS code.
     * @return void
     */
    public function addOnReadyCode(string $code): void
    {
        if (trim($code)) {
            $this->jsOnReady[] = "(function() { $code })();";
        }
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getOnReadyCode(): array
    {
        return $this->jsOnReady;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getCSSFiles(): array
    {
        return array_unique($this->cssFiles, SORT_REGULAR);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getJSFiles(): array
    {
        return array_unique($this->jsFiles);
    }

    /**
     * Return external css library
     *
     * @return array
     */
    public function getExternalCSS(): array
    {
        return $this->cssExternal;
    }

    /**
     * Return external js library
     *
     * @return array
     */
    public function getExternalJS(): array
    {
        return $this->jsExternal;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getInlineJS(): array
    {
        return $this->jsInline;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getPolyfills(): array
    {
        return array_unique($this->polyfills);
    }

    /**
     * Get the library dependencies of a widget class including that of its
     * ancestor classes.
     *
     * @param string $class
     * @param string $type
     * @param bool $isParent For recursive calls.
     * @return array
     */
    public function getExtendedLibraries(string $class, string $type, bool $childNeeds = false): array
    {
        // Try getting the data from the cache first
        $libs = $this->widgetExtendedLibs[$class][$type] ?? false;

        if ($libs !== false) {
            return $libs;
        }

        $wp = $this->renderer->loadWidgetPackage($class);
        $libs = $wp->getLibraries($type, $childNeeds);

        $this->initJsAssetsFolder($class, $libs);

        $parentNeeds = $type == CP::CUSTOM_LIBS && count($libs[CP::JS]);

        // Add the parent libraries recursively
        $this->extendLibraries($class, $type, $libs, $parentNeeds);

        if (!isset($this->widgetExtendedLibs[$class])) {
            $this->widgetExtendedLibs[$class] = [];
        }

        // Cache the results and return the libraries
        return $this->widgetExtendedLibs[$class][$type] = $libs;
    }

    /**
     * Add the parent libraries recursively.
     *
     * @param string $class
     * @param string $type
     * @param array $libs
     * @param bool $childNeeds
     * @return void
     */
    public function extendLibraries(string $class, string $type, array &$libs, bool $childNeeds = false): void
    {
        $parentClass = get_parent_class($class);

        if (!$parentClass) {
            return;
        }
        // if (CP::ROOT_WIDGET_CLASS == $parentClass) {
        //     return;
        // }

        $parentLibs = $this->getExtendedLibraries($parentClass, $type, $childNeeds);

        if ($parentLibs) {
            foreach ($parentLibs as $libType => $value) {
                array_splice($value, count($value), 0, $libs[$libType] ?? []);

                $libs[$libType] = array_unique($value, SORT_REGULAR);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param string $mode
     * @return array
     */
    public function getGenericLibs(string $mode): array
    {
        if ($mode == 'css') {
            $libs = $this->getExternalCSS();
        } elseif ($mode == 'js') {
            $libs = $this->getExternalJS();
        } else {
            throw new \Exception("Unknown mode '$mode'");
        }

        return $this->getLibManager()->getGenericLibs($libs, $mode);
    }

    /**
     * This function collects html codes which will be put into
     * the head of the page.
     * @param sting|array $html
     * @return void
     */
    public function addHeadHtml($html)
    {
        if (is_array($html)) {
            $html = implode("\n", $html);
        }

        $this->headHtml[] = $html;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getHeadHtml(): array
    {
        return $this->headHtml;
    }

    /**
     * Head widgets are those widgets which are placed inside head tag
     * This function returns all head widgets which will be put into the page.
     * It includes those widgets installed in website level and page level.
     *
     * @return array
     */
    public function getPageLevelWidgets(): array
    {
        if ($this->headWidgets) {
            return $this->headWidgets;
        }

        $headWidgets = $this->renderer->loadBasePageHead();

        $data = $this->renderer->loadPageTemplateData();

        $clientHeadWidgets = $data['head'] ?? [];

        $this->headWidgets = $this->mergeHeadWidgets($clientHeadWidgets, $headWidgets);

        return $this->headWidgets;
    }

    /**
     * Make scripts tags. It includes widget library, polyfills and custom js
     * code that declared in widget package.
     *
     * @return string
     */
    public function renderPageSourceCode(): string
    {
        $polyfills = $this->getPolyfills();

        $polyfillScript = '';

        if ($polyfills) {
            $str = implode('%2C', $polyfills);
            $polyfillSrc = "https://polyfill.io/v3/polyfill.min.js?features=default%2C" . $str;

            $polyfillScript = "<script crossorigin='anonymous' src='$polyfillSrc'></script>";
        }

        $genericLibs = $this->getGenericLibs('js');

        $genericTag = $this->makeOnlineJSLinks($genericLibs);

        if ($jsOnReady = $this->getOnReadyCode()) {
            $jsOnReady = trim(implode("\n", $jsOnReady));

            // A self-invoking anonymous JS function must be wrapped in (...) or
            // start with !. Both options are valid.
            $readyJS = "(function() {
			function onReady() { $jsOnReady }
            (document.attachEvent ? document.readyState === 'complete' : 
                document.readyState !== 'loading') ? onReady() : 
                    document.addEventListener('DOMContentLoaded', onReady);
            })();";
        } else {
            $readyJS = '';
        }

        $inlineJS = $this->getInlineJS();

        $inlineJS = ($readyJS || $inlineJS) ?
            implode("\n", [
                '<script type="text/javascript">',
                implode("\n", $inlineJS),
                $readyJS,
                '</script>'
            ]) : '';

        $source = [
            $this->makeJSLinks($this->renderer->getPageJS()),
            $genericTag,
            $this->makeJSLinks($this->getJSFiles()),
            $inlineJS
        ];

        if ($polyfillScript) {
            array_unshift($source, $polyfillScript);
        }

        $corePolyfills = array_unique($this->corePolyfills);

        if ($this->renderer->getRenderingType() == $this->renderer::STATIC_RENDERING) {
            /**
             * regenerator-runtime library.
             * Used for enable async/await and some other advanced features in 
             * JavaScript after parse the JS files by @babel
             */
            $polyfillSettings = $this->renderer->loadRendererSettings('polyfills');

            foreach ($corePolyfills as $value) {
                $extraScripts = $polyfillSettings[$value]['scripts'] ?? '';

                if ($extraScripts) {
                    array_unshift($source, $extraScripts);
                }

                $src = $polyfillSettings[$value]['src'] ?? '';

                if ($src) {
                    $defer = $polyfillSettings[$value]['defer'] ?? '';
                    $async = $polyfillSettings[$value]['async'] ?? '';

                    $attributes = '';
                    if ($defer)
                        $attributes .= " defer";

                    if ($async)
                        $attributes .= " async";

                    $script = "<script src='$src'$attributes></script>";

                    $extraScript = $polyfillSettings[$value]['script'] ?? false;

                    if ($extraScript) {
                        $data = $extraScript['data'] ?? '';
                        $pos = $extraScript['pos'] ?? 'after';

                        $script = $pos == 'pre' ? $data . "\n" . $script : $script . "\n" . $data;
                    }

                    array_unshift($source, $script);
                }
            }
        }

        return implode("\n", array_filter($source));
    }

    /**
     * Get the answer as to whether the widget has requested the addition
     * of custom JS code into the page.
     *
     * @return bool
     */
    public function hasJSCode(string $class, string $id): bool
    {
        return $this->hasJSCode[$class][$id] ?? false;
    }

    /**
     * Initialize the JavaScript class of the widget by adding all necessary
     * code in the "document ready" event of the webpage.
     *
     * @param array $params the user parameters received by the widget.
     * @param string $target An array of the form 
     *    ['holderId' => '', 'class' => '', 'method => ''] 
     * defining the HTML ID of the element, the widget class of the element
     * (i.e. the caller's class), and the name of a method to call. If the 
     * method is not set, 'render' is assumed. The method is
     * optional and can be set to the empty ('' or false) to call no method. 
     * A persistent object will be created first if the method
     * is not static or if 'method' is empty. Otherwise, the given static 
     * method is called without creating an object.
     * @return bool true if initialization code was added and false otherwise.
     */
    public function initJavaScriptWidget(array $params, array $target): bool
    {

        if (empty($target['class'])) {
            return false;
        }

        $widgetClass = $target['class'];
        $methodName = $target['method'] ?? 'render';

        // Get information about the methods in the widget's JS class.
        $wp = $this->renderer->loadWidgetPackage($widgetClass);
        $classInfo = $wp->getClientClassInfo();

        // If the class has no code, there is nothing to initialize
        if (!$classInfo || empty($classInfo['hasCode'])) {
            return false;
        }

        $holderId = $target['holderId'] ?? '';

        // Record the fact that the widget has JS code
        $this->hasJSCode[$widgetClass][$holderId] = true;

        $target['jsClass'] = $wp->getJavaScriptClass();

        // The class exists and has some code in it
        $method = ($methodName && isset($classInfo['methods'][$methodName])) ?
            $classInfo['methods'][$methodName] : false;

        if ($method && $method['isStatic']) {
            // Call a *static* method of the JavaScript class.
            $this->callStaticClientMethod($target, $params);
        } else {
            // Empty method is allowed to just create the JS object.
            // If a method name is given, it is called. If
            // the method doesn't exist, there will be a JS error. That's okay.
            $this->createClientObject($target, $params);
        }

        return true;
    }

    /**
     * This function makes css links for collected local library.
     *
     * @param array $files Collected local library.
     * @return string <link> tags
     */
    protected function makeCSSLinks(array $files): string
    {
        $links = [];

        foreach ($files as $file) {
            $links[] = '<link type="text/css" rel="stylesheet" href="' .
                $file . '"/>';
        }

        return implode("\n", $links);
    }

    /**
     * This function returns css files which is used to reset css
     * properties in the page.
     *
     * @return array
     */
    public function getPageCSS(): array
    {
        $mode = $this->renderer->getRenderingType();

        if ($mode == $this->renderer::STATIC_RENDERING) {
            // $paths = ['css/normalize-7.0.0.css'];
            $paths = [];

            $pageCss = 'css/pages/' . $this->renderer->pageName() . '.css';

            if (file_exists($this->renderer->getClientFolder() . $pageCss))
                $paths[] = $pageCss;

            $this->convertExportCSS($paths);
        } else {
            $paths = [
                // $this->renderer->getClientRootUrl() . 'css/normalize-7.0.0.css'
            ];
        }

        return $paths;
    }

    /**
     * In exporting mode, we correct the path for css files
     *
     * @param array $array
     * @return void
     */
    public function convertExportCSS(array &$array)
    {
        foreach ($array as &$value) {
            $str = subStr($value, 0, 3);

            if ($str == 'css') {
                $value = substr_replace($value, self::EXPORT_STYLES, 0, 3);

                $value = $this->renderer->getClientRootUrl() . $value;
            }
        }
    }

    /**
     * Make css links for collected external library.
     *
     * @param array $urls Collected external library.
     * @return string <link> tags.
     */
    protected function makeOnlineCSSLinks(array $urls): string
    {
        $links = [];

        foreach ($urls as $url) {
            if (is_array($url) && isset($url['libPath'])) {
                $url = $this->renderer->getClientWidgetUrl($url['libPath']);
            }

            $links[] = '<link type="text/css" rel="stylesheet" href="' .
                $url . '"/>';
        }

        return implode("\n", $links);
    }

    /**
     * This function makes js scripts for collected external library
     *
     * @param array $urls Collected external library
     * @return string <script> tags
     */
    protected function makeOnlineJSLinks(array $urls): string
    {
        $links = [];

        foreach ($urls as $url) {
            if (is_array($url) && isset($url['libPath'])) {
                $url = $this->renderer->getClientWidgetUrl($url['libPath']);
            }

            $links[] = '<script type="text/javascript" src="' . $url .
                '"></script>';
        }

        return implode("\n", $links);
    }

    /**
     * Make JS scripts for collected local library.
     *
     * @param array $files Collected local library.
     * @return string <scripts> tags
     */
    protected function makeJSLinks(array $files): string
    {
        $links = [];

        foreach ($files as $file) {
            $links[] = '<script type="text/javascript" src="' . $file .
                '"></script>';
        }

        return implode("\n", $links);
    }

    /**
     * Generate url for css or js files.
     *
     * @param string $class The name of the widget, with namespace.
     * @param string $fname The name of the library file.
     * @param string $folderPath The type of the file, js or css.
     * @return string the url of the file which will be used as href or src.
     */
    private function getLibFile(string $class, string $fname, string $folderPath): string
    {
        $renderer = $this->renderer;

        $parentClass = get_parent_class($class);

        if (!$parentClass) {
            return false;
        }

        $path = $renderer->parseClientPath($parentClass);
        $widgetFolder = $renderer->getClientWidgetFolder() . $path;

        if (file_exists($widgetFolder . "/$folderPath/$fname")) {
            $wp = $renderer->loadWidgetPackage($parentClass);
            $result = $renderer->getClientWidgetUrl(
                "$path/$folderPath/$fname?v=" . $wp->getWidgetVersion()
            );
        } else {
            $result = $this->getLibFile($parentClass, $fname, $folderPath);
        }

        return $result;
    }

    /**
     * This function checks if the css file is empty or not.
     * We only want to include css file which is not empty.
     *
     * @param string $file The absolute path of the css file.
     * @return string
     */
    private function isCSSFileEmpty(string $file): string
    {
        // $contents = file_get_contents($file);

        // return !trim($contents);
        clearstatcache();
        return !filesize($file);
    }

    /**
     * This function merges head widgets in page level and website level
     * Base on the settings of the head widget, we will merge or replace
     * those head widgets which are installed both in page level and website
     * level.
     *
     * @param array $clientHeadWidgets head widgets installed in page level.
     * @param array $headWidgets head widgets installed in website level.
     * @return array
     */
    private function mergeHeadWidgets(array $clientHeadWidgets, array $headWidgets): array
    {
        $heads = [];

        foreach ($headWidgets as $value) {
            $widgetClass = $value['widget'];

            if ($this->isMergeableWidget($widgetClass, $clientHeadWidgets)) {
                $heads[] = $value;
            }
        }

        return array_merge($heads, $clientHeadWidgets);
    }

    /**
     * This function checks if the head widgets are mergeable
     * In the head widget settings, users can select if they want to replace
     * or merge the head widget.
     *
     * @param string $class The class of the widget
     * @param array $collection Installed widgets
     * @return boolean
     */
    private function isMergeableWidget(string $class, array $collection): bool
    {
        $result = true;

        foreach ($collection as $value) {
            $clientWidget = $value['widget'];

            if ($class == $clientWidget) {
                if (
                    isset($value['params']['_replaceWebsiteWidget']) &&
                    $value['params']['_replaceWebsiteWidget']
                ) {
                    return false;
                }
            }
        }

        return $result;
    }

    private function initJsAssetsFolder($class, $libs)
    {
        $renderer = $this->renderer;
        $path = $renderer->parseClientPath($class);
        $widgetFolder = $renderer->getClientFolder() . "widgets/$path";

        $wp = $renderer->loadWidgetPackage($class);
        $jsFolderPath = $wp->getSchemaRules()[CP::JS]['folder'];

        if (!empty($libs[CP::JS]) && is_dir("$widgetFolder/$jsFolderPath")) {
            $widgetUrl = $renderer->getClientWidgetUrl("$path/$jsFolderPath");

            $renderer->initFolderUrl("$widgetFolder/$jsFolderPath", $widgetUrl);
        }

        //For widget assets, we can just create a symlink for assets folder.
        if (is_dir($widgetFolder . '/assets')) {
            $renderer->initFolderUrl(
                $widgetFolder . '/assets',
                $renderer->getClientWidgetUrl($path . '/assets')
            );
        }
    }

    /**
     * Add JavaScript code to the "document ready" function by
     * creating a client-side widget object and calling its render() function.
     *
     * Note 1: It is assumed that there is a class in JS representing the
     * client side of this widget, and that it has the method: render(object).
     *
     * Note 2: The constructor of the class (or an ancestor) should save the
     * object in a static class variable so that the it persists beyond its
     * declaration scope.
     *
     * @param array $target Optional name of an existing method to call 
     * after creating the object.
     * @param array $params An array with parameters to pass to the method. 
     * Usually, the $params are the user parameters provided to the widget.
     * @return void
     */
    protected function createClientObject(array $target, array $params): void
    {
        $className = $target['jsClass'];

        $holderId = $target['holderId'] ?? '';
        $methodName = $target['method'] ?? 'render';
        $options = $target['options'] ?? false;

        $settings = $options ? json_encode($options) : '{}';

        $jsCode = "new $className('$holderId', $settings)";

        if ($methodName) {
            $params = json_encode($params);
            $jsCode = "($jsCode).$methodName($params)";
        }

        // Add the JS code to the "document ready" function.
        // The __Widget constructor saves the object in a static class variable.
        $this->addOnReadyCode($jsCode . ';');
    }

    /**
     * Add a call to a static JavaScript method on the client side for this
     * widget's instance in the "document ready" function.
     *
     * @param array $target The name of an existing static method in the 
     * JavaScript class representing this widget.
     * @param array $params An array with parameters to pass to the method. 
     * Usually, the $params are the user parameters provided to the widget.
     * @return void
     */
    protected function callStaticClientMethod(array $target, array $params): void
    {
        $className = $target['jsClass'];

        $holderId = $target['holderId'] ?? '';
        $methodName = $target['method'] ?? 'render';

        $params = json_encode($params);

        $jsCode = "$className.$methodName('$holderId', $params);";

        // Add the JS code to the "document ready" function.
        $this->addOnReadyCode($jsCode);
    }
}
