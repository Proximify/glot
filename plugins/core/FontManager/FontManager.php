<?php

/**
 * File for class FontManager.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

/**
 * Manager of website fonts.
 */
class FontManager extends \Proximify\Glot\Plugin
{
    /** @var array $fonts website fonts */
    private $fonts = [];

    /** @var array $widgetFonts fonts come from widget packages */
    private $widgetFonts = [];

    /**
     * Collect website font.
     *
     * @param string $font
     * @return void
     */
    public function collectWebsiteFont(string $font)
    {
        $this->fonts[] = $font;
    }

    public function activateFont($font, $class)
    {
        if ($font) {
            if ($this->isWebsiteFont($font)) {
                // $this->renderer->collectWebsiteFont($font);
                $this->fonts[] = $font;
            } else {
                $widgetFonts = $this->widgetFonts; //$this->renderer->getWidgetFonts();

                if (!isset($widgetFonts[$class][$font])) {
                    $rootURL = $this->renderer->getClientRootUrl();

                    $stdName = str_replace(['__', "\\"], '/', $class);

                    $p = $this->parseName($stdName, '/');

                    $stdName = count($p) == 1 ? "_global/$stdName" : $stdName;

                    $path = $this->renderer->getClientFolder() . 'widgets/' . $stdName;

                    $folder = $path . '/fonts/' . $font;

                    if (is_dir($folder)) {
                        $root = $rootURL . 'widgets/' . $stdName . '/fonts/' . $font . '/';

                        // $fontManager = new FontManager($folder, $root);

                        $fontFace = $this->generateFontFace($font, $folder, $root);

                        if ($fontFace) {
                            $this->widgetFonts[$class][$font] = $fontFace;
                        }
                        // $this->renderer->collectWidgetFont($font, $class, $fontFace);
                    }
                }
            }
        }
    }

    public function generateFontFaces(): array
    {
        $fonts = array_unique($this->fonts);

        $settings = $this->renderer->getFontsSettings();

        $availableFonts = $settings['fonts'] ?? [];

        $result = [];
        $preloads = [];

        foreach ($fonts as $font) {
            foreach ($availableFonts as $key => $value) {
                $name = $value['name'];
                $isDefault = $value['default'] ?? false;

                $version = $value['version'] ?? false;

                if ($name == $font && !$isDefault) {
                    $styles = $value['styles'];

                    $result[] = $this->addFontFace($key, $name, $styles, $version);
                    $preloads = array_merge($preloads, $this->addFontPreload($key, $name, $styles));
                }
            }
        }

        return ['fonts' => $result, 'preloads' => $preloads];
    }

    function addFontPreload($fontId, $name, $styles)
    {
        $result = [];

        $srcDir = $this->renderer->getClientRootUrl();

        foreach ($styles as $key => $value) {
            foreach ($value as $type => $src) {
                $dir = $srcDir . "fonts/$fontId/" . $src;

                $result[] = "<link rel='preload' href='$dir' as='font' crossorigin='anonymous' />";
            }
        }

        return $result;
    }

    /**
     * This function generate style tags which include font faces
     *
     * @param string $fontId The id of the font.
     * @param string $name The name of the font which is also used as font-family name
     * @param array $styles The array of font information (weight, italic)
     * @param string $version The version number of the font, we add a version number to avoid cache issue
     * @return string
     */
    public function addFontFace(string $fontId, string $name, array $styles, string $version): string
    {
        if ($this->renderer->isStaticRendering()) {
            $str = $this->addFontFaceFromStyles($fontId, $name, $styles, $version);
        } else {
            $str = "<style fontId='$fontId'>\n";

            $str .= $this->addFontFaceFromStyles($fontId, $name, $styles, $version);

            $str .= '</style>';
        }


        return $str;
    }

    /**
     * Generate font faces
     *
     * @param string $fontId
     * @param string $name
     * @param array $styles
     * @param string $version
     * @return string
     */
    public function addFontFaceFromStyles(string $fontId, string $name, array $styles, string $version): string
    {
        $str = '';

        $versionSubFix = $version ? "?v=$version" : '';

        //for exporting mode, we will put css file into styles/pages folder
        $srcDir = $this->renderer->isStaticRendering() ? '../../' :
            $this->renderer->getClientRootUrl();

        foreach ($styles as $key => $value) {
            $str .= "@font-face {\n font-family:'$name';\n";

            $p = $this->parseName($key);
            $weight = $this->getWeightValue($p[0]);
            $fontStyle = $p[1];
            $fontStyle = strtolower($fontStyle) == 'italic' ? 'italic' : 'normal';

            $fallback = 'src:';

            foreach ($value as $type => $src) {
                $dir = $srcDir . "fonts/$fontId/" . $src;

                switch ($type) {
                    case 'eot':
                        $str .= "src:url('$dir') format('eot');";
                        $fallback .= "url('$dir$versionSubFix#iefix') format('eot');";
                        break;
                    case 'woff':
                        $fallback .= "url('$dir$versionSubFix') format('woff');";
                        break;
                    case 'woff2':
                        $fallback .= "url('$dir$versionSubFix') format('woff2');";
                        break;
                    case 'ttf':
                        $fix = "url('$dir$versionSubFix#iefix') format('embedded-opentype')";
                        $fallback .= "$fix, url('$dir$versionSubFix') format('truetype');";
                        break;
                    case 'svg':
                        $fallback .= "url('$dir#$name $versionSubFix') format('svg');";
                        break;
                    case 'otf':
                        $fallback .= "url('$dir$versionSubFix') format('opentype');";
                        break;
                    default:;
                }
            }

            $str .= $fallback . "\n" .
                "font-weight:$weight;\n" .
                "font-style:$fontStyle;" .
                "font-display: swap;" .
                "\n}\n";
        }

        return $str;
    }

    /**
     * This function convert text weight to number weight
     * Prevent the wrong weight caused by upper case letters.
     *
     * @param string $weight
     * @return string
     */
    public function getWeightValue(string $weight): string
    {
        $result = 'normal';

        switch (strtolower($weight)) {
            case 'thin':
                $result = 100;
                break;
            case 'extralight':
                $result = 200;
                break;
            case 'light':
                $result = 300;
                break;
            case 'normal':
                $result = 400;
                break;
            case 'medium':
                $result = 500;
                break;
            case 'semibold':
                $result = 600;
                break;
            case 'bold':
                $result = 700;
                break;
            case 'ExtraBold':
                $result = 800;
                break;
            case 'Black':
                $result = 900;
                break;
            default:
                $result = 400;
        }

        return $result;
    }

    /**
     * This function generate style tags for widget fonts.
     *
     * @return string
     */
    public function generateWidgetFonts(): string
    {
        $result = '';

        if (count($this->widgetFonts)) {
            foreach ($this->widgetFonts as $fonts) {
                foreach ($fonts as $value) {
                    $result .= $value;
                }
            }
        }

        return $result ? '<style class="widgetFonts">' . $result . '</style>' : '';
    }

    /**
     * Generate a font face.
     *
     * @package FontManager
     *
     * @param string $font
     * @return void
     */
    public function generateFontFace($font, $fontFolder, $rootURL)
    {
        // $fontFolder = $this->getRootFolder();

        $str = '';

        if (is_dir($fontFolder)) {
            $availableExt = ['eot', 'otf', 'ttf', 'woff2', 'woff', 'svg'];

            $contents = $this->renderer->getDirContents($fontFolder);

            foreach ($contents as $value) {
                $filename = pathinfo($value, PATHINFO_FILENAME);

                $ext = pathinfo($value, PATHINFO_EXTENSION);

                //it is not a font file;
                if (!in_array($ext, $availableExt)) {
                    continue;
                }

                $fontName = $this->parseName($filename)[0];

                //it doesn't belong to this font group;
                if ($fontName != $font) {
                    continue;
                }

                $weight = $this->getFontWeight($filename);
                $style = $this->getFontStyle($filename);

                $this->appendFontFace($str, $fontName, $weight, $style, $ext, $rootURL . $value);
            }
        }

        return $str;
    }

    public function parseName(string $name, string $delimiter = '-')
    {
        return explode($delimiter, $name);
    }

    public function getFontWeight($name)
    {
        $p = $this->parseName($name);

        $result = count($p) > 1 ? $p[1] : 'normal';

        switch (strtolower($result)) {
            case 'thin':
            case 'Thin':
                $result = 100;
                break;
            case 'extralight':
            case 'ExtraLight':
                $result = 200;
                break;
            case 'light':
            case 'Light':
                $result = 300;
                break;
            case 'normal':
            case 'Normal':
            case 'regular':
            case 'Regular':
                $result = 400;
                break;
            case 'medium':
            case 'Medium':
                $result = 500;
                break;
            case 'semibold':
            case 'SemiBold':
                $result = 600;
                break;
            case 'bold':
            case 'Bold':
                $result = 700;
                break;
            case 'ExtraBold':
            case 'extrabold':
                $result = 800;
                break;
            case 'Black':
            case 'black':
                $result = 900;
                break;
            default:
                $result = 400;
        }

        return $result;
    }

    public function getFontStyle($name)
    {
        $p = $this->parseName($name);

        $result = count($p) > 2 ? $p[2] : 'normal';

        $result = strtolower($result) == 'italic' ? 'italic' : 'normal';

        return $result;
    }

    public function appendFontFace(&$str, $name, $weight, $fontStyle, $type, $file)
    {
        $str .= "@font-face {\n font-family:'$name';\n";

        $fallback = 'src:';

        switch ($type) {
            case 'eot':
                $str .= "src:url('$file') format('eot');";
                $fallback .= "url('$file#iefix') format('embedded-opentype');";
                break;
            case 'woff':
                $fallback .= "url('$file') format('woff');";
                break;
            case 'woff2':
                $fallback .= "url('$file') format('woff2');";
                break;
            case 'ttf':
                $fallback .= "url('$file') format('truetype');";
                break;
            case 'svg':
                $fallback .= "url('$file#$name') format('svg');";
                break;
            case 'otf':
                $fallback .= "url('$file') format('opentype');";
                break;
            default:;
        }

        $str .= $fallback . "\n" .
            "font-weight:$weight;\n" .
            "font-style:$fontStyle;" .
            "font-display: swap;" .
            "\n}\n";
    }

    /**
     * This function checks if the given font is a font in the website.
     * To enable font, we have to include right font files. If we cannot find
     * the font in the website, the font should in the widget package.
     *
     * @param string $font The name of the font
     * @return boolean
     */
    private function isWebsiteFont(string $font): bool
    {
        $settings = $this->renderer->getFontsSettings();

        $availableFonts = $settings['fonts'] ?? [];

        foreach ($availableFonts as $value) {
            if ($font == $value['name']) {
                return true;
            }
        }

        return false;
    }
}
