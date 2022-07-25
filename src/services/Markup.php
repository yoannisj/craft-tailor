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
     * Renders snippets that were registered for given position in response template.
     * 
     * @param string $position The template position of snippets to render
     * 
     * @return string The rendered snippets
     */

    public function renderSnippets( string $position ): string
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
     * Adds/registers a snippet to given template position
     * 
     * @param string $position The position at which to register the snippet
     * @param string $path The path to the snippet template
     * @param array|null $vars The variables given to snippet on render time
     * @param bool $unique Whether to only ever register the snippet with same path/vars once
     * 
     * @return bool Whether the snippet was successfully registered
     */

    public function addSnippet(
            string $position, string $path, array $vars = null, bool $unique = false ): bool
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

}