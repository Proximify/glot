<?php

/**
 * File for class PagePrerendering.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

require_once('PageDOM.php');

/**
 * Undocumented class
 */
class PagePreRenderer extends \Proximify\Glot\Plugin
{
    private $cache = [];

    /**
     * Undocumented function
     *
     * @param string $page
     * @return void
     */
    public function makeDOM(string $page)
    {
        // Save status of internal error reporting
        $internalErrors = libxml_use_internal_errors(true);

        // $pageDom = $this->renderer->require('Core\\PageDOM');
        $pageDom = new PageDOM();
        $pageDom->loadHTML($page);

        // Restore status of internal error reporting
        libxml_use_internal_errors($internalErrors);

        return $pageDom;
    }

    /**
     * Get the prerendering of a page.
     * When widget renders the markup, it can get the markup of the whole page.
     *
     * @param null|string $pageName
     * @param null|string $lang
     * @param boolean $dom Whether to return a DOM or an HTML string.
     * @return PageDOM|string|null A PageDOM object or just string of page content
     */
    public function getPrerendering(?string $pageName = '', ?string $lang = '', bool $dom = true)
    {
        // If the cache is empty it means that the current call
        // is the end of recursive call and should not pre render
        if (!isset($this->cache[$pageName][$lang])) {
            $this->preRender($pageName, $lang);
        }

        $cache = &$this->cache[$pageName][$lang];

        // Build the DOM only if requested
        if ($cache && $dom && !$cache['dom']) {
            $cache['dom'] = $this->makeDOM($cache['html']);
        }

        return $cache ? $cache[$dom ? 'dom' : 'html'] : null;
    }

    /**
     * Undocumented function
     *
     * @param string $class
     * @param string|null $page
     * @return array
     */
    public function getElementsFromPageByClass(string $class, ?string $page = null): array
    {
        $dom = $this->getPrerendering($page);
        $elements = [];

        $result = [];

        if ($dom) {
            $elements = $dom->getElementsByClass($class);

            foreach ($elements as $node) {
                $ele = [];

                foreach ($node->attributes as $attr) {
                    $ele[$attr->nodeName] = $attr->nodeValue;
                }

                $ele['textContent'] = $node->textContent;
                $result[] = $ele;
            }
        }

        return $result;
    }

    public function getElementsFromPageByTag($tag, $page = '')
    {
        $dom = $this->getPrerendering($page);

        $elements = [];
        $result = [];

        if ($dom) {
            $elements = $dom->getElementsByTagName($tag);

            foreach ($elements as $node) {
                $ele = [];

                foreach ($node->attributes as $attr) {
                    $ele[$attr->nodeName] = $attr->nodeValue;
                }

                $ele['textContent'] = $node->textContent;
                $result[] = $ele;
            }
        }

        return $result;
    }

    final public function getElementFromPageById($id, $page = '')
    {
        $dom = $this->getPrerendering($page);

        $ele = [];

        if ($dom) {
            $node = $dom->getElementById($id);

            foreach ($node->attributes as $attr) {
                $ele[$attr->nodeName] = $attr->nodeValue;
            }

            $ele['textContent'] = $node->textContent;
        }

        return $ele;
    }

    /**
     * Undocumented function
     *
     * @param string $pageName
     * @param string $lang
     * @return void Saves the page HTMl in the cache.
     */
    private function preRender(string $pageName, string $lang)
    {
        /**
         * enablePreRender: Disable the prerendering in the child website to avoid
         *    deep recursions. Also keep the 'export' mode or any other mode.
         * lang: When we create search data in publishing mode, we have to create search data for
         *    all languages. The function preRender before is based on the current language. With setLang,
         *    we can preRender the page with different language.
         * domain: domain is used for creating search data. When we publish the website,
         *    we are trying to find the image in meta tags of the page. If we find the
         *    image, we need to save the full path include domain name in the search data.
         */
        // $options = [
        //     'mode' => $this->renderer->getRenderMode(),
        //     'domain' => $this->renderer->getDomain(),
        //     'lang' => $lang
        // ];

        // $className = get_class($this->renderer);
        // $website = new $className($options);

        // Get a glot of the website. The clone starts with empty code.
        $website = clone $this->renderer;

        $params = $this->renderer->getPageParams();
        $websiteName = $this->renderer->websiteName();

        // Perform a shallow copy of all of the object's properties and
        // call the __clone() method of the website renderer if defined.
        // $website = clone $this->renderer;

        // @todo Explain why this is done where there is a page name
        //page name might come from $_GET parameters and it can be the label of
        // the page which is not the real file name.
        //keep the original page name as the cache name;
        $cacheName = $pageName ?: '';

        if ($pageName) {
            $pageName = $this->renderer->getPageInfo()->getPageName($pageName);
            $params = [
                // $websiteName . '/' . $pageName => '',
                'draft' => $params['draft'] ?? ''
            ];
        }

        // Initialize the cache here to avoid re-rendering in
        // recursive calls. ie, the cache will exist and be empty.
        if (!isset($this->cache[$cacheName])) {
            $this->cache[$cacheName] = [];
        }

        // If prerenderings are disabled, we are done.
        if (!$this->renderer->canPreRender()) {
            return;
        }

        $params['lang'] = $lang;

        // $website->initPagePath($path);

        // Save the parent website (should be $this)
        // $parentWebsite = Component::getParentWebsite();

        // The building of HTML makes the global website equal to $website
        // which needs to be fixed after we are done prerendering]
        $page = $website->renderPage($params, $websiteName, $pageName);

        // We are done with the child website. There are two pointers
        // to it, $website and the static parent website in Component
        unset($website); // unsets first pointer to child website

        // Restore the parent website (unsets second pointer to child website)
        // Component::setParentWebsite($parentWebsite);

        $page = mb_convert_encoding($page, 'HTML-ENTITIES', 'UTF-8');

        $this->cache[$cacheName][$lang] = ['html' => $page, 'dom' => false];
    }
}
