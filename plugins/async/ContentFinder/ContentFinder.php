<?php

/**
 * File for plugin class ContentFinder.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Async;

use \Proximify\Glot\Event;

/**
 * Interface-like class for all implementations of website search plugins.
 *
 * By default, this plugin implements basic search functionality that can
 * be used by a widget to render a search input box.
 */
class ContentFinder extends \Proximify\Glot\Plugin
{
    /**
     * @var array Request urls for fetching search data, collected from
     * the function enableWebsiteSearch
     * */
    public static $enabledSearch = [];

    /**
     * Enable the website search by activating the indexing of website content.
     *
     * name: base file name, by default is 'search'
     * compression: compression method, by default is 'nozip'
     * index: indexing method, by default is 'noind'
     * includeDes: add contents of pdf files as searchable content
     * assetAsPage: show assets as search result.
     *
     * @param array $options The search options are plugin dependent.
     * @return string|null The URL to use to get search results or null if
     * the functionality is not available.
     */
    public function enableWebsiteSearch(array $options = []): ?string
    {
        $params = [
            'name' => 'search',
            'compression' => 'nozip',
            'index' => 'noind',
            'method' => 'html',
            'includeDocs' => false,
            'assetAsPage' => false
        ];

        foreach ($options as $key => $value) {
            if (isset($params[$key])) {
                $params[$key] = $value;
            }
        }

        if ($this->renderer->getRenderingType() == $this->renderer::STATIC_RENDERING) {
            $searchId = implode('_', $params);

            /**
             * ExportSearchFiles function will generate files for all pages.
             * We only register the event listener once if it's coming from the
             * same search widget or different search widget with same search
             * parameters.
             * 
             */
            if (empty(self::$enabledSearch[$searchId])) {
                $this->renderer->addSiteExportListener(function (Event $event) {
                    $this->exportSearchFiles($event);
                });

                self::$enabledSearch[$searchId] = $params;
            }

            $lang = $this->renderer->getLanguage();

            $pages = $this->renderer->getClientFolder() . 'pages';

            $pageName = $this->renderer->pageName();

            if (file_exists($pages . '/' . $pageName . '.json')) {
                $pageName = $this->renderer->getPageInfo()->getPageLabel($pageName);
            } else {
                $pageName = $this->renderer->getPageInfo()->getPageNameFromURL($pageName);
                $pageName = $this->renderer->getPageInfo()->getPageLabel($pageName);
            }

            $pageName = $this->renderer->getPageInfo()->replaceFolderLabel($pageName);

            $currentPage = $this->renderer->pageName();

            $pre = implode('', array_fill(0, substr_count($currentPage, '/'), '../'));

            return $pre . "/search/" . implode('_', $params) . "_$lang.json";
        } else {
            $class = get_class();
            $class = substr($class, strrpos($class, '\\Async\\') + 1);

            return $this->renderer->getClientApiUrl($class, $params);
        }
    }

    /**
     * Handle search requests.
     *
     * @param array $params The request options are plugin dependent.
     * @return string A JSON string with the search results.
     */
    public function handleRequest(array $params): string
    {
        return json_encode($this->getHTMLData($params));
    }

    public function exportSearchFiles(Event $event)
    {
        $params = $event->getParams();

        $folder = $params['folder'] ?? '';
        $request = $params['request'] ?? [];

        if (count(self::$enabledSearch)) {
            $targetFolder = $folder . '/search';

            if (!is_dir($targetFolder)) {
                if (!mkdir($targetFolder, 0777, true)) {
                    throw new \Exception("Cannot create folder '$targetFolder'");
                }

                chmod($targetFolder, 0777);
            }
        }

        $website = $request['website'] ?? '';
        $domain = $request['domain'] ?? '';

        $langSettings = $this->renderer->getLangSettings();
        $langs = $langSettings['languages'];

        foreach ($langs as $lang) {
            foreach (self::$enabledSearch as $name => $params) {
                $params['lang'] = $lang;
                $params['website'] = $website;
                $params['domain'] = $domain;

                $data = $this->getHTMLData($params);

                $file = $targetFolder . '/' . $name . "_$lang.json";
                file_put_contents($file, json_encode($data, true));
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param array $params
     * @return array
     */
    protected function getHTMLData(array $params): array
    {
        $folder = $this->renderer->getWebsiteFolder();

        $settingFile = $folder . 'settings/glot_pageSettings.json';
        $settings = $this->renderer->readJSONFile($settingFile);

        $result = $this->getHTMLPageData(
            $folder . 'pages/',
            $params,
            $settings,
            ''
        );

        return $result;
    }

    /**
     * Undocumented function
     *
     * @param string $folder
     * @param array $params
     * @param array $settings
     * @param string $path
     * @return array
     */
    protected function getHTMLPageData(
        string $folder,
        array $params,
        array $settings,
        string $path = ''
    ): array {
        $result = [];

        // Note that dot-files are excluded by default.
        $contents = $this->renderer->getDirContents($folder, ['_masters']);

        foreach ($contents as $value) {
            $target = $folder . $value;

            if (is_dir($target)) {
                $info = $settings[$value] ?? [];

                if (isset($info['invisible']) && $info['invisible']) {
                    continue;
                }

                $folderLabel = $this->getPageFolderLabel($settings, $value);

                $newPath = $path ? $path . '/' . $folderLabel : $folderLabel;

                $result = array_merge($result, $this->getHTMLPageData(
                    $target . '/',
                    $params,
                    $settings,
                    $newPath
                ));
            } else {
                $basename = pathinfo($value, PATHINFO_FILENAME);

                $info = $settings[$basename] ?? [];

                if (isset($info['invisible']) && $info['invisible']) {
                    continue;
                }

                $pageLabel = $this->getPageFolderLabel($settings, $basename);

                $info['label'] = $pageLabel;
                $info['path'] = $path;
                $info['page'] = $value;

                $lang = !empty($params['lang']) ? $params['lang'] : '';

                // $options = [];

                // if ($lang) {
                //     $options['lang'] = $lang;
                // }

                $dom = $this->renderer->preRenderPage(
                    pathinfo($value, PATHINFO_FILENAME),
                    $lang,
                    false
                );

                $info['content'] = $dom;

                unset($info['SHA1']);
                unset($info['urlName']);

                $this->renderer->setActiveLang($lang);

                $info['href'] = $this->renderer->getLocalizer()
                    ->parseHRefParam(pathinfo($value, PATHINFO_FILENAME));

                $info['docs'] = '';

                $result[] = $info;
            }
        }

        return $result;
    }

    /**
     * Undocumented function
     *
     * @param array $settings
     * @param string $name
     * @return string
     */
    protected function getPageFolderLabel(array $settings, string $name): string
    {
        $info = $settings[$name] ?? [];

        $path = $info['path'] ?? '';
        $label = $info['label'] ?? '';

        $result = '';

        if ($path) {
            $basename = pathinfo($path, PATHINFO_BASENAME);
            $folderLabel = $this->getPageFolderLabel($settings, $basename);
            $result .= $result ? $result . '/' . $folderLabel : $folderLabel;
        }

        if ($result) {
            $result .= '/' . $label;
        } else {
            $result = $label;
        }

        return $result;
    }
}
