<?php

/**
 * File for class Component.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot;

use Proximify\Glot\Renderer;

use Exception;

/**
 * The base class for all GLOT plugins.
 * 
 * It is meant to be a simple base class in order to avoid polluting the 
 * method namespace of plugins.
 */
class DataSource
{
    const DIR_MOD = 0777;
    const DATA_SOURCES = 'data_sources';

    /** @var array|null Configuration options for the component. */
    protected $options;

    /**
     * Create a generic Plugin.
     * 
     * This based constructor cannot be overridden by extended classes.
     *
     * @param array $options All keys in the array depend on each plugin type.
     */
    final public function __construct(?array $options = null)
    {
        $this->options = $options;

        putenv('PATH=' . getenv('PATH') . ':/usr/local/nodejs/bin');
        putenv('PATH=' . getenv('PATH') . ':/usr/local/bin');
    }

    public function create(array $options)
    {
        $this->init($options);
    }

    public function init(array $options)
    {
        $this->options = $options;

        $this->initDataSourceFolder();

        $this->initData();

        $this->initSettings();

        $this->initDataSchema();
    }

    public function initDataSourceFolder()
    {
        $name = $this->options['name'] ?? '';

        if (!$name)
            throw new \Exception("Invalid data source name");

        $folder = getcwd() . '/' . self::DATA_SOURCES . "/$name";

        if (!is_dir($folder)) {
            self::makeDir($folder);

            $dataFolder = $folder . "/data";
            self::makeDir($dataFolder);

            $settingsFolder = $folder . '/config';
            self::makeDir($settingsFolder);

            $queryFolder = $folder . '/queries';
            self::makeDir($queryFolder);

            $dsQueryFolder = $this->getDataSourceFolder() .
                'queries';

            if (is_dir($dsQueryFolder))
                exec("cp -R -p $dsQueryFolder $queryFolder");
        } else {
            throw new \Exception("The data source $name exits, please choose a unique name.");
        }
    }

    public function getDataSourceFolder()
    {
        $name = $this->options['name'] ?? '';

        $folder = $this->options['folder'] ?? getcwd() . '/' . self::DATA_SOURCES . "/$name/";

        return $folder;
    }

    public function initData()
    {
    }

    public function initDataSchema()
    {
    }

    public function initSettings()
    {
        $name = $this->options['name'] ?? '';
        $className = $this->options['className'] ?? '';

        $reflector = new \ReflectionClass($className);

        $baseFolder = dirname($reflector->getFileName()) . '/../';
        $baseSettingFilename = $baseFolder . 'config/settings.json';

        $dsSettings = json_decode(
            file_get_contents($baseSettingFilename),
            true, // assoc output
            512, // default depth
            JSON_THROW_ON_ERROR
        );

        $settingFilename = $this->getDataSourceFolder() .
            "config/settings.json";

        file_put_contents($settingFilename, json_encode([
            'name' => $name,
            "class" => $className,
            'readerClass' => $dsSettings['readerClass'] ?? ''
        ], true));
    }

    public function find(array $options)
    {
        if ($this->getReader())
            return $this->getReader()->find($options);

        return [];
    }

    function getReader()
    {
        $reader = $this->reader ?? false;

        if (!$reader) {

            $this->reader = null;

            $className = get_called_class();
            $reflector = new \ReflectionClass($className);

            $baseFolder = dirname($reflector->getFileName()) . '/../';

            $filename = $baseFolder . "config/settings.json";

            $settings = json_decode(
                file_get_contents($filename),
                true, // assoc output
                512, // default depth
                JSON_THROW_ON_ERROR
            );

            $class = $settings['readerClass'] ?? '';

            if ($class)
                $this->reader = new $class($this->options ?? []);
        }

        return $this->reader;
    }

    public function findOne(array $options)
    {
    }

    public function getValidOperators()
    {
        return [
            '$eq', '$gt', '$gte', '$in', '$lt', '$lte',
            '$ne', '$nin', '$and', '$not', '$nor', '$or'
        ];
    }

    public function getSchema(array $params)
    {
        $folder = $params['folder'];
        $schemaName = $params['schema'];

        $filename = $folder . '../schemas/' . $schemaName . '.json';

        if (!\file_exists($filename))
            throw new Exception("Cannot find the schema $filename");

        $schema = json_decode(
            file_get_contents($filename),
            true, // assoc output
            512, // default depth
            JSON_THROW_ON_ERROR
        );

        return $schema;
    }

    function mergeQueryOptions($options)
    {
        $query = $options['query'] ?? '';

        if ($query) {
            $queryOptions = $this->getQuery($query);
            $options = array_merge($options, $queryOptions);
        }

        return $options;
    }

    function getQuery(string $queryName): array
    {
        $filename = $this->getDataSourceFolder() .
            "queries/$queryName.json";

        $result = [];

        if (file_exists($filename)) {
            $result = json_decode(
                file_get_contents($filename),
                true, // assoc output
                512, // default depth
                JSON_THROW_ON_ERROR
            );
        }

        return $result;
    }

    /**
     * This function assumes that is_array($array) == true, and that if there is at 
     * least one string key, $array will be regarded as associative array.
     */
    static function isAssocArray(array $array)
    {
        return (bool) count(array_filter(array_keys($array), 'is_string'));
    }

    /**
     * Create folder with certain permission
     *
     * @param string $path
     * @param [type] $mode
     * @param boolean $recursive
     * @return void
     */
    public static function makeDir(string $path, $mode = self::DIR_MOD, bool $recursive = true)
    {
        if (!mkdir($path, $mode, $recursive)) {
            throw new \Exception("Cannot create folder '$path'");
        }

        if ($mode) {
            chmod($path, $mode);
        }
    }

    /**
     * Execute command
     *
     * @param string $cmd
     * @param string|null $workingDir
     * @param array|null $env
     * @return array
     */
    public static function execute(string $cmd, ?string $workingDir = null, ?array $env = null): array
    {
        if (is_null($workingDir)) {
            $workingDir = __DIR__ . '/nodejs/';
        }

        $descriptorSpec = array(
            0 => array('pipe', 'r'),  // stdin
            1 => array('pipe', 'w'),  // stdout
            2 => array('pipe', 'w'),  // stderr
        );

        $process = proc_open($cmd, $descriptorSpec, $pipes, $workingDir, $env);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $out = [
            'code' => proc_close($process),
            'out' => trim($stdout),
            'err' => trim($stderr),
        ];

        if (self::isError($out)) {
            Renderer::log($out);
            Renderer::trace();

            // throw new Exception($out['err']);
        }

        return $out;
    }

    /**
     * Check the error from the error message
     *
     * @param array $out
     * @return boolean
     */
    public static function isError(array $out): bool
    {
        return ($out['err'] && ($out['code'] != 0 || strpos($out['err'], 'warning:') !== 0));
    }
}
