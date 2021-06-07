<?php

/**
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 */

namespace Proximify\Glot;

require_once __DIR__ . '/Widget.php';
require_once __DIR__ . '/Plugin.php';

/**
 * Reader of GLOT component folder packages such as widgets and plugins.
 */
class ComponentPackage
{
    public const ROOT_WIDGET_CLASS = __NAMESPACE__ . '\\Widget';
    public const ASSETS = 'assets';
    public const CUSTOM_LIBS = 'customLibs';
    public const EXTERNAL_LIBS = 'externalLibs';
    public const STORE_IMAGE = 'storeImage';
    public const WIDGET_ICON = 'widgetIcon';
    public const STYLES = 'styles';
    public const CSS = 'css';
    public const JS = 'js';
    public const POLYFILL = 'polyfills';

    protected const PACKAGE_FILENAME = 'settings.json';
    protected const WIDGET_SETTINGS = 'widgetSettings';
    protected const WIDGET_PARAMETERS = 'widgetParams';
    protected const WIDGET_CONSTRAINTS = 'widgetConstraints';

    /** Current version of the package data schema. */
    private const REQUIRED_SCHEMA_VERSION = '3.0.1';
    private const SCHEMA_PATH = __DIR__ . '/../settings/widget_schema.json';

    /** @var string The path to the root folder of the widget package. */
    private $rootFolder;

    /** @var string|null The qualified widget PHP class name. */
    private $className;

    /** @var string The unqualified widget class name (without namespace). */
    private $shortName;

    /** @var array The root settings of the component package. */
    private $packageSettings;

    /** @var array A cache for package data from different loaded files. */
    private $packageData = [];

    /** @var array A cache for package data the from ".config.json" file. */
    private $configData;

    /** @var array A cache for the component schema loaded from SCHEMA_PATH. */
    private $schema;

    /** @var array Parameters for widgets whose ancestors define their own. */
    private $widgetExtendedParams;

    /** @var array Loaded JSON files*/
    private static $loadedFileData = [];

    /**
     * Construct an object that represents a component package.
     *
     * @param string $rootFolder The path to the widget package.
     * @param string|null $class The PHP widget class name.
     */
    public function __construct(string $rootFolder, ?string $class = null)
    {
        $this->setRootFolder($rootFolder);

        /**
         * @todo Consider getting the class name from the package. If $class
         * is given make sure that it's equal to the package one. If not, save
         * the package one.
         */
        $this->className = $class;
    }

    /**
     * Get the full, qualified class name of the widget (i.e. with namespace).
     *
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * Gets widget's version.
     *
     * @return string
     */
    public function getWidgetVersion(): string
    {
        return $this->getWidgetSettings()['version'] ?? '1.0.0';
    }

    /**
     * @todo What is this for?
     *
     * @return array
     */
    public function getPredefinedDefaultStyle(): array
    {
        $filename = $this->getPredefinedDefaultStylePath();

        return $this->readJsonFile($filename);
    }

    /**
     * @todo What classes? Is it CSS?
     *
     * @param string $style
     * @return array
     */
    public function getWidgetClasses(string $style = ''): array
    {
        $filename = $this->getWidgetClassPath();

        $classes = $this->readJsonFile($filename);

        return $style ? ($classes[$style] ?? []) : $classes;
    }

    /**
     * Gets the path of the PHP file in the widget package.
     *
     * @return string
     */
    public function getWidgetCorePath(): string
    {
        $path = $this->getSchemaRules()['widgetMaker']['folder'] ?? false;

        if (!$path) {
            // throw new \Exception('Empty widgetMaker folder path');
            return '';
        }

        if ($path[strlen($path) - 1] != '/') {
            $path .= '/';
        }

        return $path;
    }

    /**
     * Get path from the widget's class definition file in the package.
     *
     * @return string The file path to an existing class definition
     * file for the widget.
     *
     * @throws Exception If the file path does not exist.
     * @return string
     */
    public function getWidgetFilename(): string
    {
        $path = $this->rootFolder . $this->getWidgetCorePath() .
            $this->shortName . '.php';

        if (!file_exists($path)) {
            throw new \Exception("Missing widget file '$path'");
        }

        return $path;
    }

    /**
     * Gets parameter schema defined by the widget creator.
     *
     * @return array|null
     */
    public function getParameters(): ?array
    {
        return $this->loadPackageData(self::WIDGET_PARAMETERS);
    }

    /**
     * Gets constraints defined by the widget creator.
     *
     * @return array|null
     */
    public function getConstraints(): ?array
    {
        return $this->loadPackageData(self::WIDGET_CONSTRAINTS);
    }

    /**
     * Undocumented function
     *
     * @return array|null
     */
    public function getDefaultStyle(): ?array
    {
        return $this->readJsonFile($this->getDefaultStylePath());
    }

    /**
     * Gets the folder of assets in the widget package.
     * @return string
     */
    public function getWidgetAssetsFolder(): string
    {
        $schemaRules = $this->getSchemaRules();
        $assetsFolder = $schemaRules[self::ASSETS]['base_folder'];

        return $assetsFolder;
    }

    /**
     * Gets schema rules by schema version.
     * @return array
     */
    public function getSchemaRules(): array
    {
        $schemas = $this->loadWidgetSchema();
        $v = $this->getSchemaVersionNum();

        if (!isset($schemas[$v])) {
            throw new \Exception('Wrong package schema version number.');
        }

        return $schemas[$v];
    }

    /**
     * Gets the root folder of the component package.
     * @return string
     */
    public function getRootFolder(): string
    {
        return $this->rootFolder;
    }

    /**
     * Get the unqualified widget class name (without namespace).
     *
     * @return string
     */
    public function getShortClassName(): string
    {
        return $this->shortName;
    }

    /**
     * Evaluate the class name against a list of qualified class names that are
     * considered to be root-level classes for widgets. The recursive processing
     * of widget class ancestors can end at any of the given root-level classes.
     *
     * @return array
     */
    // public static function isRootClass(?string $class): bool
    // {
    //     return !$class || in_array($class, [
    //         __NAMESPACE__ . '\\Component', 'Component'
    //     ]);
    // }

    /**
     *	Load schema rules into a memory cache.

     *	This file set rules how to reach contents in the widget package;
     * @return array
     */
    public function loadWidgetSchema(): array
    {
        return $this->schema ?? $this->schema = $this->readJsonFile(self::SCHEMA_PATH);
    }

    /**
     * Undocumented function
     *
     * @param string $
     * @param bool $childNeeds. If child widget needs JS file, we need to always include parent js file.
     * @return array
     */
    public function getLibraries(string $type, bool $childNeeds = false): array
    {
        switch ($type) {
            case self::CUSTOM_LIBS:
                $deps = [
                    self::JS => [$this->processMainJS(
                        $this->shortName . '.js',
                        $childNeeds
                    )]
                ];
                break;
            case self::EXTERNAL_LIBS:
                $deps = $this->loadPackageData($type);
                break;
            default:
                throw new \Exception("Invalid dependency type '$type'");
        }

        return $deps + [self::CSS => [], self::JS => [], self::POLYFILL => []];
    }

    public function getMainCSSFilePath(): string
    {
        return $this->getPackageFolderName(self::CSS) . '/' .
            $this->getShortClassName() . '.css';
    }

    public function getMainJSFilePath(): string
    {
        return $this->getPackageFolderName(self::JS) . '/' .
            $this->getShortClassName() . '.js';
    }

    public function getPolyfills(): array
    {
        return $this->loadPackageData(self::POLYFILL);
    }

    public function getFromPolyfillIO(): array
    {
        return $this->loadPackageData('polyfillIO') ?: [];
    }

    // /**
    //  * Gets libFiles content.
    //  *
    //  * @return array
    //  */
    // public function getCustomLibFiles(): array
    // {
    //     return $this->loadPackageData(self::CUSTOM_LIBS);
    // }

    // /**
    //  * Gets generic libs content.
    //  *
    //  * @return array
    //  */
    // public function getGenericLibs(): array
    // {
    //     return $this->loadPackageData(self::EXTERNAL_LIBS);
    // }

    /**
     * Gets widget settings content.
     *
     * @return array
     */
    public function getWidgetSettings(): array
    {
        return $this->loadPackageData(self::WIDGET_SETTINGS);
    }

    /**
     * Undocumented function
     *
     * @param string $filename
     * @return array|null
     */
    public function readJsonFile(string $filename): ?array
    {
        if (!file_exists($filename)) {
            return [];
        }

        /**
         * @todo In "shared" mode, we have to use locks.
         * Overload in extended class to do that.
         */

        if (empty(self::$loadedFileData[$filename])) {
            self::$loadedFileData[$filename] = json_decode(
                file_get_contents($filename),
                true
            );
        }

        return self::$loadedFileData[$filename];
    }

    /**
     * Get the file path declared in widget schemas
     * Needed in WidgetValidator class
     *
     * @param string $fileKey
     * @return string
     */
    public function getPackageFileName(string $fileKey): string
    {
        $schemaRules = $this->getSchemaRules();

        $path = $schemaRules[$fileKey]['filePath'];

        return $this->getRootFolder() . $path;
    }

    /**
     * Get the folder path declared in widget schemas
     * Needed in WidgetValidator class
     *
     * @param string $fileKey
     * @return string
     */
    public function getPackageFolderName(string $folderKey): string
    {
        $schemaRules = $this->getSchemaRules();

        $path = $schemaRules[$folderKey]['folder'];

        return $this->getRootFolder() . $path;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getDefaultStylePath(): string
    {
        return $this->getRootFolder() . 'preserved/default.json';
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getPredefinedDefaultStylePath(): string
    {
        $schemaRules = $this->getSchemaRules();
        $styleFolder = $schemaRules[self::STYLES]['folder'];

        $folder = $this->getRootFolder() . $styleFolder;

        return $folder . '/default.json';
    }

    /**
     * Get the path of the markup dictionary
     *
     * @return string
     */
    public function getMarkupDict(): string
    {
        $schemaRules = $this->getSchemaRules();
        $file = $schemaRules['markup']['filePath'];

        return $file;
    }

    /**
     * Get client class info (JavaScript).
     *
     * @return array
     */
    public function getClientClassInfo(): array
    {
        // if ($this->configHandle) {
        //     $data = $this->configHandle->read();
        // } else {
        //     $filename = $this->rootFolder . '.config.json';
        //     $data = $this->readJsonFile($filename);
        // }
        // self::log($this->loadPackageSettings());

        return $this->loadPackageSettings()['jsClass'] ?? [];
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    // public function getClientBaseClassVersion(): string
    // {
    //     $info = $this->getClientClassInfo();

    //     return $info['client_class_version'] ?? '';
    // }

    /**
     * Retrieves the parsed information about a method of the widget's
     * JavaScript class.
     *
     * @param string $name The name of the method to look up.
     * @return array The known information about the methods.
     */
    public function getClientMethodInfo($name): array
    {
        $info = $this->getClientClassInfo();

        return $info['methods'][$name] ?? [];
    }

    /**
     * @todo what is this for?
     *
     * @param string $style
     * @return string
     */
    public function getCustomClasses(string $style = ''): string
    {
        $filename = $this->getCustomClassPath();

        $classes = $this->readJsonFile($filename);

        return $style ? ($classes[$style] ?? []) : $classes;
    }

    /**
     * @todo what is this for?
     *
     * @return string
     */
    public function getCustomClassPath(): string
    {
        return $this->getRootFolder() . 'preserved/custom_classes.json';
    }

    /**
     * @todo what is this for?
     *
     * @return string
     */
    public function getWidgetClassPath(): string
    {
        $schemaRules = $this->getSchemaRules();
        $styleFolder = $schemaRules[self::STYLES]['folder'];

        return $this->getRootFolder() . $styleFolder . '/widget_classes.json';
    }

    static public function normalizeClientClass(string $class): string
    {
        return str_replace(['\\', '/'], '__', $class);
    }

    /**
     * Generate the JS class name from the PHP class name.
     *
     * @return string
     */
    public function getJavaScriptClass(): string
    {
        /**
         * @todo If $this->className is null, the name was
         * not given at construction time. In general, it would
         * make sense to get it from the package itself.
         */
        return self::normalizeClientClass($this->className);
    }

    /**
     * Called by the system once for every widget class in order to
     * generate the JavaScript code for the "document ready" function
     * of the webpage. Note that it is called once per widget class,
     * rather than once per widget instance like the render() function.
     *
     * @return string
     */
    public function getOnReadyCode(): string
    {
        // When overriding the function to add extra code, make sure
        // to call parent::getOnReadyCode() if you want to preserve
        // the default behaviour of this function.
        $methodName = 'initClass';
        $info = $this->getClientMethodInfo($methodName);

        if ($info && $info['isStatic'] && !$info['isEmpty']) {
            return $this->getJavaScriptClass() . ".$methodName();";
        }

        return '';
    }

    /**
     * Gets value from associate array by keys.
     *
     * @return mixed
     */
    public static function getAssociateValueByKeys($array, $stringKeys)
    {
        if (!$array) {
            return [];
        }

        $keys = explode('/', $stringKeys);

        $arr = &$array;

        foreach ($keys as $key) {
            if (!isset($arr[$key])) {
                return [];
            }

            $arr = &$arr[$key];
        }

        return $arr;
    }

    /**
     * Get the parameters of the widget or, if empty, the extended parameters
     * of its parent. It works recursively to find the closest non-empty
     * parameters in the ancestor path.
     * 
     * Merge parameters even if the current widget's parameter is not empty.
     *
     * @param Renderer $renderer The renderer object to call when
     * loading component packages recursively. It allows the renderer to
     * cache the loading of packages.
     */
    public function getExtendedParams(Renderer $renderer): array
    {
        if ($this->widgetExtendedParams === null) {
            $parent = get_parent_class($this->className);

            if (!($this->widgetExtendedParams = $this->getParameters())) {

                // Consider ROOT_WIDGET_CLASS ???
                $this->widgetExtendedParams = !$parent ? [] :
                    $renderer->getExtendedWidgetParams($parent);
            }
            else {
                $parentParams = !$parent ? [] :
                    $renderer->getExtendedWidgetParams($parent);

                if ($parentParams) {
                    $this->solveParamsConflicts($this->widgetExtendedParams,
                     $parentParams);

                     $this->widgetExtendedParams = array_merge(
                        $this->widgetExtendedParams,
                        $parentParams
                    );
                }
            }
        }

        return $this->widgetExtendedParams;
    }

    function solveParamsConflicts($params, &$parentParams)
    {
        foreach ($params as $value) {
            $type = $value['type'];
            $name = $value['name'];

            $this->removeConflictParam($parentParams, $name);

            if ($type == 'submenu')
                $this->solveParamsConflicts($value['data'], $parentParams);
        }
    }

    function removeConflictParam(&$params, $name)
    {
        $result = '';

        if (is_array($params)) {
            if (isset($params['name']) && $params['name'] == $name) {
                $result = true;
            } else {
                foreach ($params as $key => &$value) {
                    $result = $this->removeConflictParam($value, $name);

                    if ($result && isset($value['name']) && $value['name'] == $name) {
                        array_splice($params, $key, 1);
                        break;
                    }
                }
            }
        }

        return $result;
    }

    // function getContentByTarget($content, $targetName, $targetValue)
    // {
    //     $result = '';

    //     if (is_array($content)) {
    //         if (
    //             isset($content[$targetName]) &&
    //             $content[$targetName] == $targetValue
    //         ) {
    //             $result = $content;
    //         } else {
    //             foreach ($content as $value) {
    //                 $result = $this->getContentByTarget($value, 
    //                     $targetName, $targetValue);

    //                 if ($result)
    //                     break;
    //             }
    //         }
    //     }

    //     return $result;
    // }

    /**
     * Sets the root folder of the component package.
     *
     * @return void
     */
    protected function setRootFolder(string $path): void
    {
        if (!$path) {
            throw new \Exception('Empty path to component package');
        } else if (!is_dir($path)) {
            throw new \Exception("Missing package at '$path'");
        }

        // Normalize the path to have a trailing slash
        if ($path[strlen($path) - 1] != '/') {
            $path .= '/';
        }

        $this->rootFolder = $path;

        // The last directory is the short name of the widget class
        if (($pos = strrpos($path, '/', -2)) !== false) {
            $pos++;
        }

        $this->shortName = substr($path, $pos, -1);
    }

    /**
     * The package data is cached in memory. After changing the file, the cache must
     * be cleared so the new data is read again by the next load operation.
     *
     * @return void
     */
    protected function clearMemoryCache(): void
    {
        $this->packageData = null;
        $this->configData = null;
        $this->schema = null;
    }

    protected function fileExists(string $type, string $filename): bool
    {
        $filename = "$type/$filename.$type";

        return file_exists($this->getRootFolder() . $filename);
    }

    /**
     * Read the root-level settings of the package.
     *
     * @return array
     */
    protected function loadPackageSettings(): array
    {
        if (isset($this->packageSettings)) {
            return $this->packageSettings;
        }

        $filename = $this->getRootFolder() . 'config/' . self::PACKAGE_FILENAME;

        $settings = $this->readJsonFile($filename);

        if (!$settings) {
            // The default values for a missing package
            // file can be set in a hidden ".config.json" file.
            $dotConfig = $this->loadConfigData();

            $jsClass = $dotConfig['client_class_info'] ?? [];

            if (!$jsClass && $this->fileExists('js', $this->shortName)) {
                $jsClass = [
                    'methods' => [
                        'initClass' => [
                            'isStatic' => true,
                            'isEmpty' => true
                        ],
                        'render' => [
                            'isStatic' => false,
                            'isEmpty' => false
                        ]
                    ]
                ];
            }

            // The 'hasCode' is used to know the the main JS class has to be
            // included in a site or not. If 'hasCode' is not defined, it can be
            // inferred from the given method information.
            // If there is at least one non-empty method, then there is code.
            if (!isset($jsClass['hasCode']) && !empty($jsClass['methods'])) {
                foreach ($jsClass['methods'] as $method) {
                    if (empty($method['isEmpty'])) {
                        $jsClass['hasCode'] = true;
                        break;
                    }
                }
            }

            $cssClass = $this->fileExists('css', $this->shortName);

            $settings = [
                'schemaVersion' => $dotConfig['schema_version'] ?? null,
                'directories' => ['src' => '.'],
                'jsClass' => $jsClass,
                'cssClass' => $cssClass
            ];
        }

        return $this->packageSettings = $settings;
    }

    /**
     * Load a package file into the cache and return it.
     *
     * Note: the method is called "load..." because the data is read and
     * saved in memory until clearMemoryCache() is called.
     *
     * @param string $filename
     * @return array|null
     */
    protected function loadPackageData(string $fileKey): ?array
    {
        if (isset($this->packageData[$fileKey])) {
            return $this->packageData[$fileKey];
        }

        $schemaRules = $this->getSchemaRules();

        $filename = $this->getRootFolder() . $schemaRules[$fileKey]['filePath'];

        // Note that a JSON error returns null.
        $data = $this->readJsonFile($filename);

        /** @todo Explain this step. */
        if ($data && ($jsonPath = $schemaRules[$fileKey]['jsonPath'] ?? false)) {
            $data = $this->getAssociateValueByKeys($data, $jsonPath);
        }

        return $this->packageData[$fileKey] = $data;
    }

    /**
     * Log system errors (with PSR-3 Logger Interface arguments).
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    // protected static function log($message, array $context = []): void
    // {
    //     $call = debug_backtrace()[0];

    //     // Send an error message to the defined error handling routines
    //     error_log("$call[file]:$call[line]\n" . print_r($message, true) . "\n" .
    //         ($context ? print_r($context, true) . "\n" : ''));
    // }

    // /**
    //  * @todo what is this for???
    //  *
    //  * @param array $files
    //  * @return array
    //  */
    // private function processCustomCSS(array $files): array
    // {
    //     if (!$files) {
    //         if ($cssClass = $this->loadPackageSettings()['cssClass'] ?? false) {
    //             $files[] = $this->shortName . ".css";
    //         }
    //     }

    //     foreach ($files as &$value) {
    //         $p = explode('.', $value);
    //         $ext = end($p);

    //         if ($ext == 'scss') {
    //             array_pop($p);
    //             $value = implode('.', $p) . '.css';
    //         }
    //     }

    //     return array_unique($files);
    // }

    /**
     * @todo Does it matter if it's for a parent class???
     * before it was conditional on $isParent.
     *
     * @param string $file
     * @param bool $childNeeds
     * @return string
     */
    private function processMainJS(string $file, bool $childNeeds = false): string
    {
        $needsMainJS = true;

        /**
         * @todo Explain the logic here.
         */
        if (!$childNeeds && $info = $this->getClientClassInfo()) {
            $hasCode = $info['hasCode'] ?? false;

            $needsMainJS = $hasCode;
        }

        if (!$needsMainJS || !file_exists($this->getMainJSFilePath())) {
            $file = '';
        }

        return $file;
    }

    /**
     * Load the config data into a memory cache if necessary, and returns it.
     *
     * @return void
     */
    private function loadConfigData()
    {
        if ($this->configData) {
            return $this->configData;
        }

        $filename = $this->getRootFolder() . '.config.json';

        /**
         * On error, set the data to some non-empty array!
         * @todo If there are no more older widgets, we should
         * throw an exception if there is no config data.
         */
        $data = $this->readJsonFile($filename) ?? ['error' => true];

        return $this->configData = $data;
    }

    /**
     * Get schema version number.
     * The .config.json has the schema version of the widget package.s
     * @return string
     */
    private function getSchemaVersionNum(): string
    {
        $data = $this->loadPackageSettings();

        // if ($this->configHandle) {
        //     $content = $this->configHandle->read();
        //     return $content['schema_version'] ?? '1.0.1';
        // }

        if ($data['error'] ?? false) {
            /** @todo Is this the idea? */
            return self::REQUIRED_SCHEMA_VERSION;
        } else {
            return $data['schemaVersion'] ?? '1.0.1';
        }
    }
}
