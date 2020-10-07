<?php

/**
 * Tailor plugin for Craft
 *
 * @author Yoannis Jamar
 * @copyright Copyright (c) 2019 Yoannis Jamar
 * @link https://github.com/yoannisj/
 * @package craft-tailor
 *
 */

namespace yoannisj\tailor\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;

/**
 * Service to work with pathmasks
 */

class Pathmasks extends Component
{
    // =Constants
    // ========================================================================

    /**
     * Segment separator used in path masks
     */

    const PATH_MASK_SEPARATOR = '::';

    /**
     * Name of default file loaded for pathmask possibilities pointing to a directory
     */

    const PATH_MASK_DIRECTORY_DEFAULT = '_default';

    // =Properties
    // ========================================================================

    /**
     * @var array
     */

    private $_directoryFileNames;

    // =Constructor
    // ========================================================================

    // =Public Methods
    // ========================================================================

    /** 
     * Returns ordered list of files to consider when resolving directory paths
     *
     * @return array
     */

    public function getDirectoryFileNames(): array
    {
        if (!isset($this->_directoryFileNames))
        {
            $this->_directoryFileNames = array_merge(
                [ self::PATH_MASK_DIRECTORY_DEFAULT ],
                Craft::$app->getConfig()->getGeneral()->indexTemplateFilenames,
            );
        }

        return $this->_directoryFileNames;
    }

    /**
     * Parses given pathmask, and returns the full list of possible paths
     *
     * @param string $mask
     * @param Mixed $object
     * @param array $vars
     *
     * @return array
     */

    public function parsePathmask( string $mask, $object = null, array $vars = [] )
    {
        // Accept an array of pathmasks
        if (is_array($mask)) {
            return array_map([$this, 'parsePathmask'], $mask);
        }

        // Accept an object template as path mask template
        if (StringHelper::contains($mask, '{') && $object) {
            $mask = Craft::$app->view->renderObjectTemplate($mask, $object, $vars);
        }

        // initialize full list of possible paths
        $paths = [];

        // identify pathmask segments
        $segments = explode(self::PATH_MASK_SEPARATOR, $mask);
        // $count = count($segments) + 1;

        while (count($segments))
        {
            $path = implode('/', $segments);

            // add path for default and index files in directory
            foreach ($this->directoryFileNames as $fileName) {
                $paths[] = StringHelper::ensureRight($path, DIRECTORY_SEPARATOR.$fileName);
            }

            // add path for file/directory index
            $paths[] = $path;

            // move on to lower path
            $segments = array_slice($segments, 0, -1);
        }

        return $paths;
    }

    /**
     * Resolves given pathmask, returning the first candidate file found in the twig's
     * template load paths (caches results for better perfomance).
     *
     * If the `resolvePathMasks` config variable is set to false, this will return
     * exactly the same as `parsePathMasks`.
     *
     * @param string $mask
     * @param Mixed $object
     * @param array $vars
     *
     * @return string | array
     */

    public function resolvePathmask( string $mask, $object = null, array $vars = [] )
    {
        return $this->parsePathmask($mask, $object, $vars);
    }

    // =Protected Methods
    // ========================================================================

}