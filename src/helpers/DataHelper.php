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

/**
 *
 */

class DataHelper
{
    // =Properties
    // ========================================================================

    /**
     * @var List of supported fetch methods
     */

    const FETCH_METHOD_ALL = 'all';
    const FETCH_METHOD_COUNT = 'count';
    const FETCH_METHOD_EXISTS = 'exists';
    const FETCH_METHOD_ONE = 'one';
    const FETCH_METHOD_FIRST = 'first';
    const FETCH_METHOD_LAST = 'last';
    const FETCH_METHOD_NTH = 'nth';

    // =Methods
    // ========================================================================

    /**
     * Returns value for given object property name or array key.
     * Supports nested properties using dot notation.
     * Accepts a list of prefered property names (will return first property that is set).
     *
     * @param object | array $data object or associative array to access
     * @param string | array $key name of property | array key to return
     * @param bool $allowEmpty whether an empty value is satisfactory or not
     * 
     * @return mixed
     */

    public static function prop( $data, $key, bool $allowEmpty = true )
    {
        // // allow omitting the $default value
        // if (is_bool($default) && is_null($allowEmpty))
        // {
        //     $allowEmpty = $default;
        //     $default = null;
        // }

        // // make sure allow empty is a boolean
        // if (is_null($allowEmpty)) $allowEmpty = false;

        // support a list of keys (ordered by preference)
        if (is_array($key))
        {
            foreach ($key as $k)
            {
                try {
                    $value = ArrayHelper::getValue($data, $k);
                } catch (UnkownPropertyException $e) {
                    $value = null;
                }

                if (!$allowEmpty && empty($value)) {
                    continue;
                }

                if (!is_null($value)) {
                    return $value;
                }
            }

            return null;
        }

        try {
            $value = ArrayHelper::getValue($data, $key);
        } catch (UnkownPropertyException $e) {
            $value = null;
        }

        if (!$allowEmpty && empty($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Shortcut to execute a database Query fetch operation on given object or 
     * associative array property value.
     * Supports nested property values by using dot notation.
     * Accepts a list of prefered property names (will use first property that is set).
     * Supports eager-loaded property values, by filtering items according to given fetch method.
     *
     * @param array | object $data object or associative array to access
     * @param string | array $key name of property on which to run the fetch method
     * @param bool $allowEmpty (optional) whether empty property values can be considered
     * @param string $method Fetch method to use
     * @param args.. arguments passed to the fetch method (after property value)
     *
     * @return mixed Fetch results
     */

    public static function fetchProp( $data, $key, $allowEmpty, $method = null )
    {
        $args = func_get_args();
        $fetchArgsStart = 4;

        // allow omitting the $allowEmpty parameter
        if (!is_bool($allowEmpty))
        {
            $method = $allowEmpty;
            $allowEmpty = true;
            $fetchArgsStart = 3;
        }

        if (!is_string($method)) {
            throw new InvalidArgumentException('method argument must be a fetch method name');
        }

        if (!in_array($method, [
            self::FETCH_METHOD_ALL,
            self::FETCH_METHOD_COUNT,
            self::FETCH_METHOD_EXISTS,
            self::FETCH_METHOD_ONE,
            self::FETCH_METHOD_FIRST,
            self::FETCH_METHOD_LAST,
            self::FETCH_METHOD_NTH,
        ])) {
            throw new InvalidArgumentException('method argument `'.$method.'` is not supported.');
        }

        $query = static::prop($data, $key, $allowEmpty);
        $fetchMethod = 'fetch' . ucfirst($method);

        $fetchArgs = array_slice($args, $fetchArgsStart);
        array_unshift($fetchArgs, $query);

        return forward_static_call_array([DataHelper::class, $fetchMethod], $fetchArgs);
    }

    /**
     * Fetches all resutlfs for given query.
     * Supports eager-loaded list of values.
     *
     * @param yii\db\Query | array $query the base query used to fetch results
     * @param array $criteria
     *
     * @return array
     * 
     * @todo: Remove workaround for Neo issue #387 once it is fixed
     */

    public static function fetchAll( $query, array $criteria = null ): array
    {
        if (is_null($query)) {
            return [];
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            $query = static::_cleanDuplicateNeoBlocks($query);

            if ($criteria) {
                $query = static::applyCriteriaToList($query, $criteria);
            }

            return $query;
        }

        if ($query instanceof Query)
        {
            if ($criteria) {
                Craft::configure($query, $criteria);
            }

            $results = $query->all();
            $results = static::_cleanDuplicateNeoBlocks($results);

            // support eager loading for Neo Block queries
            // @link https://github.com/spicywebau/craft-neo/blob/master/docs/eager-loading.md
            if (Craft::$app->getPlugins()->isPluginInstalled('neo')
                && $query instanceof \benf\neo\elements\db\BlockQuery)
            {
                foreach ($results as $block) {
                    $block->useMemoized($query);
                }
            }

            return $results;
        }

        return [];
    }

    /**
     * Fetches total count of given query results
     * Supports eager-loaded list of values.
     *
     * @param yii\db\Query | array the base query used to fetch results
     * @param array $criteria
     *
     * @return integer
     */

    public static function fetchCount( $query, array $criteria = null ): int
    {
        if (is_null($query)) {
            return 0;
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            if ($criteria) {
                $query = static::applyCriteriaToList($list, $criteria);
            }

            return count($query);
        }

        if ($query instanceof Query)
        {
            if ($criteria) {
                Craft::configure($query, $criteria);
            }

            return $query->count();
        }

        return 0;
    }

    /**
     * Fetches whether given query returns any results
     *
     * @param \yii\db\Query | array $query
     * @param array $criteria
     *
     * @return bool
     */

    public function fetchExists( $query, array $criteria = null ): bool
    {
        if (empty($query)) {
            return false;
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            if ($criteria) {
                $query = static::applyCriteriaToList($query, $criteria);
            }

            return (count($query) > 0);
        }

        if ($query instanceof Query)
        {
            if ($criteria) {
                Craft::configure($query, $criteria);
            }

            return $query->exists();
        }

        // Accept single result
        if (is_object($query) || is_array($query)) {
            return true;
        }

        return false;
    }

    /**
     * Fetches one of given query's results.
     * Supports eager-loaded list of values.
     *
     * @param yii\db\Query | array the base query used to fetch results
     * @param array $criteria
     *
     * @return mixed
     */

    public static function fetchOne( $query, array $criteria = null )
    {
        if (empty($query)) {
            return null;
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            if ($criteria) {
                $query = static::applyCriteriaToList($query, $criteria);
            }

            return ArrayHelper::firstValue($query);
        }

        if ($query instanceof Query)
        {
            if ($criteria) {
                Craft::configure($query, $criteria);
            }

            $result = $query->one();

            if (Craft::$app->getPlugins()->isPluginInstalled('neo')
                && $query instanceof \benf\neo\elements\db\BlockQuery)
            {
                $result->useMemoized($query);
            }

            return $result;
        }

        // Accept a single result
        if (is_object($query) || is_array($query)) {
            return $query;
        }

        return null;
    }

    /**
     * Alias for `fetchOne()`
     */

    public static function fetchFirst( $query, array $criteria = null )
    {
        return static::fetchOne($query, $criteria);
    }

    /**
     * Fetches last of given query's results.
     * Supports eager-loaded list of values.
     *
     * @param yii\db\Query | array the base query used to fetch results
     * @param array $criteria
     *
     * @return mixed
     */

    public static function fetchLast( $query, array $criteria = null )
    {
        if (is_null($query)) {
            return null;
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            if ($criteria) {
                $query = static::applyCriteriaToList($query, $criteria);
            }

            return ArrayHelper::lastValue($query);
        }

        if ($query instanceof Query)
        {
            if ($criteria) {
                Craft::configure($query, $criteria);
            }

            $count = $query->count();
            return $query->nth($count - 1);
        }

        // Accept single result
        if (is_object($query) || is_array($query)) {
            return $query;
        }

        return null;
    }

    /**
     * Fetches nth result for given query.
     * Supports eager-loaded list of values.
     *
     * @param yii\db\Query | array the abse query used to fetch results
     * @param int $index position of result to fetch
     * @param array $criteria
     *
     * @return mixed
     */

    public static function fetchNth( $query, int $index, array $criteria = null )
    {
        if (is_null($query)) {
            return null;
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            if ($criteria) {
                $query = static::applyCriteriaToList($query, $criteria);
            }

            if (count($query) > $index) {
                return $query[$index];
            }

            return null;
        }

        // Accept single result
        if (is_object($query) || is_array($query)) {
            return $query;
        }

        return null;
    }

    /**
     * Filters given list of conditions, removing conditions that
     * check against empty values.
     *
     * @param array $conditions
     *
     * @return array
     */

    public static function filterConditions( array $conditions ): array
    {
        $res = [];

        foreach ($conditions as $key => $value)
        {
            if (is_array($value))
            {
                if (count($value = array_filter($value))) {
                    $res[$key] = $value;
                }
            }

            else if (!empty($value)) {
                $res[$key] = $value;
            }
        }

        return $res;
    }

    /**
     *
     */

    public static function whereMultiple( array $query, array $conditions, bool $strict = false )
    {
        $res = [];

        foreach ($query as $data)
        {
            if (static::checkProperties($data, $conditions, $strict)) {
                $res[] = $data;
            }
        }

        return $res;
    }

    /**
     *
     */

    public static function firstWhereMultiple( array $query, array $conditions, bool $strict = false )
    {
        foreach ($query as $data)
        {
            if (static::checkProperties($query, $conditions, $strict)) {
                return $data;
            }
        }

        return null;

        // foreach ($query as $data)
        // {
        //     foreach ($conditions as $key => $value)
        //     {
        //         // skip this condition if there is no comparison value
        //         if (is_array($value) && !count($value)) {
        //             continue;
        //         }

        //         $dataValue = static::prop($data, $key);

        //         // Skip this element if there are multiple options and none of them match
        //         if (is_array($value) && !in_array($dataValue, $value, $strict)) {
        //             continue 2;
        //         }

        //         // Skip this element if the value does not match
        //         if (!is_array($value) && (($strict && $dataValue !== $value) || (!$strict && $dataValue != $value))) {
        //             continue 2;
        //         }
        //     }

        //     // return first element which matched all conditions
        //     return $element;
        // }

        // return null;
    }

    /**
     * Checks if an object or associative array matches given property conditions.
     *
     * @param array | object $data object or associative array to check
     * @param array $conditions map of property values to check against
     * @param bool $strict whether it should use strict value comparison
     *
     * @return bool
     */

    public static function checkProperties( $data, array $conditions, bool $strict = false ): bool
    {
        foreach ($conditions as $key => $value)
        {
            if (!static::checkProperty($data, $key, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if an object or associative array matches given property condition
     * 
     * @param object | array $data object or associative array to check
     * @param string | array $key property name to check against
     * @param mixed $value value, or list of values to m
     *
     * @return bool
     */

    public static function checkProperty( $data, $key, $value, bool $strict = false ): bool
    {
        $dataValue = static::prop($data, $key, $allowEmpty);

        if (is_array($value)) {
            return in_array($dataValue, $value, $strict);
        }

        else if ($strict) {
            return ($dataValue === $value);
        }

        else {
            return ($dataValue == $value);
        }

        return true;
    }

    /**
     *
     */

    public static function isSame( $left, $right ): bool
    {
        // optimise for strictly equal values
        if ($left === $right) return true;

        $isObjectLeft = is_object($left);
        $isObjectRight = is_object($right);
        $isArrayLeft = false;
        $isArrayRight = false;
        $isAssocLeft = false;
        $isAssocRight = false;

        if ($isObjectLeft && $isObjectRight)
        {
            // objects must be of same class to be similar
            if (get_class($left) != get_class($right)) return false;

            // convert objects to arrays for member comparison
            $left = (array)$left;
            $right =(array)$right;
            $isArrayLeft = $isArrayRight = $isAssocLeft = $isAssocRight = true;
        }

        // object can not be similar to array (which is what we compare below)
        else if ($isObjectLeft || $isObjectRight) {
            return false;
        }

        else {
            $isArrayLeft = is_array($left);
            $isArrayRight = is_array($right);
        }

        if ($isArrayLeft && $isArrayRight)
        {
            // if one of the arrays has more items than the other => not similar
            if (count($left) != count($right)) {
                return false;
            }

            foreach ($left as $leftKey => $leftValue )
            {
                if (is_int($leftKey))
                {
                    foreach ($right as $rightKey => $rightValue)
                    {
                        if (is_int($rightKey) && static::isSame($leftValue, $rightValue)) {
                            continue 2; // check next left value
                        }
                    }

                    // left value not found in right array => not similar
                    return false;
                }

                else if (!array_key_exists($leftKey, $right)
                    || static::isSame($leftValue, $right[$leftKey]) === false
                ) {
                    // right array does not have same value for this key
                    return false;
                }
            }

            // all left values found in right array => similar
            return true;
        }

        // not equal nor 2x objects nor 2x arrays => simply not the same
        return false;
    }

    /**
     * Applies given query criteria to list of results. Useful to filter
     * results of an eager-loaded query.
     *
     * @param array $list
     * @param array $criteria
     *
     * @return array
     */

    public static function applyCriteriaToList( array $list, array $criteria = null ): array
    {
        $select = ArrayHelper::remove($criteria, 'select'); // x
        $addSelect = ArrayHelper::remove($criteria, 'addSelect'); // x
        $distinct = ArrayHelper::remove($criteria, 'distinct'); // x
        $unique = ArrayHelper::remove($criteria, 'unique'); // x

        $site = ArrayHelper::remove($criteria, 'site'); // v
        $preferSites = ArrayHelper::remove($criteria, 'preferSites'); // x
        $dateCreated = ArrayHelper::remove($criteria, 'dateCreated'); // x
        $dateUpdated = ArrayHelper::remove($criteria, 'dateUpdated'); // x
        $postDate = ArrayHelper::remove($criteria, 'postDate'); // x
        $expiryDate = ArrayHelper::remove($criteria, 'expiryDate'); // x
        $anyStatus = ArrayHelper::remove($criteria, 'anyStatus'); // v

        $where = ArrayHelper::remove($criteria, 'where'); // v
        $andWhere = ArrayHelper::remove($criteria, 'andWhere'); // v
        $orWhere = ArrayHelper::remove($criteria, 'orWhere'); // x
        $filterWhere = ArrayHelper::remove($criteria, 'filterWhere'); // v
        $andFilterWhere = ArrayHelper::remove($criteria, 'andFilterWhere'); // v
        $orFilterWhere = ArrayHelper::remove($criteria, 'orFilterWhere'); // x

        $relatedTo = ArrayHelper::remove($criteria, 'relatedTo'); // x
        $search = ArrayHelper::remove($criteria, 'search'); // x

        $with = ArrayHelper::remove($criteria, 'with'); // x
        $withTransforms = ArrayHelper::remove($criteria, 'withTransforms'); // !

        $offset = ArrayHelper::remove($criteria, 'offset'); // v
        $limit = ArrayHelper::remove($criteria, 'limit'); // v

        $groupBy = ArrayHelper::remove($criteria, 'groupBy'); // x
        $addGroupBy = ArrayHelper::remove($criteria, 'addGroupBy'); // x

        $orderBy = ArrayHelper::remove($criteria, 'orderBy'); // v
        $inReverse = ArrayHelper::remove($criteria, 'inReverse'); // v

        // if ($select || $addSelect || $distinct || $unique
        //     || $preferSites || $dateCreated || $dateUpdated || $postDate || $expiryDate
        //     || $orWhere || $orFilterWhere
        //     || $relatedTo || $search
        //     || $with || $withTransforms
        //     || $groupBy || $addGroupBy
        // ) {
        //     throw new InvalidArgumentException('Can not apply all criterias to list of query results.');
        // }

        // localization criteria
        if ($site)
        {
            if (is_array($site))
            {
                $siteHandle = array_filter($site, function($s) { return (is_string($s) && !is_numeric($s)); });
                $siteId = array_filter($site, function($s) { return is_numeric($s); });
                $siteModels = array_filter($site, function($s) { return ($s instanceof Site); });
                $siteId = array_merge($siteId ?? [], ArrayHelper::getColumn($siteModels, 'id'));

                if (!empty($siteHandle)) { $criteria['site.handle'] = $siteHandle; }
                if (!empty($siteId)) { $criteria['siteId'] = $siteId; }
            }

            else if (is_numeric($site)) {
                $criteria['siteId'] = $site;
            }

            else if (is_string($site)) {
                $criteria['site.handle'] = $site;
            }

            else if ($site instanceof Site) {
                $criteria['siteId'] = $site->id;
            }
        }

        // status criteria
        if ($anyStatus) {
            ArrayHelper::remove($criteria, 'status'); // remove existing status criteria
        }

        // conditional criteria
        if ($filterWhere) { $where = array_merge($where ?? [], static::filterConditions($filterWhere)); }
        if ($andWhere) { $where = array_merge($where ?? [], $andWhere); }
        if ($andFilterWhere) { $where = array_merge($where ?? [], $andFilterWhere); }

        if ($where) {
            $criteria = array_merge($criteria, $where);
        }

        $list = ArrayHelper::whereMultiple($list, $criteria);

        // segmenting criteria
        if ($offset && $limit) {
            $list = array_slice($list, $offset, $limit);
        } else if ($offset) {
            $list = array_slice($list, $offset);
        } else if ($limit) {
            $list = array_slice($list, 0, $limit);
        }

        // ordering criteria
        if ($orderBy)
        {
            $orderBy = explode(' ', $orderBy);
            $key = $orderBy[0];
            $dir = SORT_ASC;

            if (count($orderBy) > 1 && $orderBy[1] == 'desc') {
                $dir = $orderBy[1] == 'asc' ?  : SORT_DESC;
            }

            $list = ArrayHelper::multisort($list, $key, $dir);
        }

        if ($inReverse) {
            $list = array_reverse($list);
        }

        return $list;
    }

    /**
     * Reduces given list of filterable items by indexing and computing the
     * criteria that are relevant for each indexed result.
     *
     * @param array $items The filterable list of items
     * @param string $index The key used to index results
     * @param string $criteria The key under which item criteria can be found
     * @param array $columns The keys for item colums to retain in results
     *
     * @todo: support anonymous function to determine index dynamically
     * @todo: support picking criteria from item keys (given list of criteria key(path)s)
     *
     * @return array
     */

    public static function filterableData( array $items, string $index, string $criteria, array $columns = null ): array
    {
        $results = [];
        $allCriteria = [];

        foreach ($items as $item)
        {
            $itemIndex = ArrayHelper::getValue($item, $index);
            $itemCriteria = ArrayHelper::getValue($item, $criteria) ?? [];

            // collect column for first mathing item
            $itemColumns = empty($columns) ? arra_keys($item) : $columns;
            $result = $results[$itemIndex] ?? ArrayHelper::filter($item, $columns);

            // collect unique criteria values
            $resultCriteria = $result[$criteria] ?? [];

            foreach ($itemCriteria as $name => $itemValue)
            {
                if (!is_array($itemValue)) $itemValue = [ $itemValue ];

                $values = $resultCriteria[$name] ?? [];
                $allValues = $allCriteria[$name] ?? [];

                foreach ($itemValue as $val)
                {
                    if (!in_array($val, $values)) $values[] = $val;
                    if (!in_array($val, $allValues)) $allValues[] = $val;
                }

                $resultCriteria[$name] = $values;
                $allCriteria[$name] = $allValues;
            }

            $result[$criteria] = $resultCriteria;
            $results[$itemIndex] = $result;
        }

        // reduce resulting criteria
        foreach ($results as $key => $result)
        {
            $resultCriteria = $result[$criteria];

            foreach ($resultCriteria as $name => $values)
            {
                $count = count($values);
                $totalCount = count($allCriteria[$name]);

                if ($count == 0 || $count == $totalCount) {
                    ArrayHelper::remove($resultCriteria, $name);
                } else if ($count == 1) {
                    $resultCriteria[$name] = ArrayHelper::firstValue($values);
                }
            }

            $result[$criteria] = $resultCriteria;
            $results[$key] = $result;
        }

        return $results;
    }

    // =Protected Methods
    // -------------------------------------------------------------------------

    // =Private Methods
    // -------------------------------------------------------------------------

    /** 
     * Work around bug with eager-loaded neo queries returning duplicate blocks
     * @link https://github.com/spicywebau/craft-neo/issues/387
     * 
     * @param array $blocks
     * 
     * @return array
     */

    private static function _cleanDuplicateNeoBlocks( $blocks ): array
    {
        $uniqueIds = [];
        $cleanBlocks = [];

        foreach ($blocks as $block)
        {
            if (Craft::$app->getPlugins()->isPluginEnabled('neo')
                && $block instanceof \benf\neo\elements\Block)
            {
                if (!in_array($block->id, $uniqueIds)) {
                    $uniqueIds[] = $block->id;
                    $cleanBlocks[] = $block;
                }
            }

            else {
                $cleanBlocks[] = $block;
            }
        }

        return $cleanBlocks;
    }

}