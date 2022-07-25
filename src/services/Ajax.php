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

/**
 *
 */

class Ajax extends Component
{
    // =Public Methods
    // ======================≠======================≠======================≠===

    /**
     * @param array|null $params All params received with ajax request
     *
     * @return array Rendered snippet data to include in ajax response
     */

    public function renderSnippets( array $params = null ): array
    {
        if (!$params || !array_key_exists('snippets', $params)) {
            return [];
        }

        $snippets = $params['snippets'];

        $view = Craft::$app->getView();
        $result = [];

        foreach ($snippets as $key => $snippet)
        {
            $path = null;
            $vars = null;
            $rendered = null;
            $error = null;

            if (is_array($snippet))
            {
                $path = $snippet['path'];
                $vars = $snippet['vars'];
            }

            else if (!is_numeric($key))
            {
                $path = $key;
                $vars = $snippet;
            }

            else
            {
                $path = $snippet;
                $vars = [];
            }

            try {
                $rendered = $view->renderTemplate($path, $vars);
            }

            catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            $result[$key] = [
                'path' => $path,
                'vars' => $vars,
                'rendered' => $rendered,
                'error' => $error,
            ];
        }

        return $result;
    }
}