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

namespace yoannisj\tailor\helpers;

use Craft;
use Craft\helpers\StringHelper;
use Craft\helpers\ArrayHelper;

/**
 *
 */

class MarkupHelper
{
    /**
     * Replaces given html tag in html string
     *
     * @param string $text
     * @param string $tag
     * @param string $replacement
     * @param bool $preserveAttrs
     *
     * @return string
     */

    public static function replaceTag( string $markup, string $tag, string $replacement, bool $preserveAttrs = true ): string
    {
        // optimize for empty strings
        if (empty($markup)) return '';

        // we need to differenciate between opening and closing tags
        if (!empty($replacement))
        {
            $replacement = trim(trim($replacement), '<>');

            $search = [
                '/<'.$tag.'([\s\S]*?)>/', // opening tag
                '/<\/'.$tag.'>/', // closing tag
            ];

            $replace = [
                '<'.$replacement.($preserveAttrs ? '$1' : '').'>', // opening tag
                '</'.$replacement.'>', // closing tag
            ];

            return preg_replace($search, $replace, $markup);
        }

        return preg_replace('/<\/?p([\s\S]*?)>/', '', $markup);
    }

    /**
     * Parses and normalizes given map of classnames
     *
     * @param Array | string $classnames
     * @return Array
     */

    public static function parseClassMap( $classnames, $key = 'root' )
    {
        if (is_string($classnames)) {
            $classnames = explode(' ', $classnames);
        }

        if (is_array($classnames))
        {
            if (!empty($classnames) && ArrayHelper::isAssociative($classnames))
            {
                foreach ($classnames as $key => $classList)
                {
                    if ($key != 'root') {
                        $classnames[$key] = self::parseClassMap($classList);
                    }
                }
            }

            else
            {
                $classnames = [
                    'root' => self::parseClassList($classnames)
                ];
            }

            return $classnames;
        }

        return null;
    }

    /**
     * Parses and normalizes given list of classnames
     *
     * @param Array | string $classnames
     * @return Array
     */

    public static function parseClassList( $classnames )
    {
        if (is_array($classnames))
        {
            // if (ArrayHelper::isAssociative($classnames))
            // {
            //     // foreach ($classnames as $key => $classList)
            //     // {
            //     //     if ($key != 'root')
            //     //     {
            //     //         $classnames[$key] = [
            //     //             'root' => self::parseClassList($classList)
            //     //         ];
            //     //     }
            //     // }

            //     // return $classnames;
            //     return array_map('self::parseClassList', $classnames);
            // }

            return $classnames;
        }

        if (is_string($classnames)) {
            return explode(' ', $classnames);
        }

        return null;
    }

    /**
     *
     */

    public static function composeClassnames(): array
    {
        $classnames = static::parseClassMap([]);
        $maps = func_get_args();

        foreach ($maps as $map)
        {
            $map = static::parseClassMap($map) ?? [];
            $classnames = array_merge_recursive($classnames, $map);
        }

        return $classnames;
    }
}