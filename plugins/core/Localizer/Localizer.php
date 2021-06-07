<?php

/**
 * Localizer plugin.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

/**
 * Undocumented class
 */
class Localizer extends \Proximify\Glot\Plugin
{
    /** @var DataSourceReader */
    protected $reader;

    protected function getReader(): DataSourceReader
    {
        if (!$this->reader) {
            $this->reader = $this->renderer->getDataSourceReader();

            $this->reader->init();
        }

        return $this->reader;
    }

    /**
     * Localize parameters of the given widget $class.
     *
     * @param array $params parameters of the widget
     * @param array|null $subParams sub parameters inside some sub menus
     * @param string|null $class The name of widget
     * @param array|null [dataSource]
     * @return array
     */
    public function getLocalizedSettings(
        array $params,
        ?array $subParams = null,
        ?string $class = null,
        ?array $dataSource = null
    ): array {
        $metaParams = $subParams ? $subParams :
            $this->renderer->getExtendedWidgetParams($class);

        if (!$metaParams) {
            return $params;
        }

        foreach ($metaParams as &$info) {
            if (empty($info['name'])) {
                continue;
            }

            $name = $info['name'] ?? '';
            $type = $info['type'] ?? '';

            if ($type == 'general' || $type == 'submenu') {
                $submenu = $info['data'] ?? [];

                if ($submenu) {
                    $params = array_merge(
                        $params,
                        $this->getLocalizedSettings(
                            $params,
                            $submenu,
                            $class,
                            $dataSource
                        )
                    );
                }
            } else {
                $emptyValue = $info['emptyValue'] ?? '';

                if ($type == 'dropdown' || $type == 'font') {
                    $emptyValue = $this->getDropdownDefaultVal($info, $emptyValue);
                }
               
                //The inline parameter has higher priority,
                //check referenced parameter value only if the inline parameter value
                //is undefined.
                if (!isset($params[$name]) && isset($params['$' . $name])) {
                    $params[$name] = $this->getReferencedParams(
                        $params['$' . $name],
                        $type
                    );
                    // if ($paramDataSrc = $dataSource[$name] ?? false) {
                    //     $schema = $type == 'table' ? ($params[$name] ?? []) : [];

                    //     $params[$name] = $this->getParamFromDataSource(
                    //         $paramDataSrc,
                    //         $type,
                    //         $emptyValue,
                    //         $schema
                    //     );
                    // }
                } else {
                    $dictionary = $params['_dictionary'] ?? null;

                    switch ($type) {
                        case 'media':
                        case 'websiteAssets':
                            if (isset($params[$name])) {
                                $asset = $this->localize($params[$name], null, $dictionary);
                                $params[$name] = $this->renderer->getAssets()
                                    ->makeAssetUrl(
                                        $asset,
                                        $class
                                    );
                            } else {
                                $params[$name] = $emptyValue;
                            }
                            break;
                        case 'file':
                        case 'upload':
                            if (isset($params[$name])) {
                                $textVal = $this->localize($params[$name], null, $dictionary);
                                $params[$name] = $textVal;
                            } else {
                                $params[$name] = $emptyValue;
                            }
                            break;
                        case 'text':
                            if (isset($params[$name])) {
                                $textVal = $this->localize($params[$name], null, $dictionary);
                                $textVal = $this->stripeEmptyTags($textVal);
                                $params[$name] = $textVal;
                            } else {
                                $params[$name] = $emptyValue;
                            }
                            break;
                        case 'href':
                            if (isset($params[$name])) {
                                $params[$name] = $this->parseHRefParam(
                                    $this->localize($params[$name], null, $dictionary)
                                );
                            } else {
                                $params[$name] = $emptyValue;
                            }
                            break;
                        case 'table':
                            $params[$name] = 'No data source linked.';
                            break;
                        case 'font':
                            if (!empty($params[$name])) {
                                $this->renderer->getFonts()
                                    ->activateFont($params[$name], $class);
                            }
                            // no break
                        default:
                            if (!isset($params[$name]) || $params[$name] === '') {
                                $params[$name] = $emptyValue;
                            }
                    }
                }
            }
        }

        return $params;
    }

    /**
     * This function localize the $data.
     * The inline value for a language has presence over its dictionary value.
     * The inline dictionary value has presence over its referenced value.
     *
     * @param array|string|null $data
     * @param string $lang
     * @param string|null $dictionary
     * @return string
     */
    public function localize($data, string $lang = null, ?string $dictionary = null): string
    {
        // $result = '';
        // $mainLang = $this->renderer->getMainLanguage();

        // if ($data && is_array($data)) {
        //     if (!$lang) {
        //         $lang = $this->renderer->getLanguage();
        //     }

        //     if (!empty($data[$lang])) {
        //         $result = $data[$lang];
        //     } elseif (isset($data['dictionary'])) {
        //         $result = $this->localizeDictionary($data, $lang);
        //     } else {
        //         $result = $data[$mainLang] ?? '';
        //     }
        // } elseif (is_string($data)) {
        //     $result = $data;
        // }

        // return $result;

        if (is_string($data))
            return $data;

        $result = '';

        if (is_array($data)) {
            if (!$lang)
                $lang = $this->renderer->getLanguage();

            //check inline value
            if (isset($data[$lang])) {
                if (is_string($data[$lang])) {
                    $result = $data[$lang];
                } elseif (
                    is_array($data[$lang]) &&
                    isset($data[$lang]['value'])
                ) {
                    $result = $data[$lang]['value'];
                }
            } elseif (isset($data['$' . $lang])) {
                //check inline reference 
                $referencedVal = $data['$' . $lang];

                $result = $this->getDictionaryData($referencedVal, $lang, $dictionary);
            } elseif (!empty($data['$'])) {
                //check external reference
                $referencedVal = $data['$'];

                $result = $this->getDictionaryData($referencedVal, $lang, $dictionary);
            } else {
                //main lang
                $result = $this->localize($data, 
                    $this->renderer->getMainLanguage(), $dictionary);
            }
        }

        return $result;
    }

    /**
     * Undocumented function
     *
     * @param array|string|int $data
     * @param string $lang
     * @param string|null $dictionary
     * @param bool $fallback
     * @return void
     */
    function getDictionaryData($data, string $lang, 
    ?string $dictionary = null, $fallback)
    {
        $result = '';

        //Dictionary key key for target language
        if (is_numeric($data)) {
            $result = $this->localizeDictionary($data, $lang, $dictionary,
                $fallback);
        } elseif (
            is_array($data) &&
            isset($data['value'])
        ) {
            $result = $data['value'];
        }

        return $result;
    }

    /**
     * Take an HRef parameters and decomposes it into page name, language,
     * parameters and hash part. It then creates an href string with this parts.
     *
     *
     * @param array|string $href
     * @return string
     */
    public function parseHRefParam($href): string
    {
        if (!$href) {
            return '';
        }

        $href = $this->localize($href);

        if (!trim($href)) {
            return '';
        }

        if (strpos($href, '//') !== false || $this->isMailTo($href)) {
            return $href; //external href
        } elseif (strpos($href, '.') !== false) {
            // Don't let user create folder or pages with "."
            return $this->renderer->getAssets()->makeFileAssetUrl($href);
        } else {
            return $this->makePageHRef($href);
        }
    }

    /**
     * Check if string is a link to a mail.
     *
     *
     * @param string $href
     * @return boolean
     */
    public function isMailTo(string $href): bool
    {
        $str = 'mailto:';

        return strpos($href, $str) === 0;
    }

    function stripeEmptyTags($value)
    {
        $re = '~<(\w+)[^>]*>(?>[\p{Z}\p{C}]|<br\b[^>]*>|&(?:(?:nb|thin|zwnb|e[nm])sp|zwnj|#xfeff|#xa0|#160|#65279);|(?R))*</\1>~iu';

        return preg_replace($re, '', $value);
    }

    /**
     * Make the href for internal link.
     *
     *
     * @param string $href
     * @return string
     */
    protected function makePageHRef(string $href): string
    {
        if ($this->renderer->getRenderingType() == $this->renderer::STATIC_RENDERING)
            return $this->makeStaticPageHRef($href);
        else
            return $this->makeDynamicPageHRef($href);
    }

    protected function makeDynamicPageHRef($href)
    {
        $lang = $this->renderer->getLanguage();

        $hashPos = strpos($href, '#');

        if ($hashPos !== false) {
            $params = substr($href, 0, $hashPos);
            $hash = substr($href, $hashPos + 1);
        } else {
            $params = $href;
            $hash = '';
        }

        $params = str_replace('amp;', '&', $params);
        $params = explode('&', $params);
        $pageName = ($params) ? array_shift($params) : '';

        if (!$pageName) {
            $pageName = $this->renderer->pageName();
        }

        // See if the params specify a different language
        if ($params) {
            foreach ($params as $value) {
                $noSpaceValue = str_replace(' ', '', $value);

                if (substr($noSpaceValue, 0, 5) == 'lang=') {
                    $lang = substr($noSpaceValue, 5);
                    break;
                }
            }
        }

        return $this->makeHRef($pageName, $lang, $hash);
    }

    protected function makeStaticPageHRef($href)
    {
        $pages = $this->renderer->getClientFolder() . 'pages';

        $params = '';

        $hashPos = strpos($href, '#');

        if ($hashPos !== false) {
            $params = substr($href, 0, $hashPos);
            $hash = substr($href, $hashPos + 1);
        } else {
            $params = $href;
            $hash = '';
        }

        $params = str_replace('amp;', '&', $params);
        $params = explode('&', $params);

        $lang = $this->renderer->getLanguage();

        if ($params) {
            foreach ($params as $value) {
                $noSpaceValue = str_replace(' ', '', $value);

                if (substr($noSpaceValue, 0, 5) == 'lang=') {
                    $lang = substr($noSpaceValue, 5);
                    break;
                }
            }
        }

        if (file_exists($pages . '/' . $href . '.json')) {
            $pageName = $this->renderer->getPageInfo()->getPageLabel($href);
        } else {
            $pageName = ($params) ? array_shift($params) : '';

            if (!$pageName) {
                $pageName = $this->renderer->pageName();
            }

            $pageName = $this->renderer->getPageInfo()->getPageNameFromURL($pageName);
            $pageName = $this->renderer->getPageInfo()->getPageLabel($pageName, $lang);
        }

        $pageName = $this->renderer->getPageInfo()->replaceFolderLabel($pageName);

        $currentPage = $this->renderer->getRenderPageName();

        $alternatives = $this->renderer->getOption('renderingParams', 'alternatives');

        if (is_array($alternatives) && isset($alternatives[$lang])) {
            $pre = $alternatives[$lang];

            if (substr($pre, -1) != DIRECTORY_SEPARATOR)
                $pre .= DIRECTORY_SEPARATOR;

            $pre = 'https://' . $pre;
        } else {
            $pre = implode('', array_fill(0, substr_count($currentPage, '/'), '../'));
        }

        $href = $pre . "$lang/$pageName.html";
        $href = $hash ? $href . '#' . $hash : $href;

        return $href;
    }

    /**
     * Get the default value of options based on the setting of the parameter.
     *
     *
     * @param array $info Settings of the parameter.
     * @param string $emptyValue if there is no default setting, return this
     * value as default.
     * @return string
     */
    protected function getDropdownDefaultVal(array $info, string $emptyValue): string
    {
        $options = $info['options'] ?? [];
        $defaultVal = $emptyValue;

        foreach ($options as $value) {
            if (!empty($value['defaultOption'])) {
                $val = $value['value'];
                $defaultVal = $val;
                break;
            }
        }

        return $defaultVal;
    }

    /**
     * Get parameters from the data source.
     *
     * @todo Consider collecting several arguments into a single $options
     * argument. It is better for expandability and to name the arguments.
     *
     * @param array $dataSource
     * @param string $type
     * @param array|string $emptyValue
     * @param array $schema
     * @return mixed
     */
    // protected function getParamFromDataSource(
    //     array $dataSource,
    //     string $type,
    //     $emptyValue,
    //     array $schema
    // ) {
    //     $result = $this->getReader()->fetchData($dataSource);

    //     $data = $result['data'] ?? '';

    //     $paramResult = '';

    //     switch ($type) {

    //         case 'media':
    //             $asset = $this->getFirstNonArrayVal($data);
    //             $paramResult = is_string($asset) ?
    //                 $this->renderer->getAssets()->makeFileAssetUrl($asset) : '';
    //             break;
    //         case 'href':
    //             $value = $this->getFirstNonArrayVal($data);
    //             $paramResult = is_string($value) ? $this->renderer
    //                 ->getLocalizer()->parseHRefParam($value) : '';
    //             break;
    //         case 'pages':
    //             $paramResult = $this->parsePagesData($data);
    //             break;
    //         case 'widgets':
    //             $paramResult = $this->parseWidgetsData($data);
    //             break;
    //         case 'table':
    //             if (isset($result['dataSourceSettings'])) {
    //                 $ds = $result['dataSourceSettings'];

    //                 $type = $ds['type'];

    //                 if (is_array($data)) {
    //                     foreach ($data as &$value) {
    //                         foreach ($schema as $key => $schemaVal) {
    //                             if ($key != $schemaVal) {
    //                                 if (isset($value[$schemaVal])) {
    //                                     $value[$key] = $value[$schemaVal];
    //                                 }
    //                             }
    //                         }
    //                     }

    //                     $paramResult = $data;
    //                 } else {
    //                     $paramResult = [];
    //                 }
    //             }
    //             break;
    //         default:
    //             $paramResult = $this->getFirstNonArrayVal($data);
    //     }

    //     return $paramResult;
    // }

    function getReferencedParams($info, $type)
    {
        $data = $this->getReader()->fetch($info);

        $paramResult = '';

        switch ($type) {
            case 'media':
                $asset = $this->getFirstNonArrayVal($data);
                $paramResult = is_string($asset) ?
                    $this->renderer->getAssets()->makeFileAssetUrl($asset) : '';
                break;
            case 'href':
                $value = $this->getFirstNonArrayVal($data);
                $paramResult = is_string($value) ? $this->renderer
                    ->getLocalizer()->parseHRefParam($value) : '';
                break;
            case 'pages':
                $paramResult = $this->parsePagesData($data);
                break;
            case 'widgets':
                $paramResult = $this->parseWidgetsData($data);
                break;
            case 'table':
                $schema = $info['schema'] ?? [];

                if (is_array($data)) {
                    foreach ($data as &$value) {
                        foreach ($schema as $key => $schemaVal) {
                            if ($key != $schemaVal) {
                                if (isset($value[$schemaVal])) {
                                    $value[$key] = $value[$schemaVal];
                                }
                            }
                        }
                    }

                    $paramResult = $data;
                } else {
                    $paramResult = [];
                }
                break;
            default:
                $paramResult = $this->getFirstNonArrayVal($data);
        }

        return $paramResult;
    }

    /**
     * If there is no dictionary or no index in that dictionary, we find base
     * dictionary for users.
     * For example, fr-CA, if there is no dictionary for fr-CA, we find en
     * dictionary; If there is no base dictionary, we find the dictionary with
     * the main language in the website.
     *
     * @param string|int The index of the string
     * in that dictionary
     * @param string $lang language
     * @param string|null $dictionary
     * @param bool $fallback
     * @return mixed
     */
    protected function localizeDictionary($index, string $lang, 
    ?string $dictionary = null, bool $fallback = true)
    {
        $langSettings = $this->renderer->getLangSettings();

        $dictionaryName = $dictionary ?: pathinfo($this->renderer->pageName(), PATHINFO_FILENAME);

        // $dictionaryName = $data['dictionary'] ?? '';

        // $index = $data['index'] ?? -1;

        $preLang = explode('-', $lang)[0];

        if ($dictionaryName) {
            $dictContents = $this->loadDictionary($dictionaryName, $lang);

            //given language
            if (isset($dictContents[$index]['value'])) {
                return $dictContents[$index]['value'];
            }

            if(!$fallback)
                return false;

            //only language part
            if ($preLang != $lang) {
                $dictContents = $this->loadDictionary($dictionaryName, $preLang);

                if (isset($dictContents[$index]['value'])) {
                    return $dictContents[$index]['value'];
                }
            }

            //languages with region
            $languages = $langSettings['languages'];

            foreach ($languages as $value) {
                if (
                    explode('-', $value)[0] == $preLang &&
                    $value != $preLang && $value != $lang
                ) {
                    $dictContents = $this->loadDictionary($dictionaryName, $value);

                    if (isset($dictContents[$index]['value'])) {
                        return $dictContents[$index]['value'];
                    }
                }
            }

            //main language
            $mainLang = $langSettings['mainLang'];
            $dictContents = $this->loadDictionary($dictionaryName, $mainLang);

            if (isset($dictContents[$index]['value'])) {
                return $dictContents[$index]['value'];
            }
        }

        if(!$fallback)
            return false;

        return '';
    }

    /**
     * Load contents from a dictionary.
     *
     * @param string $dictionaryName
     * @param string $lang
     * @return array
     */
    protected function loadDictionary(string $dictionaryName, string $lang): array
    {
        $dict = $dictionaryName . '/' . $lang;

        if (isset($this->glotDicts[$dict])) {
            return $this->glotDicts[$dict];
        }

        if (!$lang || strpos($lang, '.') !== false) {
            throw new \Exception("Invalid language '$lang'");
        }

        $filename = $this->renderer->getClientFolder() . 'dictionaries/' .
            $dictionaryName . '/' . $lang . '.json';

        return $this->glotDicts[$dict] =
            $this->renderer->readJSONFile($filename) ?? [];
    }

    /**
     * Create an href string with different parts.
     *
     * @param string $pageName
     * @param string $lang
     * @param boolean $hashParams
     * @param array $options extras if needed
     * @return string
     */
    protected function makeHRef(
        string $pageName,
        string $lang = '',
        string $hashParams = '',
        array $options = []
    ): string {
        $url = '?';

        // If there is no default website, then we have to add it explicitly
        if (!$this->renderer->hasDefaultWebsite()) {
            $url .= $this->renderer->websiteName() . '/';
        }

        $url .= $pageName;

        if ($lang /*&& $lang != 'en'*/) {
            $url .= '&lang=' . $lang;
        }

        if ($hashParams) {
            $url .= '#' . $hashParams;
        }

        return $url;
    }

    /**
     * Get the first value from the array
     *
     * @param array|string $data
     * @return array|string
     */
    private function getFirstNonArrayVal($data)
    {
        $result = '';
        if (is_array($data)) {
            $values = array_values($data);

            $val = $values[0];

            if (is_array($val)) {
                $result = $this->getFirstNonArrayVal($val);
            } else {
                $result = $val;
            }
        } else {
            $result = $data;
        }

        return $result;
    }

    /**
     * This function parse the data from the page-type data source
     *
     * @param array|null $data
     * @return array
     */
    private function parsePagesData(?array $data = null): array
    {
        $result = [];

        if (is_array($data)) {
            foreach ($data as $value) {
                $label = $value['label'];
                $ele = $value;

                if (isset($value['data'])) {
                    $ele['data'] = $this->parsePagesData($value['data']);
                } else {
                    $ele['href'] =  $this->renderer->getLocalizer()
                        ->parseHRefParam($label);

                    $file = $value['file'];

                    $this->appendPageMeta($ele, $file);
                }

                $result[] = $ele;
            }
        }

        return $result;
    }

    /**
     * This function append meta data of the page to the parsed data
     *
     * @param array $ele
     * @param string $file
     * @return void
     */
    private function appendPageMeta(array &$ele, string $fileName): void
    {
        $contents = $this->renderer->readJSONFile($fileName);

        $title = $contents['params']['pageTitle'] ?? '';

        if ($title) {
            $title = $this->renderer->localize($title);
        }

        $urlName = $contents['params']['urlName'] ?? '';

        if ($urlName) {
            $urlName = $this->renderer->localize($urlName);
        }

        $ele['title'] = $title;
        $ele['urlName'] = $urlName;
    }

    /**
     * This function parse the data from the widget-type data source
     *
     * @param array $data
     * @return void
     */
    private function parseWidgetsData(array $data): array
    {
        $result = [];

        foreach ($data as $page => $widgets) {
            foreach ($widgets as $elem) {
                $id = $elem['id'] ?? false;

                $href = $this->renderer->getLocalizer()
                    ->parseHRefParam(trim($page) . '#' . $id);

                $widgetName = $elem['widgetName'] ?? $elem['id'];

                $ele = [
                    'href' => $href,
                    'widgetName' => $widgetName
                ];

                $result[] = $ele;
            }
        }

        return $result;
    }
}
