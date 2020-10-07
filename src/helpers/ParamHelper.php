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

use yii\base\InvalidCallException;
use yii\base\InvalidArgumentException;
use yii\base\UnkownPropertyException;

use Craft;
use craft\db\Query;
use craft\models\Site;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;

/**
 *
 */

class ParamHelper
{
    // =Properties
    // ========================================================================

    // =Methods
    // ========================================================================

    /**
     * 
     */

    public static function parseParams( $params ): array
    {
        if (!$params || empty($params)) {
            return [];
        }

        if (is_array($params))
        {
            if (!ArrayHelper::isIndexed($params)) {
                return $params;
            }

            $res = [];

            foreach ($params as $prm)
            {
                $name = $prm['name'] ?? null;

                if ($name)
                {
                    $value = $prm['value'] ?? true;
                    $res[$name] = $value;
                }
            }

            return $res;
        }

        else if (is_string($params)) {
            return static::parseQueryString($params);
        }

        throw new InvalidArgumentException('Params must be an associative array, an array of items with keys `name` and `value`, or a query string.');
    }

    /**
     * 
     */

    public static function parseQueryString( string $query ): array
    {
        $params = explode('&', $query);
        $res = [];

        foreach ($params as $prm)
        {
            $parts = explode('=', $prm);
            $name = $parts[0];
            $value = count($parts) > 1 ? urldecode($parts[1]) : true;

            $res[$name] = $value;
        }

        return $res;
    }

    /**
     * 
     */

    public static function buildQueryString( array $params ): string
    {
        $params = static::parseParams($params);
        return UrlHelper::buildQuery($params);
    }

    /**
     * Checks whether one set of params matches another one
     *
     * @todo: support comparing more than two hashes
     *
     * @param array | string $params the params that are being tested
     * @param array | string $values the params to test against
     * @param bool $strict (optional)
     *
     * @return bool
     */

    public static function matchParams( $params, $values, bool $strict = false ): bool
    {
        $params = static::parseParams($params);
        $values = static::parseParams($values);

        foreach ($values as $name => $value)
        {
            // @todo: pass list of ignored values in an options hash?
            if (!array_key_exists($name, $params)
                || $value === null || $value == '*'
            ) {
                continue;
            }

            $isArray = is_array($value);
            $prmValue = $params[$name] ?? null;

            // @todo: support 'strict' argument when searhing in array
            if (($isArray && !in_array($prmValue, $value))
                || ($strict && $prmValue !== $value)
                || (!$strict && $prmValue != $value)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Collect all values found in multiple param maps
     *
     * @param bool $unique (optional)
     * @param [ array, ... ] param map(s) to collect from
     *
     * @return array
     */

    public function collectValues( ): array
    {
        $params = func_get_args();
        $unique = true;

        if (gettype($params[0]) == 'boolean')
        {
            $params = array_slice($params, 1);
            $unique = true;
        }

        $res = [];

        foreach ($params as $prm)
        {
            foreach ($prm as $name => $value)
            {
                if (!array_key_exists($name, $res)) {
                    $res[$name] = [];
                }

                if (!$unique || !in_array($value, $res[$name])) {
                    $res[$name][] = $value;
                }
            }
        }

        return $res;
    }

    /**
     * Reduces list of param maps.
     *
     * @return array
     */

    // public function evaluate(): array
    // {
    //     $params = func_get_args();
    //     $allValues = forward_static_call_array([ParamHelper::class, 'collectValues'], $params;

    //     $res = [];

    //     foreach ($allValues as $name => $values)
    //     {
    //         $count = count($values);
    //         $uniqueCount = count(array_unique($values));

    //         if ($count != $uniqueCount) {
    //             $res[$name] = array_pop($values);
    //         }
    //     }

    //     return $res;
    // }

    /**
     * Reduces list of param maps to a single value based on given param name
     *
     * @param string $name name of parameter to evaluate
     * @param [ array, ... ] param map(s) to collect from
     *
     * @return string | null
     */

    // public function reduceValue( string $name )
    // {
    //     $params = array_slice(func_get_args(), 1);
    //     $value = null;

    //     foreach ($params as $prm)
    //     {
    //         if (array_key_exists($name, $prm))
    //         {
    //             $prmValue = $prm[$name] ?? null;

    //             if ($prmValue != $value) {
    //                 $value = $prmValue;
    //             }
    //         }
    //     }

    //     return $value;
    // }

    // public function evaluateValue( string $name )
    // {
    //     $params = array_slice(func_get_args(), 1);
    //     $allValues = [];
    //     $uniqueValues = []:

    //     foreach ($params as $prm)
    //     {
    //         if (array_key_exists($name, $prm))
    //         {
    //             $prmValue = $prm[$name];
    //             $allValues[] = $prmValue;

    //             if (!in_array($prmValue, $uniqueValues)) {
    //                 $uniqueValues[] = $prmValue;
    //             }
    //         }
    //     }

    //     return $value;
    // }


};