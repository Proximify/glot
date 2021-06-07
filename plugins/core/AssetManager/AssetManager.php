<?php

/**
 * File for class glot asset info.
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
class AssetManager extends \Proximify\Glot\Plugin
{
    const SIZE = 512000; //500kb
    private const RESOURCE_SETTINGS = 'glot_resourceSettings';

    protected $isInit;

    /**
     * Get the image size for target asset
     *
     * @param string $relPath the path of the asset
     * @param string $folder
     * @return void
     */
    public function getImageSize(string $assetPath)
    {
        $path = $this->getAssetPath($assetPath);

        return $path ? @getimagesize($path) : null;
    }

    /**
     * This function returns the url of the given asset.
     * Try to find the asset from the website first.
     * If the asset can't be found from the website, then
     * fetch the asset from the widget package.
     *
     * @param string $targetResource the name of asset
     * @param string $widget the name of widget
     * @return string
     */
    public function makeAssetUrl(string $asset, string $widgetClass): string
    {
        if (!$asset) {
            return '';
        }

        $this->initAssetsUrl();

        $preFolder = $this->renderer->getClientRootUrl() . 'assets/';

        if (strpos($asset, $preFolder) !== false) {
            return $asset;
        } //asset has been already parsed

        $targetFile = $this->renderer->getClientFolder() . 'assets/' .
            $asset;

        if (file_exists($targetFile)) {
            $result = $this->makeFileAssetUrl($asset);
        } else {
            $result = $this->makeWidgetAssetUrl($asset, $widgetClass);
        }

        return $result;
    }

    /**
     * Make the url for the given asset in the website
     *
     * @param string $asset
     * @return string
     */
    public function makeFileAssetUrl(string $asset): string
    {
        $this->initAssetsUrl();

        $assetSettings = $this->getResourcesSettings();

        $v = $assetSettings[$asset]['version'] ?? '';

        $result = $this->renderer->getClientRootUrl() . 'assets/' .
            $asset;

        // getFileAssetPath

        return $v ? $result . "?v=$v" : $result;
    }

    /**
     * Make the url for the given asset in the widget package
     *
     * @param string $asset
     * @param string $widgetClass
     * @return string
     */
    public function makeWidgetAssetUrl(string $asset, string $widgetClass): string
    {
        $this->initAssetsUrl();

        $result = '';

        $wp = $this->renderer->loadWidgetPackage($widgetClass);

        $assetsFolder = $wp->getWidgetAssetsFolder();

        $widgetFolder = $wp->getRootFolder();

        $widgetName = str_replace(['__', "\\"], '/', $widgetClass);
        $p = explode('/', $widgetName);
        $widgetName = count($p) == 1 ? "_global/$widgetName" : $widgetName;

        $widgetResource = $widgetFolder . $assetsFolder . "/$asset";

        if (file_exists($widgetResource)) {
            $result = $this->renderer->getClientWidgetUrl($widgetName) . '/' .
                $assetsFolder . "/$asset";
        }

        return $result;
    }

    /**
     * This function returns the absolute path of the given asset.
     * Try to find the asset from the website first.
     * If the asset can't be found from the website, fetch the asset from the widget package
     * @param string $file the name of asset
     * @param string $widgetClass the name of widget
     * @return string
     */
    public function getAssetPath(string $asset, string $widgetClass = ''): string
    {
        if (strpos($asset, './') !== false) {
            throw new \Exception('You cannot read file in parent folders.');
        }

        $assetFile = $this->getFileAssetPath($asset);

        if (!file_exists($assetFile)) {
            $assetFile = $this->getWidgetAssetPath($asset, $widgetClass);
        }

        return file_exists($assetFile) ? $assetFile : '';
    }

    /**
     * Returns the absolute path of the given asset in
     * the website.
     * @param string $file the name of asset
     * @return string
     */
    public function getFileAssetPath(string $asset): string
    {
        $resourceSettings = $this->getResourcesSettings();
        $settings = $resourceSettings[$asset] ?? [];
        $path = $settings['path'] ?? '';

        $pathStr = $path ? $path . '/' : '';

        return $this->renderer->getClientFolder() . 'assets/' .
            $pathStr . $asset;
    }

    /**
     * Returns the absolute path of the given asset in
     * the widget package.
     * @param string $file the name of asset
     * @param string $widgetClass the name of widget
     * @return string
     */
    public function getWidgetAssetPath(string $asset, $widgetClass): string
    {
        $wp = $this->renderer->loadWidgetPackage($widgetClass);

        $rootFolder = $wp->getRootFolder();

        $targetFolder = $wp->getWidgetAssetsFolder();

        return $rootFolder . $targetFolder . '/' . $asset;
    }

    /**
     * Get the content of the given asset
     * Renamed from old function getFileContent
     * @param string $file
     * @param string $widgetClass
     * @return void
     */
    public function getAssetContents(string $asset, string $widgetClass = '')
    {
        $fileName = $this->getAssetPath($asset, $widgetClass);

        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'json' ?
            $this->renderer->readJSONFile($fileName) : '';
    }

    /**
     * Get the content of the given asset in the widget package
     * @param string $file
     * @param string $widgetClass
     * @return void
     */
    public function getWidgetAssetContents(string $asset, string $widgetClass)
    {
        $fileName = $this->getWidgetAssetPath($asset, $widgetClass);

        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'json' ?
            $this->renderer->readJSONFile($fileName) : '';
    }

    /**
     * This function returns the asset settings in the website folder.
     *
     * @return array
     */
    public function getResourcesSettings(): array
    {
        return $this->renderer->loadClientSettings(self::RESOURCE_SETTINGS) ?? [];
    }

    /**
     * Get optimized assets or the original asset.
     *
     * Get urls of the asset
     *
     * @param string $asset
     * @param array $options
     * @return array|string
     */
    public function getImgSrcset(string $asset, array $options = [])
    {
        $assetUrl = $asset;

        $asset = str_replace($this->renderer->getClientRootUrl() . 'assets/', '', $asset);

        $ext = strtolower(pathinfo($asset, PATHINFO_EXTENSION));

        $assetName = pathinfo($asset, PATHINFO_FILENAME);
        $dirname = pathinfo($asset, PATHINFO_DIRNAME);

        $pendingFilename = $this->initOptFolder();

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) //need to optimize
        {
            $srcset = [];

            $assetPath = $this->renderer->getClientFolder() . "assets/$asset";

            $pendingList = json_decode(file_get_contents($pendingFilename), true);

            $md5 = md5_file($assetPath);

            $pendingList[$md5] = [
                'name' => $asset,
                'filename' => $assetName,
                'dirname' => $dirname,
                'path' => $assetPath,
                'ext' => $ext,
                'optList' => []
            ];

            $pre = $dirname && $dirname[0] != '.' ? "$dirname/$assetName"
                : $assetName;

            if (!$options) {
                $w = getimagesize($assetPath)[0];
                $w1 = intval($w * 0.5);
                $w2 = intval($w * 0.25);

                $options = [
                    [
                        'width' => $w1,
                        'rename' => ['suffix' => "-$w1"]
                    ],
                    [
                        'width' => $w2,
                        'rename' => ['suffix' => "-$w2"]
                    ],
                    [
                        'rename' => [
                            'suffix' => "-original"
                        ],
                        'isOriginal' => true
                    ]
                ];

                if ($ext !== 'webp') {
                    $options[] = [
                        'rename' => [
                            'extname' => '.webp'
                        ]
                    ];
                }
            }

            $optList = &$pendingList[$md5]['optList'];

            foreach ($options as $value) {
                $optList[] = $value;

                $width = $value['width'] ?? getimagesize($assetPath)[0];

                $newName = $pre;
                $newExt = $ext;

                if ($value['rename'] ?? false) {
                    if (!empty($value['rename']['suffix'])) {
                        $newName = $newName . $value['rename']['suffix'];
                    }

                    if (!empty($value['rename']['extname'])) {
                        $newName = $newName . $value['rename']['extname'];
                        $newExt = $value['rename']['extname'];
                    } else {
                        $newName = $newName . ".$ext";
                    }
                } else {
                    $newName = $newName . ".$ext";
                }

                $item = [
                    'url' => $this->makeFileAssetUrl($newName),
                    'w' => $width,
                    'ext' => $newExt
                ];

                if (!empty($value['isOriginal']))
                    $item['original'] = true;

                $srcset[] = $item;
            }

            // //1st version, reduce resolution to 80%
            // $w1 = intval($w * 0.5);
            // $optList[] = [
            //     'width' => $w1,
            //     'rename' => ['suffix' => "-$w1"]
            // ];

            // $srcset[] = [
            //     'url' => $this->makeFileAssetUrl($pre . "-$w1.$ext"),
            //     'w' => $w1,
            //     'ext' => $ext
            // ];

            // //2nd version, reduce resolution to 60%
            // $w2 = intval($w * 0.25);
            // $optList[] = [
            //     'width' => $w2,
            //     'rename' => ['suffix' => "-$w2"]
            // ];

            // $srcset[] = [
            //     'url' => $this->makeFileAssetUrl($pre . "-$w2.$ext"),
            //     'w' => $w2,
            //     'ext' => $ext
            // ];

            // //3rd version, if the asset is not webp, create a webp version
            // if ($ext !== 'webp') {
            //     $optList[] = [
            //         'rename' => [
            //             'extname' => '.webp'
            //         ]
            //     ];

            //     $srcset[] = [
            //         'url' => $this->makeFileAssetUrl($pre . ".webp"),
            //         'w' => $w,
            //         'ext' => 'webp'
            //     ];
            // }

            // //4th version, reduce the quality of the original asset
            // $optList[] = [
            //     'rename' => [
            //         'suffix' => "-original"
            //     ]
            // ];

            // $srcset[] = [
            //     'url' => $this->makeFileAssetUrl($pre . "-original.$ext"),
            //     'original' => true,
            //     'ext' => $ext
            // ];

            $encoded = json_encode($pendingList, JSON_PRETTY_PRINT);
            file_put_contents($pendingFilename, $encoded);

            return $srcset;
        }

        return $assetUrl;
        // $result = $this->createOptImage($asset);

        // $opts = [];

        // if (is_array($result) && $result) {
        //     foreach ($result as $value) {
        //         list($w, $h) = getimagesize($value['file']);

        //         $opts[] = [
        //             'url' => $this->makeFileAssetUrl($value['name']),
        //             'w' => $w
        //         ];
        //     }

        //     [$ow, $oh] = getimagesize($this->renderer->getClientFolder() .
        //         'assets/' . $asset);

        //     $opts[] = [
        //         'url' => $this->makeFileAssetUrl($asset),
        //         'w' => $ow,
        //         'original' => true
        //     ];

        //     return $opts;
        // }

        // return $asset;
    }

    function initOptFolder()
    {
        $optFolder = $this->renderer->getClientFolder() . 'assets/_opts';

        if (!is_dir($optFolder))
            mkdir($optFolder, 0777, true);

        $optFilename = $optFolder . '/optList.json';

        if (!file_exists($optFilename))
            file_put_contents($optFilename, json_encode((object) null));

        $pendingFileName = $optFolder . '/pendingList.json';

        if (!file_exists($pendingFileName))
            file_put_contents($pendingFileName, json_encode([], true));

        return $pendingFileName;
    }

    // /**
    //  * Create optimized version of given assets
    //  * Do nothing here, override in child class;
    //  */
    // public function createOptImage(string $asset)
    // {
    //     $folder = __DIR__ . '/../../../compiled/' .
    //         $this->renderer->websiteName() . '_static';

    //     $assetFile = $folder . '/assets/' . $asset;

    //     $result = $asset;

    //     if (file_exists($assetFile)) {
    //         $ext = strtolower(pathinfo($asset, PATHINFO_EXTENSION));

    //         if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) //need to optimize
    //         {
    //             $optFolder = $folder .  '/opts/';

    //             $this->optimizeImage($assetFile, $optFolder);

    //             $files = $this->renderer->getDirContents($optFolder);

    //             $dirname = pathinfo($assetFile, PATHINFO_DIRNAME);

    //             $pre = pathinfo($asset, PATHINFO_DIRNAME);

    //             $result = [];

    //             foreach ($files as $file) {
    //                 $optName = $pre . "/$file";
    //                 $newFile = $dirname . "/$optName";

    //                 $result[] = [
    //                     'name' => $optName,
    //                     'file' => $newFile
    //                 ];

    //                 $srcFile = $optFolder . "/$file";

    //                 rename($srcFile, $newFile);
    //             }
    //         }
    //     }

    //     return $result;
    // }

    // function optimizeImage($file, $folder)
    // {
    //     $result = null;
    //     if (!is_dir($folder))
    //         mkdir($folder);

    //     $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    //     if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
    //         $basename = pathinfo($file, PATHINFO_BASENAME);

    //         $cacheFile = $folder . '/' . $basename;
    //         copy($file, $cacheFile);

    //         $result = $this->optimizeImageSize($cacheFile, $ext, $basename);
    //     }

    //     return $result;
    // }

    // function optimizeImageSize($file, $ext, $basename)
    // {
    //     $ratio = 0.5;
    //     $result = [];

    //     if (filesize($file) >= self::SIZE) {
    //         //optimize resolition
    //         list($w, $h) = getimagesize($file);

    //         $cacheFile = $this->optimizeFileResolution(intval($w * $ratio), intval($h * $ratio), $w, $h, $file, $ext);

    //         $newName = intval($w * $ratio) . '_' . $basename;
    //         $dirname = pathinfo($file, PATHINFO_DIRNAME);

    //         $newFile = $dirname . '/' . $newName;

    //         rename($cacheFile, $newFile);

    //         $result[] = $newFile;

    //         if (filesize($newFile) >= self::SIZE) {
    //             $optFile = $dirname . '/opt_' . $newName;

    //             copy($newFile, $optFile);

    //             array_merge($result, $this->optimizeImageSize($optFile, $ext, $basename));
    //         }
    //     }

    //     return $result;
    // }

    // function optimizeFileResolution($newW, $newH, $w, $h, $file, $ext)
    // {
    //     $newImg = imagecreatetruecolor($newW, $newH);

    //     if ($ext == 'png') {
    //         $img = imagecreatefrompng($file);

    //         $transparency = imagecolortransparent($img);

    //         if ($transparency >= 0) {
    //             $trnprt_indx = imagecolorat($img, 0, 0);
    //             $trnprt_color  = imagecolorsforindex($img, $trnprt_indx);
    //             $transparency       = imagecolorallocate($newImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
    //             imagefill($newImg, 0, 0, $transparency);
    //             imagecolortransparent($newImg, $transparency);
    //         } else {
    //             imagealphablending($newImg, false);
    //             $color = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
    //             imagefill($newImg, 0, 0, $color);
    //             imagesavealpha($newImg, true);
    //         }

    //         imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

    //         unlink($file);
    //         imagepng($newImg, $file, 1);

    //         // Free up memory
    //         imagedestroy($img);
    //     } elseif ($ext == 'jpg' || $ext == 'jpeg') {
    //         $img = imagecreatefromjpeg($file);
    //         imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

    //         unlink($file);
    //         imagejpeg($newImg, $file, 90);

    //         // Free up memory
    //         imagedestroy($img);
    //     }

    //     return $file;
    // }

    /**
     * Load a default 404 HTML page.
     *
     * @return string
     */
    public function get404Page(): string
    {
        return file_get_contents(__DIR__ . "/404.html");
    }

    /**
     * Create symlink for asset folder. We don't have to create
     * symlink only for those used assets. The symlink folder should
     * not take space.
     *
     * @return void
     */
    protected function initAssetsUrl()
    {
        if ($this->isInit) {
            return;
        }

        $this->isInit = true;

        $assetsFolder = $this->renderer->getClientFolder() . 'assets/';

        $this->renderer->initFolderUrl(
            $assetsFolder,
            $this->renderer->getClientRootUrl() . 'assets'
        );
    }
}
