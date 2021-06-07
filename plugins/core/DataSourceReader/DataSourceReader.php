<?php

/**
 * File for class DataSourceReader.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

use Exception;
use Proximify\Glot\Renderer;
use Proximify\Glot\Widget;

/**
 * Reader of DataSource information.
 */
class DataSourceReader extends \Proximify\Glot\Plugin
{
    const DS = 'data_sources';

    protected $dsFolder;

    protected static $dsClasses = [];

    protected static $queryResults = [];

    public function init(): void
    {
        $this->dsFolder = $this->renderer->getOption('paths', 'root')
            . self::DS . '/';
    }

    public function getDSClass(array $info)
    {
        $ds = $info['dataSource'] ?? '';

        if (!empty(self::$dsClasses[$ds]))
            return self::$dsClasses[$ds];

        $dsFolder = $this->dsFolder . $ds . '/';

        if (!$ds || !file_exists($dsFolder))
            throw new \Exception(" $ds.");

        $settings = $this->getDSSettings($dsFolder);

        $class = $settings['class'];

        $dsClass = new $class(['folder' => $dsFolder]);

        self::$dsClasses[$ds] = $dsClass;

        return $dsClass;
    }

    public function getDSSettings($folder)
    {
        $filename = $folder . 'config/settings.json';

        if (!file_exists($filename))
            throw new \Exception("Cannot find the file $filename");

        $contents = $this->renderer->readJSONFile($filename);

        if (empty($contents['class']))
            throw new \Exception("Cannot find the class name in $filename.");

        return $contents;
    }

    function fetch($info)
    {
        $params = $this->getQueryParams($info);
        $type = $params['type'];

        $cacheKey = json_encode($info);

        if (!isset(self::$queryResults[$cacheKey])) {

            if ($type == 'ds') {
                // Renderer::log($params);
                $dsClass = $this->getDSClass($params);
                $data = $dsClass->find($params);
            } elseif ($type == 'external') {
                $data = file_get_contents($info);
            }

            self::$queryResults[$cacheKey] = $data;
        }

        return self::$queryResults[$cacheKey];
    }

    function getQueryParams($options)
    {
        $result = [];

        $errMsg = 'Invalid data source options';

        if (is_array($options)) {
            $ds = $options['dataSource'] ?? '';
            $from = $options['from'] ?? '';

            if (!$ds)
                throw new Exception($errMsg);

            $result = [
                'type' => 'ds',
                'dataSource' => $ds,
                'from' => $from,
                'column' => $options['column'] ?? '',
                'where' => $options['where'] ?? [],
                'query' => $options['query'] ?? ''
            ];
        } elseif (is_string($options)) {
            $protocolPos = strpos($options, '//');

            //local data source
            if (!$protocolPos) {
                $type = 'ds';
                $result = $this->getDSParams($options, $protocolPos);
            } else {
                $protocol = substr($options, 0, $protocolPos - 1);

                if (class_exists($protocol)) {
                    $type = 'ds';

                    $result = $this->getDSParams($options, $protocolPos);
                } else {
                    $type = 'external';

                    $result = ['type' => $type];
                }
            }
        }

        return $result;
    }

    function getDSParams($options, $pos)
    {
        $ds = substr($options, $pos + 2);

        $p = explode('/', $ds);

        if (count($p) < 2)
            throw new Exception("Invalid data source option: $options. ");

        $dataSource = array_shift($p);

        $pathStr = implode('/', $p);

        $p2 = explode('?', $pathStr);

        $from = $p2[0];

        $result = [
            'type' => 'ds',
            'dataSource' => $dataSource,
            'from' => $from
        ];

        if (count($p2) > 1) {
            $query = $p2[1];

            $p3 = explode('&', $query);

            $operators = [
                '='
            ];

            if (count($p3)) {
                $queryName = $p3[0];

                $start = 0;

                if ($this->isQueryName($queryName, $operators)) {
                    $result['query'] = $queryName;
                    $start = 1;
                }

                $where = [];
                for ($i = $start; $i < count($p3); $i++) {
                    $this->addQueryCondition($p3[$i], $operators, $where);
                }

                $result['where'] = $where;
            }
        }

        return $result;
    }

    function addQueryCondition($value, $operators, &$where)
    {
        foreach ($operators as $operator) {
            $p = explode($operator, $value);

            if (count($p) < 2)
                continue;

            if ($operator == '=') {
                $where[$p[0]] = $p[1];
                break;
            }
        }
    }

    function isQueryName($name, $operators)
    {
        foreach ($operators as $value) {
            if (strpos($name, $value) !== false)
                return false;
        }

        return true;
    }

    // const DATA_SOURCE = 'data_sources';
    // const QUERIES = 'queries';
    // const LIMITS = '5000';
    // const LINK_ELEM_KEY = 'data-linkEle';

    // protected $dataSourceFolder;
    // protected $queryFolder;
    // protected $websiteFolder;
    // protected $targetLang;
    // protected $translationCache = [];

    // public function init(): void
    // {
    //     $options = $this->options;

    //     $this->dataSourceFolder = $options['folder'] ?? 'data_sources/';
    //     $this->queryFolder = $options['queryFolder'] ?? 'queries/';

    //     $this->targetLang = $options['targetLang'] ??
    //         $this->renderer->getLanguage();

    //     $this->websiteFolder = $options['websiteFolder'] ??
    //         $this->renderer->getClientFolder();
    // }

    // /**
    //  * Fetch data from the given data source.
    //  *
    //  * @param array $dataSource
    //  * @return array
    //  */
    // public function fetchData(array $dataSource): array
    // {
    //     $result = '';
    //     $parsedResult = [];
    //     $id = $dataSource['id'] ?? '';

    //     $filters = $dataSource['filters'] ?? [];

    //     if (!$id) {
    //         return [];
    //     }

    //     $columnName = $dataSource['columnName'] ?? '';

    //     $folder = $this->queryFolder . $id;

    //     if (!is_dir($folder)) {
    //         return [];
    //     }


    //     $settings = $folder . '/settings.json';
    //     $contents = $this->renderer->readJSONFile($settings);

    //     $dataSourceId = $contents['dataSourceId'] ?? '';

    //     $dataSourceFolder = $this->dataSourceFolder . $dataSourceId;
    //     $dsSettings = $dataSourceFolder . '/settings.json';

    //     if (!file_exists(($dsSettings))) {
    //         return [];
    //     }

    //     $dsContents = $this->renderer->readJSONFile($dsSettings);

    //     $type = $dsContents['type'] ?? '';

    //     $params = array_merge($dsContents, $contents);

    //     $preDefinedColumnName = $params['columnNames'] ?? [''];
    //     $preDefinedColumnName = $preDefinedColumnName[0];

    //     $columnName = $columnName ?? $preDefinedColumnName;

    //     if ($type == 'database') {
    //         $columnName = $this->makeColumnName($columnName, $params);

    //         $result = $this->fetchDataFromDatabase(
    //             $params,
    //             $filters,
    //             $columnName
    //         );

    //         $this->getLocalizedData($result, $params);
    //     } elseif ($type == 'local' || $type == 'excel') {
    //         $columnName = $this->makeColumnName($columnName, $params);

    //         $result = $this->queryLocalData(
    //             $dataSourceFolder,
    //             $params,
    //             $filters,
    //             $columnName,
    //             $type
    //         );

    //         $this->getLocalizedData($result, $params);
    //     } elseif ($type == 'http') {
    //         $subType = $params['subType'];
    //         if ($subType == 'ghost') {
    //             $result = $this->queryHTTPData($params, $columnName);
    //         } else {
    //             $columnName = $this->makeColumnName($columnName, $params);

    //             $result = $this->queryHTTPData($params, $columnName);

    //             $this->getLocalizedData($result, $params);
    //         }
    //     } elseif ($type == 'website_reflection') {
    //         $result = $this->fetchWebsitePages($params);
    //     } elseif ($type == 'website_inspector') {
    //         $result = $this->fetchExternalPages($params);
    //     } elseif ($type == 'widget_reflection') {
    //         $result = $this->fetchWebsiteWidgets($params);
    //     }

    //     $parsedResult = [
    //         'dataSourceSettings' => $params,
    //         'data' => $result
    //     ];

    //     return $parsedResult;
    // }

    // /**
    //  * Get query columns. If the data source has the column as the translation 
    //  * of the given column, we should add the column into the query.
    //  *
    //  * @param string|array $columnName
    //  * @param array $params
    //  * @return array
    //  */
    // public function makeColumnName($columnName, array $params): array
    // {
    //     $tableName = $params['tableName'] ?? [];
    //     $tableName = is_array($tableName) ? $tableName[0] : $tableName;

    //     $result = [];

    //     if ($this->targetLang) {
    //         if (is_array($columnName)) {
    //             foreach ($columnName as $value) {
    //                 $item = $this->appendTranslationColumn(
    //                     $params,
    //                     $tableName,
    //                     $value
    //                 );

    //                 if (is_array($item)) {
    //                     $result = array_merge($result, $item);
    //                 } else {
    //                     $result[] = $item;
    //                 }
    //             }
    //         } else {
    //             $result = $this->appendTranslationColumn(
    //                 $params,
    //                 $tableName,
    //                 $columnName
    //             );
    //         }
    //     } else {
    //         $result = $columnName;
    //     }

    //     return $result;
    // }

    // /**
    //  * Add the translation column into the query.
    //  *
    //  * @param array $params
    //  * @param string $tableName
    //  * @param string $columnName
    //  * @return void
    //  */
    // public function appendTranslationColumn(
    //     array $params,
    //     string $tableName,
    //     string $columnName
    // ) {
    //     if (isset($params['columns'][$tableName][$columnName])) {
    //         $columnSettings = $params['columns'][$tableName][$columnName];

    //         $targetColumn = $columnSettings[$this->targetLang] ?? '';

    //         if ($targetColumn) {
    //             $columnName = [$columnName, $targetColumn];
    //         }
    //     }

    //     return $columnName;
    // }

    // /**
    //  * Localize the date which was fetched from the data source
    //  *
    //  * @param array $data
    //  * @param array $params
    //  * @return void
    //  */
    // public function getLocalizedData(array &$data, array $params)
    // {
    //     $srcLang = $params['lang'] ?? '';

    //     if ($srcLang && $this->targetLang && $srcLang != $this->targetLang) {
    //         $tableName = $params['tableName'] ?? [];
    //         $tableName = $tableName[0];

    //         $columns = $params['columns'];

    //         $columnsSettings = $columns[$tableName];

    //         $dsId = $params['dataSourceId'] ?? '';

    //         $dsFolder = $this->dataSourceFolder . $dsId;

    //         $dsTranslationFolder = $dsFolder . '/dictionaries';

    //         foreach ($data as &$value) {
    //             foreach ($value as $key => &$propertyVal) {
    //                 if (
    //                     isset($columnsSettings[$key][$this->targetLang]) &&
    //                     $columnsSettings[$key][$this->targetLang]
    //                 ) {
    //                     $targetColumn = $columnsSettings[$key][$this->targetLang];

    //                     $propertyVal = $value[$targetColumn] ?? $propertyVal;
    //                 } elseif (
    //                     isset($columnsSettings[$key]['selectable']) &&
    //                     $columnsSettings[$key]['selectable']
    //                 ) {
    //                     $propertyVal = $this->getLocalizedValue(
    //                         $columnsSettings,
    //                         $dsTranslationFolder,
    //                         $key,
    //                         $srcLang,
    //                         $propertyVal,
    //                         $value
    //                     );
    //                 }
    //             }
    //         }
    //     }
    // }

    // /**
    //  * Localize text
    //  *
    //  * @param array $columnsSettings
    //  * @param string $dsFolder
    //  * @param string $key
    //  * @param string $srcLang
    //  * @param string $propertyVal
    //  * @param array $value
    //  * @return void
    //  */
    // public function getLocalizedValue(
    //     array $columnsSettings,
    //     string $dsFolder,
    //     string $key,
    //     string $srcLang,
    //     string $propertyVal,
    //     array $value
    // ) {
    //     $result = $propertyVal;
    //     $md5 = md5($propertyVal);
    //     if (isset($this->translationCache[$key][$md5])) {
    //         return $this->translationCache[$key][$md5];
    //     }

    //     if (isset($columnsSettings[$key])) {
    //         $columnSettings = $columnsSettings[$key];

    //         if (
    //             isset($columnSettings['multilingual']) &&
    //             $columnSettings['multilingual']
    //         ) {
    //             if (
    //                 isset($columnSettings[$this->targetLang]) &&
    //                 $columnSettings[$this->targetLang]
    //             ) {
    //                 $result = $value[$columnSettings[$this->targetLang]];
    //             } else {
    //                 $dictionary = $dsFolder . '/' . md5($key) . '/' . $srcLang .
    //                     '_' . $this->targetLang . '/' .
    //                     $md5 . '.json';

    //                 if (file_exists($dictionary)) {
    //                     $contents = $this->renderer->readJSONFile($dictionary);

    //                     $result = $contents['target']['value'];

    //                     $this->translationCache[$key][$md5] = $result;
    //                 }
    //             }
    //         }
    //     }

    //     return $result;
    // }

    // /**
    //  * This function fetch the data source whose type is HTTP
    //  *
    //  * @param array $params
    //  * @param string $columnName
    //  * @return void
    //  */
    // public function queryHTTPData(array $params, $columnName)
    // {
    //     $subType = $params['subType'];

    //     $result = [];

    //     if ($subType == 'airtable') {
    //         $doc = $params['doc'];
    //         $tableName = $params['tableName'];

    //         if (is_array($tableName)) {
    //             $tableName = $tableName[0];
    //         }

    //         $apiKey = $params['apiKey'];

    //         $config = [
    //             'doc' => $doc,
    //             'tableName' => $tableName,
    //             'apiKey' => $apiKey
    //         ];

    //         $airtable = $this->renderer->require('DataSource\Airtable', $config);

    //         return $airtable->fetchRecords($params, $columnName);
    //     } elseif ($subType == 'customize') {
    //         $url = $params['url'];

    //         $query = $url;

    //         $queryParams = $params['queryParams'] ?? '';
    //         if ($queryParams) {
    //             $query = $query . '?' . $queryParams;
    //         }

    //         $result = file_get_contents($query);

    //         return $result;
    //     }
    //     // elseif ($subType == 'ghost') {
    //     //     require_once('DataSources/Ghost.php');
    //     //     $params['key'] = $params['apiKey'];
    //     //     $ghost = new Ghost($params);

    //     //     return $ghost->getPosts();
    //     // }
    // }

    // /**
    //  * Fetch data from the data source whose type is database
    //  *
    //  * @param array $params
    //  * @param array $filters
    //  * @param string $columnName
    //  * @return void
    //  */
    // public function fetchDataFromDatabase(array $params, array $filters, $columnName = '')
    // {
    //     $result = '';

    //     $subType = $params['subType'] ?? '';

    //     if ($subType == 'mysql') {
    //         $queryMethod = $params['queryMethod'] ?? '';

    //         if ($queryMethod) {
    //             $result = $this->$queryMethod($params, $filters, $columnName);
    //         }
    //     }

    //     return $result;
    // }

    // /**
    //  * Query data from database
    //  *
    //  * @param array $params
    //  * @param array $filters
    //  * @param array|string $columnName
    //  * @return void
    //  */
    // public function queryColumn(array $params, array $filters, $columnName)
    // {
    //     $host = $params['host'] ?? '';
    //     $username = $params['username'] ?? '';
    //     $pwd = $params['password'] ?? '';
    //     $port = $params['port'] ?? '';
    //     $db = $params['dbname'] ?? '';

    //     $link = @mysqli_connect($host, $username, $pwd, $db, $port);

    //     if (!$link) {
    //         $msg = 'Database connection error (' . mysqli_connect_errno() . '): ' .
    //             mysqli_connect_error();

    //         throw new \Exception($msg);
    //     }

    //     $queryColumn = $columnName;
    //     $tableName = $params['tableName'] ?? [];
    //     $tableName = $tableName[0];

    //     $queryStr = '';

    //     if (is_array($filters)) {
    //         foreach ($filters as $key => $value) {
    //             $queryStr = $queryStr ? $queryStr . ' AND ' . $key . ' = ' .
    //                 $value : $key . ' = ' . $value;
    //         }
    //     }

    //     if (is_array($queryColumn)) {
    //         $queryColumn = implode(', ', $queryColumn);
    //     }

    //     $q = "SELECT $queryColumn FROM $tableName";

    //     if ($queryStr) {
    //         $q .= ' WHERE ' . $queryStr;
    //     }

    //     $table = [];

    //     $result = mysqli_query($link, $q);

    //     while ($row = mysqli_fetch_row($result)) {
    //         $ele = [];

    //         for ($i = 0; $i < count($row); $i++) {
    //             $ele[$columnName[$i]] = $row[$i];
    //         }

    //         $table[] = $ele;
    //     }

    //     mysqli_free_result($result);

    //     return $table;
    // }

    // /**
    //  * Query data from the data source whose type is the local file
    //  *
    //  * @param string $folder
    //  * @param array $params
    //  * @param array $filters
    //  * @param array|null $columnName
    //  * @param string $type
    //  * @return void
    //  */
    // public function queryLocalData(
    //     string $folder,
    //     array $params,
    //     ?array $filters,
    //     ?array $columnName,
    //     string $type
    // ) {
    //     $tableName = $params['tableName'] ?? [];
    //     $tableName = $tableName[0];

    //     $dataFolder = $type == 'local' ? $folder . '/data/' : $folder . '/tables/';
    //     $filename = $dataFolder . $tableName . '.json';

    //     $result = [];

    //     if (file_exists(($filename))) {
    //         $contents = $this->renderer->readJSONFile($filename);

    //         if ($columnName) {
    //             foreach ($contents as $value) {
    //                 $flag = true;

    //                 if ($filters) {
    //                     foreach ($filters as $property => $propertyVal) {
    //                         if (isset($value[$property])) {
    //                             $propertyItem = $value[$property];

    //                             if ($propertyItem != $propertyVal) {
    //                                 $flag = false;
    //                                 break;
    //                             }
    //                         } else {
    //                             $flag = false;
    //                             break;
    //                         }
    //                     }
    //                 }

    //                 if ($flag) {
    //                     $ele = [];
    //                     foreach ($columnName as $columnItem) {
    //                         if (isset($value[$columnItem])) {
    //                             $ele[$columnItem] = $value[$columnItem];
    //                         }
    //                     }

    //                     $result[] = $ele;
    //                 }
    //             }
    //         } else {
    //             $result = $contents;
    //         }
    //     }

    //     return $result;
    // }

    // // function buildDataSourceCache($result, $columnName, $filters, $params)
    // // {
    // //     $name = $this->buildCacheName($columnName, $filters);

    // //     $queryId = $params['queryId'];

    // //     $folder = $this->queryFolder . $queryId;

    // //     $cacheFolder = $folder . '/cache/';

    // //     $file = $cacheFolder . $name . '.json';
    // //     $handle = new JsonFile($file);
    // //     $handle->write($result);
    // //     $handle->close();
    // // }

    // // function buildCacheName($columnName, $filters)
    // // {
    // //     if (is_array($columnName)) {
    // //         $name = implode('_', $columnName);
    // //     } else {
    // //         $name = $columnName;
    // //     }

    // //     if ($filters) {
    // //         foreach ($filters as $key => $value) {
    // //             $name .= $key . '_' . $value;
    // //         }
    // //     }

    // //     $name = Base::normalizeString($name);

    // //     return $name;
    // // }

    // /**
    //  * Fetch pages structure from the url
    //  *
    //  * @param array $params
    //  * @param array $result
    //  * @return void
    //  */
    // public function fetchExternalPages(array $params, array &$result = [])
    // {
    //     $sitemap = $params['sitemap'];

    //     $includeSubFolders = $params['includeSubFolders'];

    //     if ($sitemap) {
    //         $contents = file_get_contents($sitemap);

    //         if ($contents) {
    //             $dom = $this->renderer->require('Core\\PageDom');

    //             $dom->loadXML($contents);

    //             $urls = $dom->getElementsByTagName('url');

    //             foreach ($urls as $url) {
    //                 $loc = $dom->getFirstChildByTagName($url, 'loc');

    //                 if ($loc) {
    //                     $nodeVal = $loc->nodeValue;

    //                     if (!in_array($nodeVal, $result)) {
    //                         $result[] = $nodeVal;
    //                     }

    //                     if (count($result) >= self::LIMITS) {
    //                         return $result;
    //                     }
    //                 }
    //             }

    //             if ($includeSubFolders) {
    //                 $childSitemaps = $dom->getElementsByTagName('sitemap');

    //                 foreach ($childSitemaps as $childSitemap) {
    //                     $loc = $dom->getFirstChildByTagName($childSitemap, 'loc');

    //                     if ($loc) {
    //                         $nodeVal = $loc->nodeValue;

    //                         $params['sitemap'] = $nodeVal;

    //                         if (count($result) < self::LIMITS) {
    //                             $this->fetchExternalPages($params, $result);
    //                         }
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     return $result;
    // }

    /**
     * Fetch pages structure from local websites
     *
     * @param array $params
     * @param string $folder
     * @param glot\Component|null $component
     * @param array $searchParams
     * @return array|null
     */
    public function fetchWebsitePages(
        array $params,
        string $folder = '',
        ?Widget $component = null,
        array $searchParams = []
    ) {
        $folder = $folder ? $folder : $this->websiteFolder;
        $path = $params['path'] ?? '';

        $settingsPath = $folder . 'settings/glot_pageSettings.json';
        $settings = $this->renderer->readJSONFile($settingsPath);

        $webservices = $folder . 'settings/webservices.json';
        $content = $this->renderer->readJSONFile($webservices);
        $homePage = $content['homepage'] ?? 'home';

        $pageFolder = $this->getTargetFolder($path, $settings, $folder);

        $result = [];

        if ($folder && is_dir($folder)) {
            if ($searchParams) {
                $exclude = $searchParams['exclude'] ?? '';

                if ($exclude) {
                    $searchParams['exclude'] = $this->convertRegEx($exclude);
                }
            }

            $result = $this->getPagesInfo(
                $pageFolder,
                $settings,
                $homePage,
                $component,
                $searchParams
            );
        }

        return $result;
    }

    /**
     * Parse exclusion parameter
     *
     * @param string $reg
     * @return void
     */
    public function convertRegEx(string $reg)
    {
        $p = explode(',', $reg);
        $result = [];

        foreach ($p as $str) {
            $str = trim($str);
            $str = str_replace('/', "\/", $str);
            $str = str_replace('**', "~", $str);
            $str = str_replace('*', "[^\/]*", $str);
            $str = str_replace('~', '.*', $str);

            $str = '/^' . $str . '$/';
            $result[] = $str;
        }

        return $result;
    }

    /**
     * Get the information of pages
     *
     * @param string $folder
     * @param array $settings
     * @param string $homePage
     * @param glot\Component|null $component
     * @param array $searchParams
     * @return void
     */
    public function getPagesInfo(
        string $folder,
        array $settings,
        string $homePage,
        ?Widget $component,
        array $searchParams
    ) {
        $result = [];

        $contents = $this->renderer->getDirContents($folder, ['_masters']);

        $exclude = $searchParams['exclude'] ?? '';

        foreach ($contents as $value) {
            $target = $folder . $value;

            if (is_dir($target)) {
                $info = $settings[$value] ?? [];

                if (isset($info['invisible']) && $info['invisible']) {
                    continue;
                }

                $folderLabel = $this->getGivenLabel($settings, $value);

                if ($exclude) {
                    foreach ($exclude as $reg) {
                        if (preg_match($reg, $folderLabel, $mattches)) {
                            continue 2;
                        }
                    }
                }

                $info['name'] = $value;
                $info['data'] = $this->getPagesInfo(
                    $target . '/',
                    $settings,
                    $homePage,
                    $component,
                    $searchParams
                );

                $result[] = $info;
            } else {
                $basename = pathinfo($value, PATHINFO_FILENAME);
                $info = $settings[$basename] ?? [];

                if (isset($info['invisible']) && $info['invisible']) {
                    continue;
                }

                $pageLabel = $this->getGivenLabel($settings, $basename);

                if ($exclude) {
                    foreach ($exclude as $reg) {
                        if (preg_match($reg, $pageLabel, $mattches)) {
                            continue 2;
                        }
                    }
                }

                $info['name'] = $basename;

                if ($basename == $homePage) {
                    $info['isHome'] = true;
                }

                $info['file'] = $target;

                if ($component) {
                    $info['href'] = $this->renderer->getLocalizer()
                        ->parseHRefParam($basename);
                }

                $result[] = $info;
            }
        }

        $result = $this->sortPages($result, $settings);

        return $result;
    }

    /**
     * Get the label of the page name from the page settings
     *
     * @param array $settings
     * @param string $name
     * @return void
     */
    public function getGivenLabel(array $settings, string $name)
    {
        $info = $settings[$name] ?? [];

        $path = $info['path'] ?? '';
        $label = $info['label'] ?? '';

        $result = '';

        if ($path) {
            $basename = pathinfo($path, PATHINFO_BASENAME);
            $folderLabel = $this->getGivenLabel($settings, $basename);
            $result .= $result ? $result . '/' . $folderLabel : $folderLabel;
        }

        if ($result) {
            $result .= '/' . $label;
        } else {
            $result = $label;
        }

        return $result;
    }

    /**
     * Sort pages based on settings
     *
     * @param array $pages
     * @param array $settings
     * @return void
     */
    public function sortPages(array $pages, array $settings)
    {
        $result = [];

        $names = [];

        foreach ($pages as $item) {
            $names[$item['name']] = $item;
        }

        foreach ($settings as $key => $value) {
            if (isset($names[$key])) {
                $result[] = $names[$key];
            }
        }

        return $result;
    }

    /**
     * Get the folder of the page
     *
     * @param string $path the path of the page
     * @param array $settings
     * @param string $folder
     * @return string
     */
    public function getTargetFolder(string $path, array $settings, string $folder = ''): string
    {
        $folder = $folder ? $folder : $this->websiteFolder;
        $pageFolder = $folder . 'pages/';

        if (!$path) {
            return $pageFolder;
        }

        $a = explode('/', $path);

        foreach ($a as $value) {
            if ($value) {
                $targetFolder = $pageFolder . $value . '/';

                if (is_dir($targetFolder)) {
                    $pageFolder = $pageFolder . $value . '/';
                } else {
                    $contents = $this->renderer->getDirContents($pageFolder);

                    $flag = false;

                    foreach ($contents as $sub) {
                        $subFolder = $pageFolder . $sub;

                        if (is_dir($subFolder)) {
                            $label = $settings[$sub]['label'] ?? $sub;

                            if ($value == $label || $value == $sub) {
                                $flag = true;

                                $pageFolder = $pageFolder . $sub . '/';
                                break;
                            }
                        }
                    }

                    if (!$flag) {
                        return '';
                    }
                }
            }
        }

        return $pageFolder;
    }

    // /**
    //  * Fetch widgets structure from local websites
    //  *
    //  * @param array $params
    //  * @param string $folder
    //  * @return array|null
    //  */
    // public function fetchWebsiteWidgets($params, $folder = '')
    // {
    //     $pages = $params['pages'] ?? '';
    //     $widget = $params['widget'] ?? '';

    //     $folder = $folder ? $folder : $this->websiteFolder;
    //     $settingFile = $folder . 'settings/glot_pageSettings.json';
    //     $settings = $this->renderer->readJSONFile($settingFile);

    //     $p = explode(',', $pages);
    //     $w = explode(',', $widget);

    //     foreach ($w as &$wItem) {
    //         $wItem = trim($wItem);
    //     }

    //     $result = [];

    //     foreach ($p as $value) {
    //         $pageName = trim($value);
    //         $page = $this->getRealPage($pageName, $settings);

    //         if ($page) {
    //             $filename = $folder . 'pages/' . $page . '.json';

    //             $contents = $this->renderer->readJSONFile($filename);

    //             $result[$pageName] = $this->getWidgetsByClass($contents, $w);
    //         }
    //     }

    //     return $result;
    // }

    // /**
    //  * Get the file name of the page
    //  *
    //  * @param string $page the page name, it can be the label of the page
    //  * @param array $settings
    //  * @return string
    //  */
    // public function getRealPage(string $page, array $settings): string
    // {
    //     $alias = pathinfo($page, PATHINFO_FILENAME);

    //     $pageName = '';

    //     if (isset($settings[$alias])) {
    //         $path = $settings[$alias]['path'];

    //         $pageName = $path ? $path . '/' . $alias : $alias;
    //     } else {
    //         foreach ($settings as $filename => $fileAttr) {
    //             if (!isset($fileAttr['path']) || !isset($fileAttr['label'])) {
    //                 continue;
    //             }

    //             $path = $fileAttr['path'];

    //             if ($fileAttr['label'] == $alias) {
    //                 $pageName = $path ? $path . '/' . $filename : $filename;

    //                 break;
    //             } else {
    //                 foreach ($fileAttr['urlName'] as $lang => $value) {
    //                     if ($value == $alias) {
    //                         $pageName = $path ? $path . '/' . $filename : $filename;

    //                         break 2;
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     return $pageName;
    // }


    // /**
    //  * Get particular json by Widget;
    //  * Change json with specific Widget;
    //  */
    // public function getWidgetsByClass($array, $class)
    // {
    //     $result = [];

    //     if (!is_array($array)) {
    //         return [];
    //     }

    //     if (isset($array['widget'])) {
    //         if (!is_array($class)) {
    //             if (array_search($class, $array, true) === 'widget') {
    //                 array_push($result, $array);
    //             }
    //         } else {
    //             if (isset($array['widget']) && in_array($array['widget'], $class)) {
    //                 array_push($result, $array);
    //             }
    //         }
    //     }

    //     if (isset($array['data'])) {
    //         $data = $array['data'];
    //         foreach ($data as $key => $value) {
    //             $result = array_merge($result, $this->getWidgetsByClass($value, $class));
    //         }
    //     }

    //     return $result;
    // }
}
