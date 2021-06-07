<?php

/**
 * File for class LibraryManager.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\Core;

/**
 * Manager for libraries and their interdependence.
 */
class LibraryManager
{
    /** @var array Cached library dependencies. */
    protected $libDependency = [];

    public function __construct()
    {
    }

    /**
     * Draw lib dependency graph;
     *
     * @param array $libs
     * @param string $mode js or css;
     * @param string $type standard or generic;
     * @return void
     */
    public function setLibDependency(array $libs, string $mode, string $type): void
    {
        if (!is_array($libs) || !$libs)
            return;

        for ($i = 0; $i < count($libs) - 1; $i++) {
            for ($j = $i + 1; $j < count($libs); $j++) {
                // PHP uses copy-on-write, so this is efficient
                $libs_i = $libs[$i];
                $libs_j = $libs[$j];

                if ($type == 'standard') {
                    $mainLib = $libs_i['lib'];
                    $subLib = $libs_j['lib'];
                } else {
                    $ti = $libs_i['type'] ?? '';
                    $tj = $libs_j['type'] ?? '';

                    $mainLib = $ti == 'local' ? $libs_i['path'] : $libs_i['lib'];
                    $subLib = $tj == 'local' ? $libs_j['path'] : $libs_j['lib'];
                }

                // The reference is null if the slot doesn't exist
                $slot = &$this->libDependency[$type][$mode][$mainLib][$subLib];

                /** @todo Check that this works!!! */
                $slot = $slot ? $slot + 1 : 1;
            }
        }
    }

    /**
     * Get generic type library
     *
     * @param array $libs
     * @param string $mode js or css;
     * @return array
     */
    public function getGenericLibs(array $libs, string $mode): array
    {
        $result = [];

        $latestLibs = [];

        foreach ($libs as $value) {
            $lib = $value['lib'];
            $version = $value['version'] ?? '';

            $libType = $value['type'] ?? '';

            if ($libType == 'local') {
                $path = $value['path'];
                $url = $value['url'];

                $latestLibs[$path] = ['type' => 'local', 'url' => $url];

                $latestLibs[$path]['widgetPath'] = $value['widgetPath'] . "/$mode/$lib";
            } else {
                if (isset($latestLibs[$lib])) {
                    $preVersion = $latestLibs[$lib];

                    if (version_compare($preVersion, $version) < 0) {
                        $latestLibs[$lib] = $version;
                    }
                } else {
                    $latestLibs[$lib] = $version;
                }
            }
        }

        $latestLibs = $this->sortLibs($latestLibs, $mode, 'generic');

        foreach ($latestLibs as $key => $value) {
            if (is_array($value)) {
                $url = ['libPath' => $value['widgetPath']];
            } else {
                $url = $this->getLibURL($libs, $key, $value);
            }

            array_push($result, $url);
        }

        return $result;
    }

    /**
     * Undocumented function
     *
     * @param array $libs
     * @param string $libName
     * @param string $versionNum
     * @return void
     */
    public function getLibURL($libs, $libName, $versionNum)
    {
        $url = '';
        foreach ($libs as $value) {
            $lib = $value['lib'];
            $version = $value['version'] ?? -1;

            if ($lib == $libName && $versionNum == $version) {
                $url = $value['url'];
                break;
            }
        }

        return $url;
    }

    public function getLatestLibs($libs, $mode)
    {
        $latestLibs = [];
        // $result = [];

        $latestLibs = $this->getLatestLibsVersion($libs, $mode);

        $latestLibs = $this->sortLibs($latestLibs, $mode, 'standard');

        return $latestLibs;
    }

    /**
     * For same library, we try to load only the latest version
     *
     * @param array $libs
     * @param string $mode
     * @return void
     */
    public function getLatestLibsVersion(array $libs, string $mode): array
    {
        $latestLibs = [];

        foreach ($libs as $value) {
            $lib = $value['lib'];
            $version = $value['version'] ?? '';

            $path = $value['path'] ?? '';

            if ($path) {
                if (isset($latestLibs[$lib])) {
                    $version = explode('/', $path)[0];

                    $preVersion = explode('/', $latestLibs[$lib])[0];

                    if (version_compare($preVersion, $version) < 0) {
                        $latestLibs[$lib] = $path;
                    }
                } else {
                    $latestLibs[$lib] = $path;
                }
            } else {
                if (isset($latestLibs[$lib])) {
                    $preVersion = $latestLibs[$lib];

                    if (version_compare($preVersion, $version) < 0) {
                        $latestLibs[$lib] = $version;
                    }
                } else {
                    $latestLibs[$lib] = $version;
                }
            }
        }

        return $latestLibs;
    }

    public function sortLibs($libs, $mode, $type)
    {
        $sortArray = [];
        $result = [];

        foreach ($libs as $key => $x) {
            $deps = $this->libDependency[$type][$mode][$key] ?? false;

            if ($deps) {
                $sum = 0;

                foreach ($deps as $value) {
                    $sum += $value;
                }

                $sortArray[$key] = $sum;
            } else {
                $sortArray[$key] = 0;
            }
        }

        arsort($sortArray);

        foreach ($sortArray as $key => $value) {
            if (count($result) == 0) {
                array_push($result, $key);
            } else {
                $len = count($result);
                for ($i = 0; $i < $len; $i++) {
                    $r = $this->compareLibDependency($result[$i], $key, $mode, $type);

                    if ($r == -1) {
                        array_splice($result, $i, 0, array($key));
                    } elseif ($i == $len - 1) {
                        array_push($result, $key);
                    }
                }
            }
        }

        $resultLibs = [];

        foreach ($result as $value) {
            if (isset($libs[$value])) {
                $resultLibs[$value] = $libs[$value];
            }
        }

        return $resultLibs;
    }

    /**
     * Undocumented function
     *
     * @param string $lib1
     * @param string $lib2
     * @param string $mode
     * @param string $type
     * @return integer 0 = no relationship; 1 = lib1 should be placed before 
     * lib2; -1 = lib1 should be placed after lib2.
     */
    public function compareLibDependency(
        string $lib1,
        string $lib2,
        string $mode,
        string $type
    ): int {
        $n1 = $this->libDependency[$type][$mode][$lib1][$lib2] ?? false;
        $n2 = $this->libDependency[$type][$mode][$lib2][$lib1] ?? false;

        if ($n1 && $n2) {
            return $n1 > $n2 ? 1 : (($n1 == $n2) ? 0 : -1);
        } elseif ($n1 && !$n2) {
            return 1;
        } elseif (!$n1 && $n2) {
            return -1;
        }

        return 0;
    }
}
