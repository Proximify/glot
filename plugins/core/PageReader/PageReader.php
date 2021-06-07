<?php

/**
 * File for class glot PageReader.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

use Proximify\Glot\Renderer;
use Proximify\Glot\Widget;

/**
 * Undocumented class
 */
class PageReader extends \Proximify\Glot\Plugin
{
    private $reader;

    /** @var string The directory for all pages */
    private $pageDir;

    /** @var array */
    private static $loadedPages = [];

    /**
     * Get the directory for all pages.
     *
     * @return string
     */
    public function getPageDir(): string
    {
        return $this->pageDir ??
            $this->pageDir = $this->renderer->getClientFolder() . 'pages/';
    }

    /**
     * Read the data of the page by reading the page's json file.
     *
     * @param string $path A path to the JSON file to read.
     * @return array|string|null The non-null data of an existing page file.
     * or null of the page does not exist.
     * @throws JsonException If the page's JSON contents are invalid.
     * @throws Exception If the page contents are null.
     */
    public function readPageData(string $path)
    {
        if (key_exists($path, self::$loadedPages)) {
            return self::$loadedPages[$path];
        }

        $filename = $this->getPageDir() . $path . '.json';

        if (!file_exists($filename)) {
            return null;
        }

        $str = file_get_contents($filename);
        $data = json_decode($str, true, 1024, JSON_THROW_ON_ERROR);

        if ($data === null) {
            throw new \Exception("Invalid page JSON in '$path'");
        }

        return self::$loadedPages[$path] = $data;
    }

    /**
     * Determine if the JSON page exists or not.
     *
     * @param string $path A relative path to a JSON file.
     * @return boolean
     */
    public function pageExists(string $path): bool
    {
        return file_exists($this->getPageDir() . $path . '.json');
    }

    /**
     * Get information of the page
     *
     * @param string|null $pageName
     * @param boolean $isHome
     * @return null|array
     */
    public function getPageInfo(?string $pageName = '', bool $isHome = false): ?array
    {
        $pageName = $pageName ? $pageName : $this->renderer->pageName();

        if ($isHome) {
            $pageName = $this->getHomePage();
        }

        $baseName = pathinfo($pageName, PATHINFO_BASENAME);

        $pageSettings = $this->renderer->getPageMap();

        $result = [];

        if (isset($pageSettings[$baseName])) {
            $result = $pageSettings[$baseName];

            unset($result['SHA1']);

            $fileName = $this->renderer->getClientFolder() . 'pages/' . $pageName . '.json';
            $content = $this->renderer->readJSONFile($fileName);

            $pageWidget = $content['widget'];

            $realClass = str_replace('__', "\\", $pageWidget);

            $localParams = $this->renderer->getLocalizer()->getLocalizedSettings(
                $content['params'],
                null,
                $realClass,
                $content['dataSources'] ?? null
            );

            $result['params'] = $localParams;

            // $label = isset($content['params']['label']) ? $content['params']['label'] : [];

            // $result['params'] = ['label' => $this->renderer->localize($label)];

            $result['href'] = $this->renderer->getLocalizer()
                ->parseHRefParam($baseName);
        }

        return $result;
    }

    /**
     * Get pages structure of the website
     *
     * @param string|null $folder
     * @param string $exclude
     * @param Widget
     * @return array|null
     */
    public function getWebsitePages(
        ?string $folder = '',
        string $exclude = '',
        Widget $component
    ): ?array {
        $searchParams = ['exclude' => $exclude];

        return $this->getReader()->fetchWebsitePages(
            ['path' => $folder],
            $this->renderer->getClientFolder(),
            $component,
            $searchParams
        );
    }

    /**
     * Get pages and widgets structure of the website
     *
     * @param array $params
     * @return array|null
     */
    public function getWebsiteWidgets(?array $params): ?array
    {
        return $this->getReader()->fetchWebsiteWidgets($params, $this->renderer->getClientFolder());
    }

    /**
     * Get all the valid names for the current webpage. That is, sometimes
     * a page might have several alternate names (in different languages)
     *
     * @return array List of names.
     */
    public function getAllPageNames()
    {
        $pageName = $this->renderer->pageName();
        $fileName = $this->getPageNameFromURL($pageName);

        $pageSettings = $this->renderer->getPageMap();

        $pagePath = [$pageName];

        if (isset($pageSettings[$fileName])) {
            $label = $pageSettings[$fileName]['label'];
            $pagePath[] = $label;

            // Get the multilingual URL name (array with lang keys)
            $urlName = $pageSettings[$fileName]['urlName'];

            $pagePath = array_merge($pagePath, $urlName);
        }

        return $pagePath;
    }

    /**
     * Returns the information of the target page
     *
     * @param string $pageName
     * @param boolean $isHome
     * @return array
     */
    public function getInfo(string $pageName = '', bool $isHome = false): array
    {
        $pageName = $pageName ?: $this->renderer->pageName();

        if ($isHome) {
            $pageName = $this->getHomePage();
        }

        $baseName = pathinfo($pageName, PATHINFO_BASENAME);

        $pageSettings = $this->renderer->getPageMap();

        $result = [];

        if (isset($pageSettings[$baseName])) {
            $result = $pageSettings[$baseName];

            unset($result['SHA1']);

            $fileName = $this->renderer->getClientFolder() . 'pages/' . $pageName . '.json';
            $content = $this->renderer->readJSONFile($fileName);

            $label = $content['params']['label'] ?? [];

            $result['params'] = ['label' => $this->renderer->localize($label)];

            $result['href'] = $this->renderer->getLocalizer()->parseHRefParam($baseName);
        }

        return $result;
    }

    /**
     * This function returns the information of the next folder of the target page.
     *
     * @param string|null $pageName
     * @param Widget $component
     * @return array
     */
    public function getNextFolder(?string $pageName = '', Widget $component): array
    {
        $pageName = $pageName ?: $this->renderer->pageName();
        $baseName = pathinfo($pageName, PATHINFO_BASENAME);

        $pageSettings = $this->renderer->getPageMap();

        $result = [];
        $found = false;

        if (isset($pageSettings[$baseName])) {
            $path = $pageSettings[$baseName]['path'];

            $baseFolder = pathinfo($path, PATHINFO_BASENAME);

            if (isset($pageSettings[$baseFolder])) {
                $folderPath = $pageSettings[$baseFolder]['path'];

                foreach ($pageSettings as $key => $value) {
                    if (isset($value['isFolder']) && empty($value['invisible'])) {
                        if ($key == $baseFolder) {
                            $found = true;
                        }

                        $itemPath = $value['path'];

                        if ($itemPath == $folderPath && $found && $key != $baseFolder) {
                            $result = $value;
                            $result['folder'] = $key;
                            $result['data'] = $this->getWebsitePages($itemPath . '/' . $key, '', $component);
                            break;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * This function returns the information of the next page of the target page.
     *
     * @param string $pageName
     * @return array
     */
    public function getNextPage(string $pageName = ''): array
    {
        $pageName = $pageName ?: $this->renderer->pageName();
        $baseName = pathinfo($pageName, PATHINFO_BASENAME);

        $pageSettings = $this->renderer->getPageMap();

        $result = [];
        $found = false;

        if (isset($pageSettings[$baseName])) {
            $path = $pageSettings[$baseName]['path'];

            foreach ($pageSettings as $key => $value) {
                if (isset($value['isFolder'])) {
                    continue;
                }

                if ($key == $baseName) {
                    $found = true;
                }

                $itemPath = $value['path'];
                if ($itemPath == $path && $found && $key != $baseName) {
                    $result = $value;
                    unset($result['SHA1']);

                    $fileName = $this->renderer->getClientFolder() . 'pages/' . $path . '/' . $key . '.json';
                    $content = $this->renderer->readJSONFile($fileName);

                    $label = $content['params']['label'] ?? [];
                    $result['params'] = ['label' => $this->renderer->localize($label)];

                    $result['href'] = $this->renderer->getLocalizer()
                        ->parseHRefParam($key);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * This function returns the information of the previous page of the target page.
     *
     * @param string $pageName
     * @return array
     */
    public function getPrevPage(string $pageName = '', $folderPath = ''): array
    {
        $pageName = $pageName ?: $this->renderer->pageName();
        $baseName = pathinfo($pageName, PATHINFO_BASENAME);

        $pageSettings = $this->renderer->getPageMap();

        $result = [];

        if (isset($pageSettings[$baseName]) || $folderPath) {
            if ($folderPath) {
                $p = explode('/', $folderPath);
                array_pop($p);
                $path = implode('/', $p);
            } else {
                $path = $pageSettings[$baseName]['path'];
            }

            foreach ($pageSettings as $key => $value) {
                $itemPath = $value['path'];

                if ($folderPath) {
                    if (isset($value['isFolder'])) {
                        if ($itemPath . "/$key" == $folderPath) {
                            return $result;
                        } else {
                            continue;
                        }
                    }
                } else {
                    if (isset($value['isFolder'])) {
                        continue;
                    }

                    if ($key == $baseName) {
                        return $result;
                    }
                }

                if ($itemPath == $path) {
                    $result = $value;
                    unset($result['SHA1']);

                    $fileName = $this->renderer->getClientFolder() . 'pages/' .
                        $path . '/' . $key . '.json';

                    $content = $this->renderer->readJSONFile($fileName);

                    $label = $content['params']['label'] ?? [];
                    $result['params'] = ['label' => $this->renderer->localize($label)];

                    $result['href'] = $this->renderer->getLocalizer()->parseHRefParam($key);
                    $result['name'] = $key;
                }
            }
        }

        return $result;
    }

    /**
     * This function returns the name of the home page which is set in the web
     * service file.
     *
     * @return string|null
     */
    public function getHomePage(): string
    {
        $key = Renderer::WEB_SERVICES;

        return $this->renderer->loadClientSettings($key)['homepage'] ?? 'home';
    }

    /**
     * For dynamic website, the url can be multilingual or the url
     * includes page label.
     * This function returns the real page name from the url
     *
     * @param string $alias The page name from the url
     * @return string|null
     */
    public function getPageNameFromURL(string $alias): ?string
    {
        $map = $this->renderer->getPageMap();

        // We don't use home page as the default name because
        // another page maybe can handle the request
        $pageName = $alias; //$this->getHomePage();

        $pieces = explode('/', $alias);
        $alias = end($pieces);

        if (!is_array($map)) {
            return $alias;
        }

        if (isset($map[$alias])) {
            $path = $map[$alias]['path'];
            $pageName = $path ? $path . '/' . $alias : $alias;
        } else {
            foreach ($map as $filename => $fileAttr) {
                if (isset($fileAttr['isFolder']) && $fileAttr['isFolder']) {
                    continue;
                }

                if (!isset($fileAttr['path']) || !isset($fileAttr['label'])) {
                    continue;
                }

                $path = $fileAttr['path'];

                if ($fileAttr['label'] == $alias) {
                    $pageName = $path ? $path . '/' . $filename : $filename;

                    break;
                } else {
                    $urlName = $fileAttr['urlName'] ?? false;

                    if (!$urlName || !is_array($urlName)) {
                        continue;
                    }

                    foreach ($urlName as $lang => $value) {
                        if ($value == $alias) {
                            $pageName = $path ? $path . '/' . $filename : $filename;
                            break 2;
                        }
                    }
                }
            }
        }

        return $pageName;
    }

    /**
     * Returns the real page name based on given parameter
     * The parameter can be the label of the page but the real page name is
     * different.
     *
     * @return string the name of the page
     */
    public function getPageName($page): string
    {
        $pages = $this->renderer->getClientFolder() . 'pages';

        if (file_exists($pages . '/' . $page . '.json')) {
            $pageName = $page;
        } else {
            $pageName = $this->getPageNameFromURL($page);
        }

        return $pageName;
    }

    /**
     * Return label of the page
     * When we render the page in static way,
     * we want to generate pages with readable name.
     *
     * @param string $alias
     * @param string $lang
     * @return string
     */
    public function getPageLabel(string $alias, string $lang = ''): string
    {
        $lang = $lang ?: $this->renderer->getLanguage();

        $map = $this->renderer->getPageMap();
        $pageName = '';

        $pieces = explode('/', $alias);
        $alias = end($pieces);

        if (is_array($map) && $alias) {
            if (isset($map[$alias])) {
                $path = $map[$alias]['path'];

                $label = $map[$alias]['label'] ?? '';

                if ($lang && !empty($map[$alias]['urlName'][$lang])) {
                    $label = $map[$alias]['urlName'][$lang];
                }

                $pageName = $path ? $path . '/' . $label : $label;
            } else {
                foreach ($map as $filename => $fileAttr) {
                    $path = $fileAttr['path'];

                    $label = $fileAttr['label'];

                    if ($label == $alias) {
                        if ($lang && !empty($fileAttr['urlName'][$lang])) {
                            $label = $fileAttr['urlName'][$lang];
                        }

                        $pageName = $path ? $path . '/' . $label : $label;

                        break;
                    } else {
                        if (isset($fileAttr['urlName'])) {
                            foreach ($fileAttr['urlName'] as $langs => $value) {
                                if ($value == $alias) {
                                    if ($lang && !empty($fileAttr['urlName'][$lang])) {
                                        $label = $fileAttr['urlName'][$lang];
                                    }

                                    $pageName = $path ? $path . '/' . $label : $label;

                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $pageName;
    }

    /**
     * Get a readable directory from the path of the page
     * For static website, we want to use readable names one pages and
     * folders.
     *
     * @param string $pageName
     * @return string
     */
    public function replaceFolderLabel(string $pageName): string
    {
        $map = $this->renderer->getPageMap();

        $pieces = explode('/', $pageName);
        $end = array_pop($pieces);

        if (count($pieces)) {
            $result = '';

            foreach ($pieces as $value) {
                $label = $map[$value]['label'] ?? $value;

                $label = $this->normalizePageName($label);

                $result .= $label . '/';
            }

            $result .= $end;
            return $result;
        } else {
            return $pageName;
        }
    }

    /**
     * Normalize the page name
     *
     * @param string $name
     * @return string
     */
    public function normalizePageName(string $name): string
    {
        // The order of the replacements is important!
        $needles = [' / ',  '/', ' ', '&', '@'];

        $name = str_replace($needles, '_', strtolower(trim($name)));

        return str_replace('?', '', $name);
    }

    protected function getReader(): DataSourceReader
    {
        return $this->reader ??
            $this->reader = $this->renderer->getDataSourceReader();
    }
}
