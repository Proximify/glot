<?php

/**
 * File for class MasterPageReader.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

/**
 * Manage webpage templates in terms of master pages and super-master pages.
 */
class MasterPageReader extends \Proximify\Glot\Plugin
{
    /**
     * Return the right data if the elements are coming from one of master pages.
     *
     * @param string $template The name of the master page
     * @param array $elements
     * @return array
     */
    public function getMasterData(string $template, array $elements): array
    {
        $pageSettings = $this->renderer->getPageMap();

        $result = $elements;

        if (isset($pageSettings[$template]['path'])) {
            $path = $pageSettings[$template]['path'];

            $name = $path ? $path . '/' . $template . '.json' : $template . '.json';

            //this page is the master page, we use the data from the master page and replace
            //widgets that changed in the client page.
            $fileName = $this->renderer->getClientFolder() . 'pages/' . $name;

            if (file_exists($fileName)) {
                $contents = $this->renderer->readJSONFile($fileName);

                $superTemplate = $contents['params']['_template'] ?? '';

                if ($superTemplate) {
                    $contents = $this->getMasterData($superTemplate, $contents, true);

                    $result['data-supermaster'] = $superTemplate;

                    $this->appendMasterDictionary($contents, $superTemplate);
                }

                if (isset($contents['data'])) {
                    $this->addChangeableToWidgets($contents, 'data');

                    $this->addChangedWidgets($elements, $contents, 'data');
                }

                if (isset($contents['head'])) {
                    $this->addChangeableToWidgets($contents, 'head');
                    $this->addChangedWidgets($elements, $contents, 'head');
                } else {
                    $contents['head'] = [];
                }

                $this->appendMasterDictionary($contents, $template);

                $result['data'] = $contents['data'];
                $result['css'] = $contents['css'] ?? [];
                $result['head'] = $contents['head'];

                unset($result['params']['_template']);

                $result['id'] = $elements['id'];
                $result['path'] = $elements['path'];
            }
        }

        //free memory
        //https://stackoverflow.com/questions/584960/whats-better-at-freeing-memory-with-php-unset-or-var-null
        $elements = null;
        // gc_collect_cycles();

        return $result;
    }

    function appendMasterDictionary(&$contents, $template)
    {
        if (isset($contents['data'])) {
            $this->appendDictionary($contents['data'], $template);
        }

        if (isset($contents['head'])) {
            $this->appendDictionary($contents['head'], $template);
        }
    }

    function appendDictionary(&$data, $dict)
    {
        foreach ($data as &$widget) {
            //widget is coming from template page
            if (empty($widget['data-changedFromSuper']) && isset($widget['params'])) {
                $widget['params']['_dictionary'] = $dict;

                if (!empty($widget['data'])) {
                    $this->appendDictionary($widget['data'], $dict);
                }
            }
        }
    }

    /**
     * Add data-changeable attribute to widgets
     * When users select widget from the website, if the widget is changeable, we
     * will notify users about that
     *
     * @param array $contents
     * @param string $key
     * @return void
     */
    public function addChangeableToWidgets(array &$contents, string $key = 'data')
    {
        if (isset($contents[$key])) {
            $data = &$contents[$key];

            foreach ($data as &$value) {
                if (isset($value['widget'])) {
                    if (!empty($value['sysParams']['changeable'])) {
                        $value['data-changeable'] = true;
                    }

                    $this->addChangeableToWidgets($value, $key);
                }
            }
        }
    }

    /**
     * Replace changeable widgets based on different lock levels.
     * The user can unlock widgets in the master page with 4 levels
     * themes, styling, parameters and inner widgets.
     *
     * @param array $elements
     * @param array $contents
     * @param string $key
     * @return void
     */
    public function addChangedWidgets(array $elements, array &$contents, string $key = 'data')
    {
        $originalData = $elements[$key] ?? [];

        foreach ($originalData as $value) {
            // $widget = $value['widget'];
            $id = $value['id'];

            //check if widget is changeable
            if ($id) {
                $section = $this->getWidgetContentById($contents, $id);

                if (!empty($section['sysParams']['changeable'])) {
                    $unlockParams = !empty($section['sysParams']['unlockParams']) ?
                        $section['sysParams']['unlockParams'] : [];

                    if (empty($unlockParams['styling'])) {
                        //the way to keep master's styles
                        if (isset($section['css'])) {
                            $value['css'] = $section['css'];
                        }

                        if (isset($section['customCode'])) {
                            $value['customCode'] = $section['customCode'];
                        }
                    }

                    if (empty($unlockParams['themes'])) {
                        //the way to keep master's themes
                        if (isset($section['widgetStyles'])) {
                            $value['widgetStyles'] = $section['widgetStyles'];
                        }
                    }

                    //keep master's data and parameters
                    // if (empty($unlockParams['data']) && isset($section['data']))
                    // 	$value['data'] = $section['data'];

                    if (empty($unlockParams['params']) && isset($section['params']))
                        $value['params'] = $section['params'];

                    // if ($fromSuper)
                    $value['data-changedFromSuper'] = true;

                    $this->setWidgetContentById($contents, $id, $value);
                }
            }
        }
    }

    /**
     * This function return the json block with given id in the page
     *
     * @param array $content
     * @param string $id
     * @return array|null
     */
    public function getWidgetContentById(array $content, string $id): ?array
    {
        $result = null;

        if (is_array($content)) {
            if (isset($content['id']) && isset($content['widget']) && $content['id'] == $id) {
                $result = $content;
            } else {
                foreach ($content as $value) {
                    if (is_array($value)) {
                        $result = $this->getWidgetContentById($value, $id);

                        if ($result)
                            break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * This function update the json block with given id in the page
     *
     * @param array $content
     * @param string $id
     * @param array $newContent
     * @return array|null
     */
    public function setWidgetContentById(array &$content, string $id, array $newContent)
    {
        $result = null;

        if (is_array($content)) {
            if (isset($content['id']) && isset($content['widget']) && $content['id'] == $id) {
                if ($newContent !== '')
                    $content = $newContent;

                $result = true;
            } else {
                foreach ($content as $key => &$value) {
                    if (is_array($value)) {
                        $result = $this->setWidgetContentById($value, $id, $newContent);

                        if ($result && isset($value['id']) && $value['id'] == $id) {
                            if ($newContent === '')
                                array_splice($content, $key, 1);

                            break;
                        }
                    }
                }
            }
        }

        return $result;
    }
}
