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

use yoannisj\tailor\helpers\DataHelper;

/**
 *
 */

class Markup extends Component
{
    // =Properties
    // ========================================================================

    protected $snippets = [];

    // =Public Methods
    // ========================================================================

    /**
     *
     */

    public function renderSnippets( string $position )
    {
        $view = Craft::$app->getView();
        $snippets = $this->snippets[$position] ?? null;

        if (empty($snippets)) return null;

        $res = '';

        foreach ($snippets as $snippet) {
            $res .= $view->renderTemplate($snippet['path'], $snippet['vars']);
        }

        return $res;
    }

    /**
     *
     */

    public function addSnippet( string $position, string $path, array $vars = null, bool $unique = false )
    {
        if (!array_key_exists($position, $this->snippets)) {
            $this->snippets[$position] = [];
        }

        if ($unique)
        {
            $snippets = $this->snippets[$position];

            foreach ($snippets as $snippet)
            {
                if ($snippet['path'] == $path
                    && DataHelper::isSame($snippet['vars'], $vars)
                ) {
                    return false;
                }
            }
        }

        $this->snippets[$position][] = [
            'path' => $path,
            'vars' => $vars
        ];

        return true;
    }

    /**
     *
     */

    public function getAttrs( array $attrs, string $key )
    {
    }

    /**
     *
     */

    public function setAttrs( array $attrs, string $key, $value )
    {
    }

    /**
     *
     */

    public function addAttrs( array $attrs, string $key, $values, bool $join = false )
    {
    }

    /**
     *
     */

    public function attrs( array $attrs, string $key )
    {
        
    }

}