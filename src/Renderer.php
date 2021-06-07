<?php

/**
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 */

namespace Proximify\Glot;

use Proximify\Glot\Plugin\Core;
use Proximify\Glot\Plugin\Async;
use Proximify\Glot\Plugin\Utils;
use Exception;

require_once __DIR__ . '/ComponentPackage.php';
require_once __DIR__ . '/Event.php';

defined('JSON_THROW_ON_ERROR') or define('JSON_THROW_ON_ERROR', 0);

/**
 * Renderer of a GLOT webpage in a client website. Call buildPage(...) to
 * render a webpage.
 */
class Renderer
{
    /**
     * Optional rendering type for static website outputs.
     * @var string
     */
    public const STATIC_RENDERING = 'static';

    /**
     * Name of the settings category that define the rendering environment.
     * Options: renderingType, systemUrl, systemPlugins, logger.
     */
    public const ENV = 'environment';

    /**
     * Name of the settings category under which all system paths are set.
     * Options: root, sites, www, plugins.
     */
    public const PATHS = 'paths';

    /** Name of the web-services settings file. */
    public const WEB_SERVICES = 'webservices';

    /** Special attribute added to the output markup of widget containers. */
    public const WIDGET_ATTRS = 'data-widget_';

    /** Folder name for the global namespace. */
    public const GLOBAL_NS = '_global';

    /** The name for a request argument specifying the rendering language. */
    public const LANG = 'lang';

    /**
     * Name and data of the page to render.
     * @var array
     */
    protected $currentPage;

    /**
     * The name of the website.
     * @var string
     */
    protected $websiteName;

    /** @var bool True of the website name was NOT given. */
    protected $hasDefaultWebsite;

    /** @var array GET parameters from the URL query string. */
    protected $pageParams;

    /** @var array Loaded instances of root-level plugins. */
    private $systemPlugins;

    /** @var array Loaded JSON data files. */
    private static $loadedFileData = [];

    /**
     * The first website renderer created is considered to be the
     * main one. The main renderer registers the auto loader for widget
     * classes, and is the only one that can perform pre-renderings.
     * @var self
     */
    private static $mainRenderer;

    /** @var array Loaded widget package by the widget auto loader. */
    private static $widgetPackages = [];

    /**
     * Registered listeners of rendering events.
     * @var array
     */
    private static $listeners = [];

    /**
     * Cache rendered HTML, prevent re-render all the pages for exporting search files.
     * @var array
     */
    private static $renderedPages = [];

    /**
     * Settings of the website renderer class.
     * @var array
     */
    private $rendererSettings = [];

    /** @var array Settings of the client website project. */
    private $clientSettings = [];

    /** @var string The current language for rendering. */
    private $activeLang;

    /** @var array Construction options (possibly extended at run time). */
    private $options;

    /** @var array Boolean flags to keep track of initialized folders. */
    private $folderUrls = [];

    /** @var stdClass A collection of plugins to get info about the website. */
    private $site;

    /** @var Localizer Reference to the core Localizer plugin. */
    private $localizer;

    /** @var PageCodeManager Reference to the core PageCodeManager plugin. */
    private $pageCode;

    /**
     * Construct a website renderer.
     *
     * @param array $options Options grouped by categories. Some options
     * can also be given in settings files named settings/{$category}.json.
     * Example options:
     * {
     *   Renderer::ENV: {
     *      renderingType: 'static',
     *      plugins: {folder: '...', namespace: '...'},
     *      systemPlugins: {'Async\ContentFinder': '\Proximify\NoSearch', ...}
     *   },
     *   Renderer::LANG: 'fr-ca'
     * }
     */
    public function __construct(array $options = [])
    {
        // Save the given options
        $this->options = $options;

        $this->initSystemPaths();

        // Register the autoload method if the object is the first renderer
        if (!self::$mainRenderer) {
            self::$mainRenderer = $this;
            spl_autoload_register([$this, 'autoload']);
        }
    }

    /**
     * Create a clone renderer.
     */
    public function __clone()
    {
        // System plugins are not meant to be shared across clones
        $this->systemPlugins = [];
        $this->site = [];

        // Recreate the two main core plugins
        $this->localizer = $this->getLocalizer();
        $this->pageCode = $this->getPageCode();
    }

    /**
     * Render the requested page if possible or a 404 page if not.
     *
     * @param array $params URL arguments (i.e. the query '?' part of the URL).
     * @param string|null $siteName The internal name of the website to load.
     * @param string|null $pageUrl A relative URL to the resource to load.
     * @return string
     */
    public function buildPage(
        array $params,
        ?string $websiteName = null,
        ?string $pageUrl = null
    ): string {
        $html = $this->renderPage($params, $websiteName, $pageUrl);

        // If there is no page data, send 404 header and page.
        return $html ?? $this->getAssets()->get404Page();
    }

    /**
     * Handle the request of a generic API method.
     *
     * @param array $params @see initialize()
     * @param string|null $siteName @see initialize()
     * @param string|null $pageUrl @see initialize()
     * @return string
     */
    public function handleRequest(
        array $params,
        ?string $siteName = null,
        ?string $pageUrl = null
    ): string {
        // Initialize the request of a generic API method
        $path = $this->initialize($params, $siteName, $pageUrl);

        $path = trim($path, '/');

        // Not giving error information on purpose to avoid unwanted probing
        // Exclude relative paths ('/../') and file extensions 'aaa.bbb'
        if (!$path || strpos($path, '.') !== false) {
            return '';
        }

        $class = '\\' . str_replace('/', '\\', $path);

        // Check if it could be a special "asynchronous" system plugin
        $pos = strpos($path, '/');
        $async = ($pos && substr($path, 0, $pos) == 'Async');

        // The special "asynchronous" system plugins are checked first
        $plugin = $async ? ($this->getSystemPlugin(substr($class, 1)) ?:
            $this->require($class)) : $this->require($class);

        return $plugin ? $plugin->handleRequest($params) : '';
    }

    /**
     * Echo a response to a server request. A 404 page and header is generated
     * if a non-exiting page is requested.
     *
     * @param array $options It can define a 'params' property with an array of
     * request parameters, and an 'isApi' property a boolean value.
     * @return void
     */
    public function outputResponse(array $options = []): void
    {
        // The request params can be given in the options or inferred from
        // the CGI request if there is one.
        $params = $options['params'] ??
            (($_SERVER['REQUEST_METHOD'] ?? '') == 'POST') ? $_POST : ($_GET ?? []);

        // If 'isApi' undefined, try to infer it by assuming that a calling CGI 
        // script exist with a parent folder named 'api' is an API request.
        $isApi = $options['isApi'] ??
            (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) == 'api');

        try {
            if ($isApi) {
                $response = $this->handleRequest($params);
            } else {
                $response = $this->renderPage($params);

                // If there is no page data, send 404 header and page.
                if ($response === null) {
                    http_response_code(404);
                    $response = $this->getAssets()->get404Page();
                }
            }
        } catch (Exception $e) {
            $response = $isApi ? json_encode(['error' => $e->getMessage()]) :
                '<h3>' . $e->getMessage() . '</h3>';
        }

        echo $response;
    }

    /**
     * Get the effective path to the resource from the request URL.
     *
     * @param string $url A relative URL to the resource to load.
     * @param string|null $siteName The internal name of the website to load. If
     * not given and defined in the system's options, it is assumed to be the
     * prefix of $url up to the first '/'.
     * @return array An array with the effective page path, website name, and flag
     * indicting whether the website name is a default case (@todo change this)
     */
    public function getResourcePath(string $url, ?string $siteName = null): array
    {
        // The target website name might be defined in a configuration file or
        // be inferred from the domain name set in $_SERVER.
        if ($siteName !== null || !$this->getWebsiteRootFolder()) {
            /** @todo find another way to deal with default site names. */
            $hasDefaultWebsite = false;
            $siteName = $siteName ?: '';
        } elseif ($siteName = $this->findWebsiteName()) {
            // Keep track of the fact that it's a registered website name
            $hasDefaultWebsite = true;
        } elseif ($url) {
            // The first level of the URL is taken as a custom website name
            $hasDefaultWebsite = false;

            // Expecting: website-name/pageUrl
            if ($pos = strpos($url, '/')) {
                $siteName = substr($url, 0, $pos);
                $url = substr($url, $pos + 1);
            } elseif ($pos === false) {
                $siteName = $url;
                $url = '';
            } else {
                throw new Exception('Invalid empty website name');
            }
        } else {
            throw new Exception('Missing website name. Example:
              http://localhost/.../www/?siteName/pageUrl');
        }

        // Disallow names that start with a period, specially the name '..'
        if (($siteName[0] ?? '') == '.') {
            throw new Exception("Invalid website name '$siteName'");
        }

        return [$url, $siteName, $hasDefaultWebsite];
    }

    /**
     * Set the website name and requested URL.
     *
     * @todo The arguments should be $websiteName, $pageUrl, $params, where
     * all are mandatory and params is already converted.
     * 
     * The first array KEY of the GET request is taken as the page path
     * e.g. https://real-url/?page-path&lang=en
     *
     * @param array $params URL arguments (i.e. the query '?' part of the URL).
     * @param string|null $siteName The internal name of the website to load.
     * @param string|null $url A relative URL to the resource to load.
     * @return string The effective URL to the requested resource.
     */
    public function initialize(
        array $params,
        ?string $siteName = null,
        ?string $url = null
    ): string {
        // Clear the current page
        $this->currentPage = null;

        // Clear the cached system plugins in case of re-initialization
        $this->systemPlugins = [];
        $this->site = [];

        // Save the given page rendering parameters
        $this->pageParams = $params;

        // Get the first key in the array. We ignore the value of the key
        // because the key itself us the URL.
        if ($url === null) {
            $url = $this->getUrlFromParams($params);
        }

        [$url, $siteName, $isDef] = $this->getResourcePath($url, $siteName);

        $this->websiteName = $siteName;
        $this->hasDefaultWebsite = $isDef;

        // Set the language of the page
        $this->setActiveLang($params[self::LANG] ?? '');

        // Create the two main code plugin needed for rendering a webpage.
        $this->localizer = $this->getLocalizer();
        $this->pageCode = $this->getPageCode();

        // The effective URL to the requested resource (without parameters)
        return $url;
    }

    /**
     * Set the language of the page being rendered. Validate that the language
     * exist, and default to an appropriate alternative otherwise.
     *
     * @param string $lang A language code. e.g., en-ca for English Canada. If
     * $lang is empty, it is taken from the construction options or from the
     * $_POST and $_GET parameters (otherwise it defaults to 'en').
     * @return void
     */
    public function setActiveLang(string $lang = ''): void
    {
        if (!$lang) {
            $lbl = self::LANG;
            $lang = empty($this->options[$lbl]) ? (empty($_POST[$lbl]) ?
                (empty($_GET[$lbl]) ? 'en' : $_GET[$lbl]) :
                $_POST[$lbl]) : $this->options[$lbl];
        }

        $this->activeLang = $this->makeValidLang($lang);
    }

    /**
     * Make an project folder available via a web URL that is readable by
     * the web server hosting the renderer. It is used to create folder for
     * widget files such as assets, JS, and CSS. It can also be used to expose
     * API folders defined by plugins.
     *
     * @param string $target The absolute path to an internal project folder.
     * @param string $url The desired relative URL (from the site's base URL)
     * to the folder.
     *
     * @return void
     */
    public function initFolderUrl(string $target, string $url): void
    {
        //We don't need to link files for static rendering
        if ($this->getRenderingType() == self::STATIC_RENDERING) {
            return;
        }

        // Remove optional trailing slash for symlink to work
        $url = rtrim($url, '/');

        if ($this->folderUrls[$url] ?? false) {
            return;
        }

        $this->folderUrls[$url] = true;

        $link = $this->getWebFolder() . $url;

        if (file_exists($link)) {
            return;
        }

        $pos = strrpos($link, '/');
        $root = substr($link, 0, $pos);

        $dir = $root;

        if (!is_dir($root) && !mkdir($root, 0755, true)) {
            $this->log("Missing write access to web folder '$root'");

            // Find the top folder that exists and check that it's writable
            while (!file_exists($dir)) {
                $dir = dirname($dir);
            }

            if (!is_writable($dir)) {
                throw new Exception("The folder '$dir' must be writable");
            }
        }

        $target = rtrim($target, '/');

        symlink($target, $link);
    }

    /**
     * Get the public root URL which might be used for src or href attribute.
     * It's the root URL for assets. It allows for the aliased absolute or
     * relative paths in the src attribute of assets. Instead, it assumes an
     * alias defined with respect to the document root. Eg. /clients/
     *
     * @return string
     */
    public function getClientRootUrl(): string
    {
        //return relative path for static rendering
        if ($this->getRenderingType() == self::STATIC_RENDERING) {
            /** @todo see if this is okay */
            $name = $this->getRenderPageName();

            return implode('', array_fill(0, substr_count($name, '/'), '../'));

            // $a = explode('/', $this->getRenderPageName());
            // $counter = count($a) - 1;

            // $str = '../';

            // for ($i = 0; $i < $counter; $i++) {
            //     $str .= '../';
            // }

            // Renderer::log($result);
            // Renderer::log($str);
            // return $str;
        }

        return 'public/' . ($this->websiteName ? $this->websiteName . '/' : '');
    }

    /**
     * Get a URL to the API endpoint to communicate with a plugin.
     *
     * @param object|string $plugin The plugin object or the plugin class.
     * @param array $args The HTTP query arguments to add to the URL. If
     * $args[self::LANG] is not set, it's assumed to be the active language.
     * @return string The generated URL.
     */
    public function getClientApiUrl($plugin, array $args = []): string
    {
        $class = is_string($plugin) ? $plugin : get_class($plugin);

        $path = str_replace('\\', '/', $class);

        $args[self::LANG] = $args[self::LANG] ?? $this->activeLang;

        $query = http_build_query($args);

        return 'api/?' . $this->websiteName . '/' . $path . '&' . $query;
    }

    /**
     * Get the public URL for widget assets.
     *
     * @param string $path
     * @return string
     */
    public function getClientWidgetUrl(string $path): string
    {
        return $this->getClientRootUrl() . 'widgets/' . $path;
    }

    /**
     * Get the root folder of ALL client websites available for rendering.
     *
     * @return string|null A path to the 'sites' folder or null if the
     * system is running on a single site mode.
     */
    public function getWebsiteRootFolder(): ?string
    {
        // Only consider the construction options for this case.
        return $this->options[self::PATHS]['sites'];
    }

    /**
     * Get the version of the website, such as as 'workspace', 'draft', and
     * 'published'.
     *
     * The version is used by getWebsiteFolder() to locate the website files by
     * assuming that it corresponds to a sub-folder of getWebsiteRootFolder().
     *
     * @return string
     */
    public function getWebsiteVersion(): string
    {
        return $this->options['site']['version'] ?? '';
    }

    /**
     * Get the client website folder. i.e. the website that is being rendered.
     *
     * @return string
     */
    public function getWebsiteFolder(): string
    {
        $sites = $this->getWebsiteRootFolder();

        return $sites ? $sites . $this->websiteName . '/' :
            $this->getProjectFolder();
    }

    /**
     * Get the client website folder. i.e. the website that is being rendered.
     * It is the combination of getWebsiteFolder() and getWebsiteVersion().
     *
     * @return string
     */
    public function getClientFolder(): string
    {
        $dir = $this->getWebsiteFolder();
        $ver = $this->getWebsiteVersion();

        return self::addEndingSlash($ver ? $dir . $ver : $dir);
    }

    /**
     * Get the public web folder that is set as the "document root" of the
     * web server (e.g., the DocumentRoot of a virtual host).
     *
     * @return string
     */
    public function getWebFolder(): string
    {
        return $this->options[self::PATHS]['www'];
    }

    /**
     * Load the widget package of a given widget class.
     *
     * @param string $class The name of the widget, with namespace
     * separated by \, /, or __.
     * @return ComponentPackage
     */
    public function loadWidgetPackage(string $class): ?ComponentPackage
    {
        $class = self::normalizeClass($class);

        if (isset(self::$widgetPackages[$class])) {
            return self::$widgetPackages[$class];
        }

        if (class_exists($class, false)) {
            $path = dirname((new \ReflectionClass($class))->getFileName());

            if (strrchr($path, '/') == '/src') {
                $path = dirname($path);
            }
        } else {
            $path = $this->getClientWidgetFolder() . (strpos($class, '\\') ?
                str_replace('\\', '/', $class) : self::GLOBAL_NS . '/' . $class);
        }
       
        try {
            $cp = new ComponentPackage($path, $class);
        } catch (Exception $e) {
            $this->log($e->getMessage());
            return null;
        }

        // Cache the object using the given class name
        return self::$widgetPackages[$class] = $cp;
    }

    /**
     * The GET request received from the URL query string.
     *
     * @return array|null
     */
    public function getPageParams(): ?array
    {
        return $this->pageParams;
    }

    /**
     * Return the requested page name. Useful for static renderings.
     *
     * @return string
     */
    public function getRenderPageName(): string
    {
        return $this->currentPage['givenPath'] ?? $this->pageName();
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getClientWidgetFolder(): string
    {
        return $this->getClientFolder() . 'widgets/';
    }

    /**
     * This function coverts the class name to the path of directory.
     *
     * @param string $class The class name of the widget. If there is no
     * namespace, the default namespace is self::GLOBAL_NS.
     * @return string Path of the directory of the widget. namespace/class.
     */
    public function parseClientPath(string $class): string
    {
        $pieces = explode('\\', $class);

        $widgetName = array_pop($pieces);

        return $pieces ? implode('/', $pieces) . '/' . $widgetName :
            self::GLOBAL_NS . '/' . $widgetName;
    }

    /**
     * Normalize a given class name by replacing alternative namespace
     * separators with the one use by PHP. Also remove the optional backslash
     * at position 0 so that the class name is canonical.
     *
     * @param string $class A possibly non-canonical class name.
     * @return string The canonical version of the class name.
     */
    public static function normalizeClass(string $class): string
    {
        // '__' is the our choice of separator in JS and CSS classes.
        // '/' is convenient in JSON because in needs no scaping.
        $class = str_replace(['__', '/'], '\\', $class);

        // Remove prefix (for class-name caching scenarios)
        return $class[0] == '\\' ? substr($class, 1) : $class;
    }

    /**
     * Get the settings in the widget package of a given widget class.
     *
     * @param string $class The name of the widget class.
     * @return array Array of widget settings.
     */
    public function getWidgetSettings($class): array
    {
        return $this->loadWidgetPackage($class)->getWidgetSettings();
    }

    /**
     * Get the parameters of the widget or, if empty, the extended parameters
     * of its parent. It works recursively to find the closest non-empty
     * parameters in the ancestor path.
     *
     * @param string $class The name of the widget class.
     * @return array Array of widget parameters.
     */
    public function getExtendedWidgetParams(string $class): array
    {
        return $this->loadWidgetPackage($class)->getExtendedParams($this);
    }

    /**
     * Load the data of a json file encoding an array as whose root-level
     * element is assumed to be an array.
     *
     * @param string $filename Absolute path of the file
     * @return array|string|null An array if the file exist and has valid json data.
     * @throws JsonException If the page's JSON contents are invalid.
     */
    public function readJSONFile(string $filename)
    {
        if (!file_exists($filename)) {
            return null;
        }

        if (empty(self::$loadedFileData[$filename])) {
            self::$loadedFileData[$filename] = json_decode(
                file_get_contents($filename),
                true, // assoc output
                512, // default depth
                JSON_THROW_ON_ERROR
            );
        }

        return self::$loadedFileData[$filename];
    }

    /**
     * Load the data of a json file encoding an array as whose root-level
     * element is assumed to be an array.
     *
     * @param string $filename Absolute path of the file
     * @return string
     */
    public function readFile(string $filename)
    {
        if (!file_exists($filename)) {
            return null;
        }

        if (empty(self::$loadedFileData[$filename])) {
            self::$loadedFileData[$filename] = file_get_contents($filename);
        }

        return self::$loadedFileData[$filename];
    }

    /**
     * Get the website name.
     *
     * @return string|null
     */
    public function websiteName(): ?string
    {
        return $this->websiteName;
    }

    /**
     * Get the active page name (i.e., the real path to a page file).
     *
     * @return string
     */
    public function pageName(): string
    {
        return $this->currentPage['name'] ?? '';
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getPageCSS(): array
    {
        return $this->getPageCode()->getPageCSS();
    }

    /**
     * Get the page settings in the website folder.
     *
     * @return array|string
     */
    public function getPageMap()
    {
        /** @todo Consider a different name and location for this */
        return $this->loadDataFile('settings', 'glot_pageSettings');
    }

    /**
     * Render the HTML of the current webpage defined by initialize($params).
     *
     * @param array $params URL arguments (i.e. the query '?' part of the URL).
     * @param string|null $siteName The internal name of the website to load.
     * @param string|null $pageUrl A relative URL to the resource to load.
     * @return string|null
     */
    public function renderPage(
        array $params,
        ?string $siteName = null,
        ?string $pageUrl = null
    ): ?string {
        // Initialize the request of a webpage
        $path = $this->initialize($params, $siteName, $pageUrl);

        /** @todo this is an error. page name is not unique. */
        $page = pathinfo($path, PATHINFO_FILENAME);

        $lang = $this->getLanguage();

        if (!empty(self::$renderedPages[$lang][$page])) {
            return self::$renderedPages[$lang][$page];
        }

        [$name, $data] = $this->loadPage($path);

        if ($data === null) {
            return null;
        }

        // Keep a copy of the page for later uses and keep the original path
        // to the file (might not be the real path).
        $this->currentPage = [
            'name' => $name,
            'data' => $data,
            'givenPath' => $path
        ];

        return self::$renderedPages[$lang][$page] = $this->render($data);
    }

    /**
     * Render HTML from the given data. It assumes that everything needed is 
     * already initialized.
     * 
     * @param array $data
     * @return string|null
     */
    public function render(array $data): ?string
    {
        // The body must be made first in order to collect the needed JS and CSS
        $body = $this->renderPageBody($data);

        //render page source first in case we need to append some polyfills to the head.
        $src = $this->pageCode->renderPageSourceCode();

        $head = $this->pageCode->renderPageHead();

        $lang = $this->getLanguage();

        return "<!DOCTYPE html>\n<html lang=\"$lang\">\n<head>" .
            implode("\n", $head) . "\n</head>\n" . $body . $src . "\n</html>";
    }

    /**
     * Localize the given data.
     *
     * @param array|string $data
     * @param string $lang
     * @return string
     */
    public function localize($data, string $lang = null): string
    {
        return $this->localizer->localize($data, $lang);
    }

    /**
     * This function returns the primary language that set in the language
     * setting file. The default primary language is english.
     *
     * @return string
     */
    public function getMainLanguage(): string
    {
        $content = $this->getLangSettings();

        return $content['mainLang'] ?? 'en';
    }

    /**
     * Get the language settings that set in the language setting file.
     *
     * @return array
     */
    public function getLangSettings(): array
    {
        return $this->loadClientSettings('languages') ?? [];
    }

    /**
     * Get the active page language.
     *
     * @return string|null
     */
    public function getLanguage(): ?string
    {
        return $this->activeLang;
    }

    /**
     * Get information about all languages of the website.
     *
     * @return array
     */
    public function getAvailableLanguages(): array
    {
        $settings = $this->getLangSettings();

        $langs = $settings['languages'];

        $result = [];

        foreach ($langs as $value) {
            $result[] = [
                'name' => $value,
                'label' => $this->getLangLabel($value)
            ];
        }

        return $result;
    }

    /**
     * Load the settings of the website renderer class.
     *
     * @param string $name
     * @return array|null
     */
    public function loadRendererSettings(string $name): ?array
    {
        if (isset($this->rendererSettings[$name])) {
            return $this->rendererSettings[$name];
        }

        $path = $this->getSettingsFolder() . $name . '.json';

        return $this->rendererSettings[$name] = $this->readJSONFile($path) ?? [];
    }

    /**
     * Load the settings of the client website project.
     *
     * @param string $name
     * @return array|null
     */
    public function loadClientSettings(string $name): ?array
    {
        if (isset($this->clientSettings[$name])) {
            return $this->clientSettings[$name];
        }
        $path = $this->getClientFolder() . 'settings/' . $name . '.json';

        return $this->clientSettings[$name] = $this->readJSONFile($path);
    }

    /**
     * Get the languages information.
     *
     * @return void
     */
    public function getLanguages()
    {
        return $this->loadRendererSettings('langs');
    }

    /**
     * Get the regions information.
     *
     * @return void
     */
    public function getRegionsInfo()
    {
        return $this->loadRendererSettings('regions');
    }

    /**
     * Get the font settings in the website folder.
     *
     * @return array
     */
    public function getFontsSettings(): array
    {
        /** @todo Consider a different name for this. */
        return $this->loadClientSettings('glot_fontSettings') ?? [];
    }

    /**
     * Get website breakpoints.
     *
     * @return array
     */
    public function getBreakpoints(): array
    {
        return $this->getPageCode()->getBreakpoints();
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
        return $this->pageCode->getPageLevelWidgets();
    }

    /**
     * Initialize the JavaScript class of the widget by adding all necessary
     * code in the "document ready" event of the webpage.
     *
     * @see PageCodeManager::initJavaScriptWidget() for details
     *
     * @param array $params Render params for the JS widget class.
     * @param array $target The target widget.
     * @return boolean Whether JS code was added to the page or not.
     */
    public function initJavaScriptWidget(array $params, array $target): bool
    {
        return $this->pageCode->initJavaScriptWidget($params, $target);
    }

    /**
     * Add custom JavaScript codes to the website head.
     *
     * @param string $code Custom JS code.
     * @return void
     */
    // public function addOnReadyCode(string $code): void
    // {
    //     $this->pageCode->addOnReadyCode($code);
    // }

    /**
     * Collect html codes which will be put into the head of the page.
     *
     * @param sting|array $html
     * @return void
     */
    public function addHeadHtml($html): void
    {
        $this->pageCode->addHeadHtml($html);
    }

    /**
     * Analyze the url and return the url type asset, link to internal pages or
     * link to external pages.
     *
     * @param string $href
     * @return string
     */
    public function getLinkType(string $href): string
    {
        if (!$href) {
            return '';
        }

        if (strpos($href, '//') !== false || $this->localizer->isMailTo($href)) {
            return 'External';
        }

        if ($this->getRenderingType() == self::STATIC_RENDERING) {
            $ext = pathinfo($href, PATHINFO_EXTENSION);

            return $ext == 'html' ? 'Inner' : 'Asset';
        }

        return (strpos($href, '.') !== false) ? 'Asset' : 'Inner';
    }

    /**
     * Determine if prerenderings are allowed.
     *
     * In order to avoid infinite loops and/or performance penalties,
     * only the main website rendered is allowed to requests pre-renders.
     *
     * @return boolean True iff prerenderings are allowed.
     */
    public function canPreRender(): bool
    {
        return ($this === self::$mainRenderer);
    }

    /**
     * Get pages and widgets structure information of the website.
     *
     * @return PageReader
     */
    public function getPageInfo(): Core\PageReader
    {
        return $this->getSystemPlugin('Core\\PageReader');
    }

    /**
     * Get a generator of font faces.
     *
     * @return FontManager
     */
    public function getFonts(): Core\FontManager
    {
        return $this->getSystemPlugin('Core\\FontManager');
    }

    /**
     * Get a manager of asset information (path, url, content and so on).
     *
     * @return AssetManager
     */
    public function getAssets(): Core\AssetManager
    {
        return $this->getSystemPlugin('Core\\AssetManager');
    }

    /**
     * Get a manager of Page data from master pages.
     *
     * @return MasterPageReader
     */
    public function getMasterPageReader(): Core\MasterPageReader
    {
        return $this->getSystemPlugin('Core\\MasterPageReader');
    }

    /**
     * Get a reader of data sources.
     *
     * @return DataSourceReader
     */
    public function getDataSourceReader(): Core\DataSourceReader
    {
        return $this->getSystemPlugin('Core\\DataSourceReader');
    }

    /**
     * Get a Localizer object to manage translations of widget parameters.
     *
     * @return Core\Localizer
     */
    public function getLocalizer(): Core\Localizer
    {
        return $this->getSystemPlugin('Core\\Localizer');
    }

    /**
     * Get a generator of page pre-renderings.
     *
     * @return PagePreRenderer An object to make pre-renderings.
     */
    public function getPreRenderer(): Core\PagePreRenderer
    {
        return $this->getSystemPlugin('Core\\PagePreRenderer');
    }

    /**
     * Get a content finder to enable search functionality for a website.
     *
     * @return ContentFinder An object to make pre-renderings.
     */
    public function getContentFinder(): Async\ContentFinder
    {
        return $this->getSystemPlugin('Async\\ContentFinder');
    }

    /**
     * Get a manager for web analytics to enable their collection on the
     * current page.
     *
     * @return AnalyticsManager An object to make pre-renderings.
     */
    public function getAnalyticsManager(): Utils\AnalyticsManager
    {
        return $this->getSystemPlugin('Core\\AnalyticsManager');
    }

    /**
     * Get the system plugins that provide information about the whole website
     * and relevant functionality for general-purpose components.
     *
     * @return stdClass An object with short-name properties for each selected
     * core system plugins.
     */
    public function site(): \stdClass
    {
        if ($this->site) {
            return $this->site;
        }

        // Add more core plugins as needed. Use simple property names.
        $aliases = [
            'assets' => 'Core\\AssetManager',
            'code' => 'Core\\PageCodeManager',
            'page' => 'Core\\PageReader',
            'finder' => 'Async\\ContentFinder',
            'analytics' => 'Utils\\AnalyticsManager'
        ];

        foreach ($aliases as &$value) {
            $value = $this->getSystemPlugin($value);
        }

        return $this->site = (object) $aliases;
    }

    /**
     * Get the prerendering of a page.
     * When widget renders the markup, it can get the markup of the whole page.
     *
     * @param string|null $pageName
     * @param string|null $lang
     * @param boolean $dom Whether to return a DOM or an HTML string.
     * @return DOMWalker|string A DOMWalker object or string of page content.
     */
    public function preRenderPage(
        ?string $pageName = null,
        ?string $lang = null,
        bool $dom = true
    ) {
        if (!$pageName) {
            $pageName = $this->getPageInfo()->getHomePage();
        }

        if (!$lang) {
            $lang = $this->getLanguage();
        }

        return $this->getPreRenderer()->getPrerendering($pageName, $lang, $dom);
    }

    /**
     * Undocumented function
     *
     * @return string|null
     */
    public function getRenderingType(): ?string
    {
        return $this->getOption(self::ENV, 'renderingType') ?? null;
    }

    /**
     * When rendering static website, we need the output directory for
     * creating some files.
     *
     * @return string|null
     */
    public function getRenderingDir(): ?string
    {
        return $this->getOption(self::ENV, 'renderingDir') ?? null;
    }

    /**
     * Check if it's exporting mode.
     * @deprecated Use getRenderingType() instead.
     *
     * @return boolean
     */
    public function isStaticRendering(): bool
    {
        return $this->getRenderingType() == self::STATIC_RENDERING;
    }

    /**
     * Convert GLOT elements into an HTML markup string.
     *
     * @param array|string $elements GLOT elements. It can be an HTML string,
     * a GLOT object (associative array) or a numeric array of strings or GLOT
     * objects.
     * @return string The rendered HTML markup.
     */
    public function renderMarkup($data): string
    {
        if (!is_array($data)) {
            // If it's not a string or empty, it's a strange case. Convert
            // to JSON as a fallback.
            return is_string($data) ? $data : ($data ?
                json_encode($data) : '');
        }

        if (!self::isAssocArray($data)) {
            $markup = [];

            foreach ($data as $elem) {
                $markup[] = $this->renderMarkup($elem);
            }

            return implode("\n", $markup);
        }

        if ($template = $data['params']['_template'] ?? false) {
            // The page is coming from one of the master page,
            // load the data in the master page.
            $testEle = $this->getMasterData($template, $data);
            $testEle['data-pageTemplate'] = $template;

            return $this->renderMarkup($testEle);
        }

        $class = $data['widget'] ?? false;

        if (!empty($data['sysParams']['invisible'])) {
            // Do not render the contents of the element
            $inner = null;
            // Remove unused attributes from the container element
            $this->extractWidgetAttributes($data);
        } elseif (!$class) {
            // There's no widget class, the element is HTML markup
            $inner = $data['data'] ?? $data['value'] ?? null;
            unset($data['data']);
        } else {
            unset($data['widget']);
            // Pass $data by reference to remove some properties
            $inner = $this->renderWidgetData($data, $class);
        }

        // The 'head' tag is a special case that sends the rendering to the
        // page head instead of the body.
        if (($data['tag'] ?? '') == 'head') {
            $this->addHeadHtml($this->renderMarkup($inner));
            return '';
        }

        // $data is the HTML element (wrapper) of the $inner markup content
        return $this->renderElement($inner, $data);
    }

    /**
     * Get system url.
     *
     * @todo What is this for? Why does it return a constant string?
     * System URL is the root url of the host.
     * Get from user's configs in the future.
     * It's used for generating the url for sending request
     *
     * @return string
     */
    public function getSystemUrl(): string
    {
        return $this->getOption(self::ENV, 'systemUrl') ?: '';
    }

    /**
     * Construct a plugin object and return it.
     *
     * @param string $class The class name of the plugin. It must be absolute
     * class name (the leading slash of the namespace is optional).
     * @param array $options Named arguments for the plugin's constructor.
     * @return mixed A plugin object or null of the plugin class was not found.
     */
    public function require(string $class, array $options = [])
    {
        // The load method standardizes the name of the class and loads
        // the class definition if it can be found. If not found, an autoload
        // function might end up finding it.
        if (!$this->loadPluginClass($class)) {
            // Try loading from the global custom plugin folder
            if ($class[0] != '\\') {
                $class = '\\' . $class;
            }

            $path = 'plugins/custom' . str_replace('\\', '/', $class);

            $this->loadPluginClass($class, $path);
        }

        return $this->newPlugin($class, $options);
    }

    /**
     * Create and copy file dependencies that are required by plugins in static
     * renderings. Do nothing if the rendering mode is not STATIC.
     *
     * @param string $tgtDir
     *   The target folder of the export (static rendering) operation.
     * @param array $req
     *   The request parameters to use when generating the dependencies.
     * @return void
     */
    public function exportStaticDependencies(string $tgtDir, array $req): void
    {
        if ($this->getRenderingType() != self::STATIC_RENDERING) {
            return;
        }

        $params = ['folder' => $tgtDir, 'request' => $req];

        $this->dispatch(new Event(Event::SITE_EXPORT, $params));
    }

    /**
     * Add a listener for loading non-standard pages.
     *
     * @param callable $cb A callback function.
     * @return void
     */
    public function addPageLoadListener(callable $cb): void
    {
        $this->addEventListener(Event::PAGE_LOAD, $cb);
    }

    /**
     * Add a listener for "export dependencies" events.
     *
     * @param callable $cb A callback function.
     * @return void
     */
    public function addSiteExportListener(callable $cb): void
    {
        $this->addEventListener(Event::SITE_EXPORT, $cb);
    }

    /**
     * Determine if the current website is of "virtual" type.
     *
     * Virtual websites have a different folder structure. A website
     * is considered virtual if its name starts with an underscore.
     *
     * @return boolean
     */
    public function isVirtualWebsite(): bool
    {
        return ($websiteId = $this->websiteName()) ?
            ($websiteId == '_') : false;
    }

    /**
     * Recover the page URL from the query parameters.
     *
     * @param array $params URL arguments (i.e. the query '?' part of the URL).
     * The first *key* found in $params will be taken as the $url (the first key
     * should map to the the empty string).

     * @return string The empty string is return if the array is empty.
     */
    public static function getUrlFromParams(array $params): ?string
    {
        return $params ? key($params) : '';
    }

    /**
     * Temp solution.
     *
     * @return self
     */
    public static function getMainRenderer(): self
    {
        return self::$mainRenderer;
    }

    /**
     * Undocumented function
     *
     * @param self|null $renderer, if it's null, it resets the mainRender
     * @return void
     */
    public static function setMainRenderer(?self $renderer): void
    {
        self::$mainRenderer = $renderer;
    }

    /**
     * Get the contents of a directory.
     *
     * @param string $path The path to a directory.
     * @param array $exclude An array with filenames to exclude.
     * @param boolean $skipDotFiles Excludes filenames that start with a dot.
     * @return array The list of items on the directory.
     */
    public function getDirContents($path, $exclude = false, $skipDotFiles = true)
    {
        $items = [];

        if (is_dir($path)) {
            if ($dh = opendir($path)) {
                while (($file = readdir($dh)) !== false) {
                    if ($skipDotFiles && $file && $file[0] === '.') {
                        continue;
                    }

                    $items[] = $file;
                }

                closedir($dh);
            }
        }

        return ($items && $exclude) ? array_diff($items, $exclude) : $items;
    }

    /**
     * Check if there is a default website name.
     *
     * @return boolean
     */
    public function hasDefaultWebsite(): bool
    {
        return $this->hasDefaultWebsite;
    }

    /**
     * Load a master's page data. If the page is extended from another master
     * page, load the page data from the master page.
     *
     * @return array
     */
    public function loadPageTemplateData(): array
    {
        $data = $this->currentPage['data'];

        $name = $data['params']['_template'] ?? false;

        return $name ? $this->getMasterData($name, $data) : $data;
    }

    /**
     * Return js file that page need.
     * @todo Is this necessary?
     * @return array
     */
    public function getPageJS(): array
    {
        return [];
    }

    /**
     * Get the value of an system/user option with name $key. All options
     * belong to some $category.
     *
     * The value search considers 3 levels of configuration: construction
     * options, client settings and renderer settings. The first one with
     * a value (in that order) is returned.
     *
     * Note: if an option must be taken from a specific level, then the specific
     * method for that level must be used. This methods considers all levels.
     *
     * @param string $category The category of the key-value pair.
     * @param string $key The key of the value.
     * @return mixed The value of the requested option, or the empty array if
     * the options is not set.
     */
    public function getOption(string $category, string $key)
    {
        // The key might map to a null value, so isset() can't be used
        $ref = &$this->options[$category];

        if (!$ref) {
            $ref = [];
        } elseif (array_key_exists($key, $ref)) {
            return $ref[$key];
        }

        // Save and return the value
        return $ref[$key] = $this->loadClientSettings($category)[$key] ??
            $this->loadRendererSettings($category)[$key] ?? null;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function loadBasePageHead(): array
    {
        return $this->loadClientSettings('_basePage')['head'] ?? [];
    }

    /**
     * Log system errors (with PSR-3 Logger Interface arguments).
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function log($message, array $context = []): void
    {
        $call = debug_backtrace()[0];

        // Send an error message to the defined error handling routines.
        // Make sure that php.ini sets error_log = '$path', where $path points
        // to a writable file. Otherwise newlines will appear as literals in a
        // default error_log file.
        error_log("\n$call[file]:$call[line]\n\n* " . print_r($message, true) . "\n\n" .
            ($context ? print_r($context, true) . "\n" : ''));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function trace(): void
    {
        $stack = [''];

        foreach (debug_backtrace() as $idx => $call) {
            $class = $call['class'] ?? '--';
            $line = $call['line'] ?? '--';

            if ($idx) {
                $stack[] = "$class:$call[function]:$line";
            } else {
                $msg = "$call[file]:$line\n";
            }
        }

        error_log($msg . implode("\n- ", $stack) . "\n");
    }

    /**
     * Check if website is in exporting mode
     *
     * @return bool
     */
    public function isModeExport(): bool
    {
        return false;
    }

    /**
     * Get a system plugin object. The object returned is always the same
     * because there is only one system plugin with a given name per renderer.
     *
     * System plugins are globally accessed by all website projects. That's in
     * contrast to plugins installed within each website folder.
     *
     * @param string $name A 'vendor\\plugin-name' for custom plugins or
     * 'Core\\plugin-name' for core system plugins.
     * @param array $options Construction options for the plugin class.
     * @return mixed The requested plugin object or null if the object could
     * not be constructed (e.g. its class definition was not found).
     */
    public function getSystemPlugin(string $name, array $options = [])
    {
        if (isset($this->systemPlugins[$name])) {
            return $this->systemPlugins[$name];
        }

        // Ensure a non-empty relative namespace
        if (!$name || $name[0] == '\\' || strpos($name, '\\') === false) {
            throw new Exception('Invalid system plugin name');
        }

        // The system plugin classes can be aliases with extended versions
        $alias = $this->getOption(self::ENV, 'systemPlugins')[$name] ?? false;

        // The first level of the name space is the type of system plugin
        $pos = strpos($name, '\\');
        $type = strtolower(substr($name, 0, $pos));
        $path = str_replace('\\', '/', substr($name, $pos + 1));

        // Assume the system's Plugin namespace for relative class names
        $class = '\\' . __NAMESPACE__ . "\\Plugin\\$name";

        // System plugins are located in a predefined root-level folder
        $path = 'plugins/' . $type . '/' .  $path;

        // The given class definition is always needed since aliases are
        // expected to extend their aliased base classes.
        $this->loadPluginClass($class, $path);

        $plugin = $alias ? $this->require($alias, $options) :
            $this->newPlugin($class, $options);

        // Store using the given name and not the class
        return $this->systemPlugins[$name] = $plugin;
    }

    /**
     * Undocumented function
     *
     * @param string $path
     * @return array|null
     */
    public function getGeneratedRequests(string $path): ?array
    {
        $data = $this->getPageInfo()->readPageData($path);

        if (!$data || empty($data['widget'])) {
            return null;
        }

        $pageWidget = $data['widget'];

        $realClass = str_replace('__', "\\", $pageWidget);

        // $this->pageCode->initWidgetClass($realClass);
        // $widget = $this->newWidget($realClass, []);

        if (!method_exists($realClass, 'generateRequests')) {
            return null;
        }

        $localParams = $this->localizer->getLocalizedSettings(
            $data['params'],
            null,
            $realClass,
            $data['dataSources'] ?? null
        );

        return $realClass::generateRequests($localParams);
    }

    /**
     * Add an ending slash to the given path.
     *
     * @param string $path
     * @return string
     */
    public static function addEndingSlash(string $path): string
    {
        if (!$path || $path[strlen($path) - 1] != '/') {
            $path .= '/';
        }

        return $path;
    }

    /**
     * Get a manager for code submitted by widget classes.
     *
     * @return PageCodeManager
     */
    public function getPageCode(): Core\PageCodeManager
    {
        return $this->getSystemPlugin('Core\\PageCodeManager');
    }

    /**
     * Undocumented function
     *
     * @param string $path
     * @return array
     */
    protected function loadPage(string $path): array
    {
        $reader = $this->getPageInfo();

        if (!$path) {
            $path = $reader->getHomePage();
        };

        if (($data = $reader->readPageData($path)) !== null) {
            return [$path, $data];
        }

        $name = $reader->getPageNameFromURL($path);

        if ($name != $path && ($data = $reader->readPageData($name)) !== null) {
            return [$name, $data];
        }

        // Give a chance to the registered listeners to load the page using
        // some different methods (e.g. a non-JSON file extension)
        if (($data = $this->loadCustomPage($path)) !== null) {
            return [$path, $data];
        }

        // Search for an ancestor page that can handle the request
        $path = $this->findTargetPage($path);

        if ($path && ($data = $reader->readPageData($path)) !== null) {
            return [$path, $data];
        }

        return [$path, null];
    }

    /**
     * Give a chance to PAGE_LOAD listeners to load a non-JSON page.
     *
     * @param string $path The path, without extension, to a page.
     * @return array|string|null
     */
    protected function loadCustomPage(string $path)
    {
        return $this->dispatch(new Event(Event::PAGE_LOAD, ['path' => $path]));
    }

    /**
     * Register a plugin callback for a given event. Plugins are expected to
     * call dedicated methods for registering each event type instead of this
     * generic method.
     *
     * @param string $eventName
     * @param callable $cb A callback function.
     * @return void
     */
    protected function addEventListener(string $eventName, callable $cb): void
    {
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }

        self::$listeners[$eventName][] = $cb;
    }

    /**
     * Dispatch the event to all listeners by executing their callbacks.
     *
     * @param array $params
     * @return mixed The response set by the listeners.
     */
    protected function dispatch(Event $event)
    {
        foreach (self::$listeners[$event->getName()] ?? [] as $cb) {
            $cb($event);

            if ($event->isPropagationStopped()) {
                break;
            }
        }

        return $event->response;
    }

    /**
     * Autoload method in charge of loading the Widget classes and calling
     * their static (class-level) functions to determine their CSS and JS needs.
     * It might fail to find a widget package, in which case so other registered
     * autoload might end up loading the widget class.
     *
     * @param string $class The name of the widget class. It is used to
     * determine the names of corresponding PHP and CSS files.
     */
    protected function autoload(string $class)
    {
        // Read and cache the widget's package (statically).
        // Load the widget class (statically).
        if ($wp = $this->loadWidgetPackage($class)) {
            require_once($wp->getWidgetFilename());
        }
    }

    /**
     * Determine the name of the website from the domain name in $_SERVER by
     * looking up the domain name in a map: [domain names => website names].
     */
    protected function findWebsiteName(): ?string
    {
        if (isset($this->options['websiteName'])) {
            return $this->options['websiteName'];
        }

        // Try mapping the active domain name to an internal website name
        if ($names = $this->loadRendererSettings('websiteNames')) {
            return $names[$_SERVER['SERVER_NAME']] ??
                $names[$_SERVER['HTTP_HOST']] ?? null;
        }

        return null;
    }

    /**
     * Load the json file $name from the $folder inside website folder
     *
     * @param string $folder The name of the target folder
     * @param string $name The name of the file
     * @return array|string
     */
    protected function loadDataFile(string $folder, string $name)
    {
        $filename = $this->getClientFolder() . "$folder/$name.json";

        return $this->readJSONFile($filename);
    }

    /**
     * Make the body of the page.
     *
     * @param array|string|null $data The page data.
     * @return string
     */
    protected function renderPageBody($data): string
    {
        return $this->renderMarkup($data);
    }

    /**
     * Get the directory of settings folder for the renderer.
     *
     * @return string
     */
    protected function getSettingsFolder(): string
    {
        return __DIR__ . '/../settings/';
    }

    /**
     * This function make the head of the page.
     * It includes different type of styles, font faces and href langs.
     *
     * @return string
     */
    protected function renderPageHead(): string
    {
        $head = $this->pageCode->renderPageHead();

        return "\n<head>\n" . implode("\n", $head) . "\n</head>\n";
    }

    /**
     * Make scripts tags. It includes widget library, polyfills and custom js
     * code that declared in widget package.
     *
     * @return string
     */
    protected function renderPageSourceCode(): string
    {
        return $this->pageCode->renderPageSourceCode();
    }

    /**
     * Check if website is in developing mode.
     *
     * @return boolean
     */
    protected function isEditMode(): bool
    {
        return false;
    }

    /**
     * Undocumented function
     *
     * @param array $data A reference to the array from which properties
     * will by removed.
     * @return array The removed properties and their values.
     */
    protected function extractWidgetAttributes(array &$data): array
    {
        /** @todo Should we remove 'widgetName' and put it in $attrs? */
        $keys = [
            'data', 'params', 'sysParams', 'css', 'dataSources',
            'widgetName', 'widgetStyles', 'customCode'
        ];

        $attrs = [];

        foreach ($keys as $key) {
            $attrs[$key] = $data[$key] ?? null;
            unset($data[$key]);
        }

        return $attrs;
    }

    /**
     * Undocumented function
     *
     * @param string $id
     * @param array $elem
     * @param string $class
     * @return array
     */
    protected function getWidgetOptions(string $id, array $attrs, string $class): array
    {
        $label = ($name = $attrs['widgetName'] ?? false) ?
            substr($name, 0, 25) : '';

        // Merge the sysParams with additional system options
        return ($attrs['sysParams'] ?? []) + [
            'firstImpressionRatio' => 0.5,
            'tracking' => [
                'event_category' => $class,
                'event_label' => $id . ';' . $label
            ]
        ];
    }

    /**
     * Render the markup for associative array
     *
     * @param array $elem A reference to an associative array with GLOT object
     * markup. Some properties of the given element are removed for keeping html
     * tags clean
     * @return array|string|null
     */
    protected function renderWidgetData(array &$elem, string $widgetClass)
    {
        $id = $elem['id'] ?? false;

        $attrs = $this->extractWidgetAttributes($elem);

        $realClass = self::normalizeClass($widgetClass);

        $options = $this->getWidgetOptions($id, $attrs, $realClass);

        $options['holderId'] = $id;

        // Start buffering the standard output coming from the widget.
        // e.g. Any echo or print from a render() method will be logged.
        ob_start();

        $widget = $this->newWidget($realClass, $options);

        $this->pageCode->initWidgetClass($realClass);

        $localParams = $this->localizer->getLocalizedSettings(
            $attrs['params'] ?? [],
            null,
            $realClass,
            $attrs['dataSources']
        );

        $markup = $widget->render($attrs['data'], $localParams);

        $classes = $this->pageCode->getWidgetCssClasses(
            $id,
            $widgetClass,
            $attrs
        );

        if (!empty($elem['class'])) {
            $classes[] = $elem['class'];
        }

        // Add the widget class name to the CSS class names
        // $elem['class'] = !empty($elem['class']) ?
        //     $widgetClass . ' ' . $elem['class'] : $widgetClass;

        // // See if there are explicit wrapper attributes
        // $attr = $widget->getHTMLAttributes($attrs['data'], $localParams);

        // if ($attr) {
        //     // The properties of $elem have priority over those of $attr
        //     $elem = array_merge($attr, $elem);

        //     // Merge the classes in $attr instead (no swap it with one in $elem)
        //     if (isset($attr['class'])) {
        //         $classes[] = $attr['class'];
        //     }
        // } else

        if (is_array($markup) && isset($markup['tag']) && !isset($markup['widget'])) {
            // Separate the $markup into inner data and wrapper. Then make the
            // inner data the $markup and merge the wrapper with $elem.
            $innerData = null;

            // Transfer the parameters of the upper-most element in the markup
            // to the $elem array that we created above.
            foreach ($markup as $attrKey => &$attrValue) {
                switch ($attrKey) {
                    case 'data':
                        $innerData = &$attrValue;
                        break;
                    case 'class':
                        $classes[] = $attrValue;
                        break;
                    default:
                        // Only copy the params that don't exist in the element
                        if (!isset($elem[$attrKey])) {
                            $elem[$attrKey] = $attrValue;
                        }
                        break;
                }
            }

            $markup = $innerData;
        }

        // Save all collected CSS classes back to the element
        $elem['class'] = implode(' ', $classes);

        // If there is not explicit HTML role, get one from the widget settings
        if (empty($elem['role'])) {
            $settingContent = $this->getWidgetSettings($realClass);

            if (!empty($settingContent['role'])) {
                $elem['role'] = $settingContent['role'];
            }
        }

        $output = ob_get_contents();

        // Finish buffering and clean the buffer
        ob_end_clean();

        // Log any error found when rendering the widget
        if ($output !== '') {
            $context = [
                'widgetId' => $id,
                'widgetClass' => $widgetClass
            ];

            $this->logWidgetError($output, $context);
        }

        return $markup;
    }

    /**
     * Log widget errors (with PSR-3 Logger Interface arguments).
     *
     * @param mixed $message
     * @param array $context Includes 'widgetId' and 'widgetClass'.
     * @return void
     */
    protected function logWidgetError($message, array $context = []): void
    {
        // Add additional info to the context
        $context['website'] = $this->websiteName();
        $context['page'] = $this->pageName();

        $class = $this->getOption(self::ENV, 'logger');
        $logger = $class ? $this->require($class) : false;

        if ($logger) {
            // Assume that PSR-3 logger was given (e.g. Monolog)
            $logger->error($message, $context);
        } else {
            $this->log($message, $context);
        }
    }

    protected function getProjectFolder(): string
    {
        return $this->options[self::PATHS]['root'];
    }

    /**
     * Get the absolute path to the plugin folder. It defaults to the 'plugins'
     * folder within the client website.
     *
     * @return string
     */
    protected function getPluginFolder(): string
    {
        if ($dir = $this->options[self::PATHS]['plugins'] ?? false) {
            return $dir;
        }

        // Possible roots order by precedence
        $roots = [
            $this->getClientFolder(),
            $this->getProjectFolder(),
            __DIR__ . '../'
        ];

        foreach ($roots as $dir) {
            $dir .= 'plugins';
            if (is_dir($dir)) {
                break;
            }
        }

        return $this->options[self::PATHS]['plugins'] = $dir . '/';
    }

    /**
     * Load a plugin class into the PHP class namespace if an appropriate PHP
     * file is found. Otherwise it is assumed that some autoload function
     * might be able to locate the class via other methods.
     *
     * @param string $class The qualified plugin class name. A leading \ is
     * added if not given.
     * Otherwise, it is assumed to be relative to the default plugin namespace.
     * @param string $path An optional relative path from the project's root
     * directory for plugins that are not under the default plugin folder.
     * @return bool true if the class exists or a PHP file with the class name
     * was loaded, and false if the target file was not found.
     */
    protected function loadPluginClass(string $class, string $path = ''): bool
    {
        if (!$class) {
            throw new Exception('Empty plugin class');
        }

        if ($class[0] != '\\') {
            $class = '\\' . $class;
        }

        if (class_exists($class, false)) {
            return true;
        }

        // The short name starts after the last slash (there are 1+ slashes)
        $shortName = substr($class, strrpos($class, '\\') + 1);

        if (!$shortName) {
            throw new Exception('Invalid class name (has trailing slash?)');
        }

        $path = $path ? __DIR__ . "/../$path" : $this->getPluginFolder() .
            str_replace('\\', '/', substr($class, 1));

        $filename = $path . "/$shortName.php";

        if (!file_exists($filename)) {
            return false;
        }

        require_once($filename);

        return true;
    }

    /**
     * The given page in the url is not a valid path,
     * this function try to find another page in the parent
     * folder which can handle the request
     *
     * @param string $path The given path to a missing target page.
     * @return string|null The page name or null if not found.
     */
    protected function findTargetPage(string $pageName): ?string
    {
        $path = $pageName;
        $topDir = dirname($this->getWebsiteFolder());

        while ($path = dirname($path) && $path != $topDir) {
            foreach ($this->getDirContents($path) as $basename) {
                $subPath = $path . '/' . $basename;

                // See if the current file is a page that can handle the request
                if (
                    is_file($subPath) &&
                    ($requests = $this->getGeneratedRequests($subPath)) &&
                    $this->canRenderUrl($pageName, $requests)
                ) {
                    return $path . '/' . pathinfo($basename, PATHINFO_FILENAME);
                }
            }
        }

        return null;
    }

    /**
     * This function return label of language code.
     *
     * @param string $name language code.
     * @return string
     */
    private function getLangLabel(string $name): string
    {
        $parts = explode('-', $name);

        $lang = $parts[0];

        $region = count($parts) > 1 ? $parts[1] : '';

        $langSettings = $this->getLanguages();
        $label = $langSettings[$lang]['label'] ?? $lang;

        if ($region) {
            $regionSettings = $this->getRegionsInfo();

            $regionLabel = empty($regionSettings[$region]) ?
                $regionSettings[$region] : $region;

            $label = $label . " ($regionLabel)";
        }

        return $label;
    }

    /**
     * This function returns a valid language based on a given language. It
     * overrides the same function on the base class SlimController. In the base
     * class, this function uses the valid languages defined in the startup.ini.
     * But in this version, we check the languages of the website.
     * @param string $lang
     * @return string
     */
    private function makeValidLang(string $lang): string
    {
        $settings = $this->getLangSettings();

        if (!$settings) {
            return $lang;
        } // @todo This might be an invalid language

        $langs = $settings['languages'];

        // The first attempt matched both language-region against
        // language-region. The next attempts focus on the language part.
        if (in_array($lang, $langs)) {
            return $lang;
        }

        // If the given languages is of the form language-region, then
        // try finding a valid language equal to the "language" part.
        $preLang = explode('-', $lang)[0];

        if ($preLang != $lang && in_array($preLang, $langs)) {
            return $preLang;
        }

        // It might be that we have a valid language of the form
        // language-region whose language part matches the language
        // part of $lang. So, we remove the "region" from all valid langs.
        foreach ($langs as $value) {
            if (explode('-', $value)[0] == $preLang) {
                // the first valid language that matches
                return $value;
            }
        }

        return $settings['mainLang'] ?? 'en';
    }

    /**
     * Return the right data if the elements are coming from one of master pages.
     *
     * @param string $name The name of the master page.
     * @param array $elements
     * @return array
     */
    private function getMasterData(string $name, array $elements): array
    {
        return $this->getMasterPageReader()->getMasterData($name, $elements);
    }

    /**
     * Renders the element with given HTML $attributes that wraps the given
     * $markup content.
     *
     * @param array|string|null $markup Contents of the elements to render.
     * @param array $attributes Attributes of the element to render.
     * @return string Rendered HTML element.
     */
    private function renderElement($markup, array $attributes = []): string
    {
        if ($markup === null && !$attributes) {
            return '';
        }

        if (isset($attributes['tag'])) {
            $tag = $attributes['tag'] ?: 'div';
            unset($attributes['tag']);
        } else {
            $tag = 'div';
        }

        $extras = '';

        // The data may have come from a 'value' property which should not
        // be wrapped as an attribute unless the element tag is a special one.
        // https://developer.mozilla.org/tr/docs/Web/HTML/Attributes
        if (isset($attributes['value']) && !in_array($tag, [
            'button', 'data', 'input', 'li', 'meter', 'option', 'progress', 'param'
        ])) {
            unset($attributes['value']);
        }

        /**
         * @todo process extras in a different methods so we can deal
         * with self::WIDGET_ATTRS in it???
         */
        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                $extras .= ' ' . $key;
            } elseif ($key == self::WIDGET_ATTRS) {
                // Convert all single quotes ' into \u0027
                $str = json_encode($value, JSON_HEX_APOS);
                $extras .= ' ' . self::WIDGET_ATTRS . "='$str'";
            } else {
                if (is_array($value)) {
                    $value = $this->localizer->localize($value);
                }

                $extras .= " $key=\"$value\"";
            }
        }

        // If the markup is given as an array, make a markup string.
        // Note: An empty array is a DIV because of the default tag.
        if (is_array($markup)) {
            $markup = $this->renderMarkup($markup);
        }

        // Some tags are self closing. e.g. <tag ... />
        if (!$markup && in_array($tag, [
            'img', 'input', 'hr', 'br', 'col', 'embed', 'meta', 'param',
            'area', 'base', 'link', 'source', 'track', 'wbr'
        ])) {
            /**
             * The self-closing-tag slash is optional in HTML5. The meaning of a
             * closing slash depends on whether its a void or foreign element
             * @see
             * https://html.spec.whatwg.org/multipage/syntax.html#void-elements
             * https://html.spec.whatwg.org/multipage/syntax.html#foreign-elements
             */
            return "<$tag$extras>"; // "<$tag$extras />" for HTML 4 void elem
        }

        return "<$tag$extras>$markup</$tag>";
    }

    /**
     * Create a new Widget object.
     *
     * @param string $class The qualified widget class.
     * @param array $options Options for the widget constructor.
     * @return Widget A new widget object.
     * @throws Exception
     */
    private function newWidget(string $class, array $options): Widget
    {
        return new $class($this, $options);
    }

    /**
     * Create a new Plugin object.
     *
     * @param string $class The qualified plugin class.
     * @param array $options Options for the plugin constructor.
     * @return Plugin|null A new plugin object. Null if the class doesn't exist.
     */
    private function newPlugin(string $class, array $options): ?Plugin
    {
        // Check that the class exist (it auto-loads it if not)
        return class_exists($class) ? new $class($this, $options) : null;
    }

    /**
     * Set all needed system paths under the $options property.
     *
     * @return void
     */
    private function initSystemPaths(): void
    {
        // Make an alias for the paths
        $paths = &$this->options[self::PATHS];

        // Get the www path so it can be use as a reference for the others
        if (empty($paths['www'])) {
            // The script name is assumed to be the path to the "router" file
            if ($filename = $_SERVER['SCRIPT_FILENAME'] ?? false) {
                // Assuming that the parent dir is '.../www' or '.../www/api'
                $www = dirname($filename);
                // Move one level up if it's the API sub-folder
                if (basename($www) == 'api') {
                    $www = dirname($www);
                }
            } else {
                // Fallback to the 'www' of the Renderer
                $www = dirname(__DIR__) . '/www';
            }
        } else {
            $www = realpath($paths['www']);
        }

        // If the project root folder is not given, it is assumed to be
        // one level up from the www folder.
        $root = empty($paths['root']) ? dirname($www) :
            realpath($paths['root']);

        // All system paths are expected to end with a slash
        $www .= '/';
        $root .= '/';

        // If 'plugins' is not given, it is set at render time because it
        // depends on the specific website being rendered.
        $plugins = empty($paths['plugins']) ? null :
            realpath($paths['plugins']) . '/';

        // An explicitly empty 'sites' means that the root is a single site.
        // A missing 'sites' is ambiguous and can be inferred with a heuristic.
        if (!isset($paths['sites'])) {
            // Not given: infer it based on whether there is a 'sites' at root
            $sites = is_dir($root . 'sites') ? $root . 'sites/' : null;
        } elseif ($paths['sites']) {
            $sites = realpath($paths['sites']) . '/';
        } else {
            $sites = null;
        }

        //Include autoload of the current project.
        //Widgets might be installed from composer.
        if (file_exists($root . 'vendor/autoload.php')) {
            require_once $root . 'vendor/autoload.php';
        }

        // Save the normalized paths back to the referenced options array
        $paths['www'] = $www;
        $paths['root'] = $root;
        $paths['sites'] = $sites;
        $paths['plugins'] = $plugins;
    }

    /**
     * Check if there is at least one string key. If so, $a is associative.
     *
     * @param array $a The array to evaluate.
     * @return boolean True if and only if $array has at least one string key.
     */
    private static function isAssocArray(array $a): bool
    {
        // Move the array's internal pointer to the first non INT key.
        for (reset($a); is_int(key($a)); next($a));

        // If it's not the array's end, then it's not associative.
        return !is_null(key($a));
    }

    /**
     * This function check if the current page belongs to
     * the given request urls
     *
     * @param string $pageName
     * @param array $requests URLs that the page widget can handle
     * @return boolean
     */
    private static function canRenderUrl(string $pageName, array $requests)
    {
        if (in_array($pageName, $requests)) {
            return true;
        }

        foreach ($requests as $str) {
            // There are no wildcards
            if (strpos($str, '*') === false) {
                continue;
            }

            // Convert wildcards to regular expression
            $str = trim($str);

            $search = ['/', '**', '*', '~'];
            $replace = ["\/", "~", "[^\/]*", '.*'];

            $reg = '/^' . str_replace($search, $replace, $str) . '$/';

            if (preg_match($reg, $pageName, $matches)) {
                return true;
            }
        }

        return false;
    }
}
