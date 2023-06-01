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

use Illuminate\Support\Collection;

use yii\base\InvalidArgumentException;
use yii\base\UnknownPropertyException;
use yii\db\Query;

use Craft;
use craft\models\Site;
use craft\errors\InvalidFieldException;
use craft\helpers\ArrayHelper;

use benf\neo\elements\Block as NeoBlock;
use benf\neo\elements\db\BlockQuery as NeoBlockQuery;
use craft\helpers\StringHelper;
use yii\base\BaseObject;
use yoannisj\tailor\Tailor;

/**
 *
 */

class DataHelper
{
    // =Properties
    // ========================================================================

    const PROP_ALIAS_PATTERN = '/^\+([a-zA-Z0-9_]+)(?::(.+))?$/';
    const KEY_NTH_METHOD_PATTERN = '/^nth\((\d+)\)$/';

    const ALLOW_EXISTING_PROPERTY = 'existingProperty';
    const ALLOW_NON_NULL_VALUE = 'nonNullValue';
    const ALLOW_NON_EMPTY_VALUE = 'nonEmptyValue';

    /**
     * @var List of supported fetch methods
     */

    const FETCH_METHOD_ALL = 'all';
    const FETCH_METHOD_COLLECT = 'collect';
    const FETCH_METHOD_COUNT = 'count';
    const FETCH_METHOD_EXISTS = 'exists';
    const FETCH_METHOD_IDS = 'ids';
    const FETCH_METHOD_ONE = 'one';
    const FETCH_METHOD_FIRST = 'first';
    const FETCH_METHOD_LAST = 'last';
    const FETCH_METHOD_NTH = 'nth';
    const FETCH_METHOD_SCALAR = 'scalar';
    const FETCH_METHOD_SUM = 'sum';
    const FETCH_METHOD_AVERAGE = 'average';
    const FETCH_METHOD_MIN = 'min';
    const FETCH_METHOD_MAX = 'max';
    const FETCH_METHOD_PAIRS = 'pairs';

    const MODEL_FETCH_METHODS = [
        self::FETCH_METHOD_ONE,
        self::FETCH_METHOD_FIRST,
        self::FETCH_METHOD_LAST,
        self::FETCH_METHOD_NTH,
    ];

    const SUPPORTED_FETCH_METHODS = [
        self::FETCH_METHOD_ALL,
        self::FETCH_METHOD_COLLECT,
        self::FETCH_METHOD_IDS,
        self::FETCH_METHOD_COUNT,
        self::FETCH_METHOD_EXISTS,
        self::FETCH_METHOD_ONE,
        self::FETCH_METHOD_FIRST,
        self::FETCH_METHOD_LAST,
        self::FETCH_METHOD_NTH,
        // self::FETCH_METHOD_SCALAR,
        // self::FETCH_METHOD_SUM,
        // self::FETCH_METHOD_AVERAGE,
        // self::FETCH_METHOD_MIN,
        // self::FETCH_METHOD_MAX,
        // self::FETCH_METHOD_PAIRS,
    ];

    // =Public Methods
    // ========================================================================

    // =Property Keys
    // -------------------------------------------------------------------------

    /**
     * Parses given (list of) property key(s), and resolves included aliases,
     * as defined in the `propertyAliases` setting.
     *
     * @param string|array $keys 
     *
     * @return string[]
     */
    public static function parsePropKey(
        string|array $keys,
    ): array
    {
        $parsed = [];
        $aliases = null;

        // support comma-separated list of keys
        if (is_string($keys)) $keys = StringHelper::split($keys);

        foreach ($keys as $key)
        {
            if (is_string($key)) {
                $key = static::parsePropKeyPath($key);
            }

            else if (!is_array($key))
            {
                throw new InvalidArgumentException(
                    "Argument #1 `key` must be a string or array of strings");
            }

            $parsed = array_merge($parsed, array_map([self::class, 'parsePropKey'], $key));
        }

        return $parsed;
    }

    /**
     * Parses a property key path (i.e. property key expressions nested
     * in dot-notation) into ordered list of resolved key paths.
     *
     * @param string $keypath 
     *
     * @return array
     */
    private static function parsePropKeyPath( string $keypath ): array
    {
        $keys = explode('.', $keypath);
        $parsed = [];
        $aliases = null;

        foreach ($keys as $levelKey)
        {
            // resolve keys on this level
            $levelKeys = [];
            
            // resolve property alias in this key
            $matches = [];
            if (preg_match(static::PROP_ALIAS_PATTERN, $levelKey, $matches))
            {
                $alias = $matches[1];
                $method = $matches[2] ?? null;

                if ($aliases === null) {
                    $aliases = Tailor::$plugin->getSettings()->propertyAliases;
                }

                if (!array_key_exists($alias, $aliases)) {
                    throw new InvalidArgumentException("Could not find property alias '$alias'");
                }

                $levelKeys = static::parsePropKey($aliases[$alias]);
                
                if ($method)
                {
                    // inject method in each aliased key
                    $levelKeys = array_map(function($k) use ($method) {
                        $k = explode(':', $k)[0]; // replace any default method
                        return $k.':'.$method; 
                    }, $levelKeys);
                }
            }
            
            else {
                $levelKeys = [ $levelKey ];
            }
            
            // inject back into list of resolved paths
            $paths = [];

            foreach ($parsed as $path)
            {
                foreach ($levelKeys as $levelKey) {
                    $paths[] = $path.'.'.$levelKey;
                }
            }

            $parsed = $paths;
        }

        return $parsed;
    }

    /**
     * Parses property key expression (e.g. 'key:method(arg)')
     *
     * @param string $keyMethod The method to parse (after the ':' character in the full property key)
     * @param string $key The key to which the parsed method is attached (before the ':' character)
     *
     * @return array with keys 'property', 'method', 'args'
     */
    public static function parsePropKeyExpression( string $expression )
    {
        $parts = explode(':', $expression);
        $property = $parts[0];
        $keyMethod = $parts[1] ?? null;

        if ($keyMethod) {
            return static::_parseKeyMethod($keyMethod, $property);
        }

        return $parsed = [
            'property' => $property,
            'method' => null,
            'args' => null,
        ];
    }

    // =Property Values
    // -------------------------------------------------------------------------

    /**
     * Returns value contained by given property key in data object or
     * associative array.
     * 
     * The `$key` argument supports property key aliases, dot notation, key
     * expressions, and any combination thereof.
     * 
     * If $key resolves to a list of keys, this will return the value for the
     * first key holding one allowed by `$options`.
     * 
     * The `$options` argument supports the following keys:
     * - 'asText' Whether property value should be casted to text
     * - 'allowEmpty' Whether empty value should be returned
     * - 'fetchMethod' If and how to fetch model value(s) from the DB
     * - 'fetchArgs' Arguments passed on to the model fetching method
     * 
     * Note: if '$key' is using a dot-notation to check nested properties, the
     * 'fetchMethod' and 'fetchArgs' options apply to the deepest property only.
     * 
     * @see [[static::parsePropKey]]
     *
     * @param array|object $data 
     * @param string $key 
     * @param array $options 
     *
     * @return mixed
     */
    public static function propertyValue(
        array|object $data,
        string|array $key,
        array $options
    ): mixed
    {
        $asText = $options['asText'] ?? false;
        $allowEmpty = $options['allowEmpty'] ?? false;

        // support key aliases, as well as a list of preferred keys
        $keys = static::parsePropKey($key);

        foreach ($keys as $key)
        {
            $foundKey = false;
            $value = static::_checkPropertyValue($data, $key, $options, $foundKey);

            // cast value to string? (may result in an empty string...)
            if ($asText) {
                $value = (string)$value;
            }

            if (!$allowEmpty && empty($value)) {
                continue;
            }

            else if ($value !== null) {
                return $value; // return first key that returned an actual value
            }
        }

        return null; // no key returned any allowed value
    }

    /**
     * Returns list of values stored under given key (delegating to [[static::propertyValue]])
     * for eachitem in a set of data objects or assiciative arrays.
     * 
     * :::Tip
     * This method preserves the key for each item in $dataset
     * :::
     *
     * @param array $dataset 
     * @param string $key 
     * @param array $options 
     *
     * @return array
     */
    public static function getColumn(
        array $dataset,
        string|array $key,
        array $options = []
    ): array
    {
        $values = [];

        foreach ($dataset as $rowKey => $data) {
            $values[$rowKey] = static::propertyValue($data, $key, $options);
        }

        return $values;
    }

    // =Fetching
    // -------------------------------------------------------------------------

    /**
     * Checks whether given value is a fetchable data-set.
     *
     * @param array|object $data Value to check
     *
     * @return bool
     */
    public static function isFetchable( mixed $data ): bool
    {
        // indexed array is "fetchable"
        if (is_array($data) && isset($data[0])) return true;

        // queries and collections are fetchable
        if (is_object($data)) {
            return ($data instanceof Query || $data instanceof Collection);
        }

        return false;
    }

    /**
     * Shortcut to execute a database Query fetch operation on given object or 
     * associative array property value.
     * The `$key` argument can be a list of property names (in order of preference)
     * and supports nested property values by using dot notation.
     * If the property has been eager-loaded, the results won't be fetched again,
     * but they will be filtered to simulate the fetch `$method`.
     *
     * @param array|object $data Object or associative array to access
     * @param string|array $key Name of property on which to run the fetch method
     * @param bool $allowEmpty Whether empty property values can be considered (omittable)
     * @param string $method Fetch method to use ('one', 'all', 'count', 'exists', 'ids')
     * @param args... arguments passed to the fetch method (after property value)
     *
     * @return mixed Fetch results
     */

    public static function fetchProp(
        array|object $data, string|array $key,
        string|bool $allowEmpty,
        string $method = null 
    ): mixed
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
            throw new InvalidArgumentException('Argument `method` must be a fetch method name');
        }

        if (!in_array($method, self::SUPPORTED_FETCH_METHODS)) {
            throw new InvalidArgumentException('Fetch `method` "'.$method.'" is not supported.');
        }

        $query = static::prop($data, $key, $allowEmpty);
        $fetchMethod = 'fetch' . ucfirst($method);

        $fetchArgs = array_slice($args, $fetchArgsStart);
        array_unshift($fetchArgs, $query);

        return forward_static_call_array([ self::class, $fetchMethod ], $fetchArgs);
    }

    /**
     * Fetches all results for given query.
     * Supports collected values and eager-loaded list of values.
     *
     * @param array|Query|Collection|null $query the base query used to fetch results
     * @param array $criteria
     *
     * @return array
    **/

    public static function fetchAll(
        array|Query|Collection|null $query,
        array $criteria = null
    ): array
    {
        if (!$query) return [];

        if ($query instanceof Collection) {
            $query = $query->all(); // continue with underlying array to apply given criteria
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            $results = static::_cleanDuplicateNeoBlocks($query);

            if ($criteria) {
                $results = static::applyCriteriaToList($query, $criteria);
            }

            return $results;
        }

        if ($query instanceof Query)
        {
            if ($criteria) {
                Craft::configure($query, $criteria);
            }

            $results = $query->all();

            // enable eager loaded data for Neo Block queries
            // @link https://github.com/spicywebau/craft-neo/blob/master/docs/eager loading.md
            if (Tailor::$plugin->isNeoInstalled
                && $query instanceof NeoBlockQuery)
            {
                $results = static::_cleanDuplicateNeoBlocks($results);

                foreach ($results as $block) {
                    $block->useMemoized($results);
                }
            }

            return $results;
        }

        return [];
    }

    /**
     * Fetches results in given query and returns it in a collection.
     * Supports collected results and list of eager-loaded results.
     *
     * @param array|Query|Collection|null $query 
     * @param array|null $criteria 
     *
     * @return Collection
     */

    public static function fetchCollect(
        array|Query|Collection|null $query,
        array $criteria = null
    ): Collection
    {
        return Collection::make(static::fetchAll($query, $criteria));
    }

    /**
     * Fetches total count of given query results
     * Supports eager-loaded list of values.
     *
     * @param array|Query|Collection|null the base query used to fetch results
     * @param array $criteria
     *
     * @return integer
     */

    public static function fetchCount(
        array|Query|Collection|null $query,
        array $criteria = null
    ): int
    {
        if (empty($query)) return 0;

        if ($query instanceof Query || $query instanceof Collection) {
            $query = $query->all(); // work with underlying array to apply given criteria
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            if ($criteria) {
                $query = static::applyCriteriaToList($query, $criteria);
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
     * @param array|Query|Collection|null $query
     * @param array $criteria
     *
     * @return bool
     */

    public static function fetchExists(
        array|Query|Collection|null $query,
        array $criteria = null
    ): bool
    {
        if (empty($query)) return false;

        if ($query instanceof Collection) {
            $query = $query->all(); // work with underlying array to apply given criteria
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
     * Undocumented function
     *
     * @param array|object|null $query 
     * @param mixed $criteria 
     *
     * @return array
     */
    public static function fetchIds(
        array|object|null $query,
        array $criteria = null
    ): array
    {
        if ($query instanceof Query)
        {
            $ids = $query->ids();

            if (Tailor::$plugin->neoVersionHasDuplicatesIssue) {
                $ids = array_unique($ids);
            }

            return $ids;
        }

        $results = static::fetchAll($query, $criteria);
        return ArrayHelper::getColumn($results, 'id');
    }

    /**
     * Fetches one of given query's results.
     * Supports eager-loaded list of values.
     *
     * @param array|object|null the base query used to fetch results
     * @param array $criteria
     *
     * @return mixed
     * 
     * @todo: support scalar results
     */

    public static function fetchOne(
        array|object|null $query,
        array $criteria = null
    ): mixed
    {
        if (empty($query)) return null;

        if ($query instanceof Collection) {
            $query = $query->all(); // work with underlying array to apply given criteria
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
                && $query instanceof NeoBlockQuery)
            {
                /** @var NeoBlock $result */
                $result->useMemoized([ $result ]);
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
     * @alias for `fetchOne()`
     */

    public static function fetchFirst(
        array|object|null $query,
        array $criteria = null
    ): mixed
    {
        return static::fetchOne($query, $criteria);
    }

    /**
     * Fetches last of given query's results.
     * Supports eager-loaded list of values.
     *
     * @param array|object|null the base query used to fetch results
     * @param array $criteria
     *
     * @return mixed
     */

    public static function fetchLast(
        array|object|null $query,
        array $criteria = null
    ): mixed
    {
        if (empty($query)) return null;

        if ($query instanceof Collection) {
            $query = $query->all(); // work with underlying array to apply given criteria
        }

        if (is_array($query) && ArrayHelper::isIndexed($query))
        {
            if ($criteria) {
                $query = static::applyCriteriaToList($query, $criteria);
            }

            return ArrayHelper::firstValue(array_reverse($query));
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
     * Fetches nth result for given query. Supports eager-loaded list of values.
     *
     * @param array|object|null $query The base query used to fetch results
     * @param int $index position of result to fetch
     * @param array|null $criteria
     *
     * @return mixed
     */

    public static function fetchNth(
        array|object|null $query,
        int $index,
        array $criteria = null
    ): mixed
    {
        if (empty($query)) return null;

        if ($query instanceof Collection) {
            $query = $query->all(); // work with underlying array to apply given criteria
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
     * @todo: keep conditions mapping to empty non-null values?
     *
     * @param array $conditions
     *
     * @return array
     */

    public static function filterConditions(
        array $conditions
    ): array
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
     * @param array|Query|Collection|null $items
     * @param array $conditions
     * @param bool $strict
     * 
     * @return array
     */

    public static function whereMultiple(
        array|Query|Collection|null $items,
        array $conditions,
        bool $strict = false
    ): array
    {
        if ($items instanceof Query || $items instanceof Collection) {
            $items = $items->all();
        }

        $res = [];

        foreach ($items as $data)
        {
            if (static::checkProperties($data, $conditions, $strict)) {
                $res[] = $data;
            }
        }

        return $res;
    }

    /**
     * @param array|Query|Collection|null $items
     * @param array $conditions
     * @param bool $strict
     * 
     * @return mixed
     */

    public static function firstWhereMultiple(
        array|Query|Collection|null $items,
        array $conditions,
        bool $strict = false
    ): mixed
    {
        // if ($items instanceof Query) {
        //     return $items->andWhere($conditions)->one();
        // }

        if ($items instanceof Query || $items instanceof Collection) {
            $items = $items->all();
        }

        foreach ($items as $data)
        {
            if (static::checkProperties($items, $conditions, $strict)) {
                return $data;
            }
        }

        return null;

        // foreach ($items as $data)
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
     * @param array|object $data object or associative array to check
     * @param array $conditions map of property values to check against
     * @param bool $strict whether it should use strict value comparison
     *
     * @return bool
     */

    public static function checkProperties(
        array|object $data,
        array $conditions,
        bool $strict = false
    ): bool
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
     * @param array|object $data object or associative array to check
     * @param string|array $key property name to check against
     * @param mixed $value value, or list of values to match against
     * @param bool $strict
     *
     * @return bool
     */

    public static function checkProperty(
        array|object $data,
        string|array $key,
        mixed $value,
        bool $strict = false
    ): bool
    {
        $dataValue = static::prop($data, $key);

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
     * @param mixed $left
     * @param mixed $right
     * 
     * @return bool
     */

    public static function isSame( mixed $left, mixed $right ): bool
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

    public static function filterableData(
        array $items,
        string $index,
        string $criteria,
        array $columns = null
    ): array
    {
        $results = [];
        $allCriteria = [];

        foreach ($items as $item)
        {
            $itemIndex = ArrayHelper::getValue($item, $index);
            $itemCriteria = ArrayHelper::getValue($item, $criteria) ?? [];

            // collect column for first mathing item
            $result = ($results[$itemIndex] ??
                (empty($columns) ? $item : ArrayHelper::filter($item, $columns)));

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

    // =Deprecated
    // -------------------------------------------------------------------------

    /**
     * Returns value for given object property name or array key.
     * Supports nested properties using dot notation.
     * Accepts a list of prefered property names (will return first property that is set).
     *
     * @param object|array $data Object or associative array to access
     * @param string|array $key Name of property/array key to return
     * @param bool $allowEmpty Whether an empty value is satisfactory or not
     * 
     * @return mixed
     */

     public static function prop(
        object|array $data,
        string|array $key,
        bool $allowEmpty = true
    ): mixed
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
                $value = static::prop($data, $k, $allowEmpty);
                if ($value !== null) return $value;
            }

            return null;
        }

        try {
            $value = ArrayHelper::getValue($data, $key);
        } catch (UnknownPropertyException|InvalidFieldException $e) {
            $value = null;
        }

        if (!$allowEmpty && empty($value)) {
            return null;
        }

        return $value;
    }

    // =Protected Methods
    // ========================================================================

    // =Private Methods
    // ========================================================================

    /**
     * Checks if a property exists on given data object or associative array,
     * and returns value if it does.
     * 
     * The main use for this method is to inspect nested property keys with
     * dot-notation, and/or to fetch nested models with the 'key:method(arg)`
     * expression. For example:
     * 
     * ```php
     * $value = DataHelper::checkPropertyValue($entry, 'children.relatedEntries:nth(2))`
     * ```
     *
     * @param array|object $data 
     * @param string $key 
     * @param array $options 
     * @param bool $foundKey 
     *
     * @return mixed
     */
    private static function _checkPropertyValue(
        array|object $data,
        string $key,
        array $options,
        bool &$foundKey = false
    ): mixed
    {
        if (empty($key))
        {
            $foundKey = false;
            return null;
        }
    
        $keypath = explode('.', $key);
        $levels = count($keypath);
        $level = 0;

        while (($key = array_shift($keypath)))
        {
            $isDeepestKey = ($level == $levels - 1);
            list($property, $method, $args) = static::parsePropKeyExpression($key);

            if (is_array($data))
            {
                if (!array_key_exists($property, $data)) {
                    $foundKey = false;
                    return null;
                }

                $data = $data[$key];
            }

            // maybe previous key pointed to a scalar value
            else if (!is_object($data)) {
                $foundKey = false; // which don't have properties...
                return null;
            }

            else if ($data instanceof BaseObject)
            {
                if (!$data->canGetProperty($property, true)) {
                    $foundKey = false;
                    return null;
                }

                $data = $data->$property;
            }

            else
            {
                if (!property_exists($data, $property)) {
                    $foundKey = false;
                    return null;
                }

                $data = $data->$property;
            }

            // fetch data model along the way?
            if ($isDeepestKey)
            {
                if (!$method) $method = $options['fetchMethod'] ?? null;

                if ($method && !in_array($method, self::SUPPORTED_FETCH_METHODS)) {
                    throw new InvalidArgumentException("Invalid property fetching method '$method'");
                }

                if (!$args) $args = $options['fetchArgs'] ?? [];
            }

            if ($method && static::isFetchable($data))
            {
                $callable = [ self::class, 'fetch'.ucfirst($method)];
                $data = forward_static_call_array($callable, $args);
            }

            $level++;
        }

        $allowEmpty = $options['allowEmpty'] ?? false;

        if (!$allowEmpty && empty($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Parses fetching method from property key epression (e.g. 'key:method(args...)')
     *
     * @param string $method The method to parse from the expression (after the ':' character)
     * @param string $key The key name in the expression (before the ':' character)
     *
     * @return array
     */
    private static function _parseKeyMethod( string $method, string $property = '' ): array
    {
        $name = $method;
        $args = [];

        if (strncmp($method, 'nth', 3) === 0)
        {
            $name = substr($method, 0, 3);

            $matches = [];
            if (!preg_match(self::KEY_NTH_METHOD_PATTERN, $method, $matches)) {
                throw new InvalidArgumentException(
                    "Property key expression '{$property}:{$method}' is missing argument #1 `index`");
            }

            $keyIndex = $matches[1] ?? null;
            if (!is_numeric($keyIndex)) {
                throw new InvalidArgumentException(
                    "Argument #1 `index` in property key expression '{$property}:{$method}' must be a number");
            }

            $args[] = (int)$keyIndex;
        }

        if (!in_array($name, self::MODEL_FETCH_METHODS)) {
            throw new InvalidArgumentException(
                "Method in property key expression '{$property}:{$method}' must return a model");
        }

        return [
            'property' => $property,
            'method' => $name,
            'args' => $args,
        ];
    }

    /** 
     * Work around bug with eager-loaded neo queries returning duplicate blocks
     * @link https://github.com/spicywebau/craft-neo/issues/387
     * 
     * @param array $blocks
     * 
     * @return array
     */

    private static function _cleanDuplicateNeoBlocks( array $blocks ): array
    {
        if (Tailor::$plugin->neoVersionHasDuplicatesIssue == false) {
            return $blocks;
        }

        $uniqueIds = [];
        $cleanBlocks = [];

        foreach ($blocks as $block)
        {
            if (!($block instanceof NeoBlock)) continue;

            if (!in_array($block->id, $uniqueIds)) {
                $uniqueIds[] = $block->id;
                $cleanBlocks[] = $block;
            }

            else {
                $cleanBlocks[] = $block;
            }
        }

        return $cleanBlocks;
    }





        // /**
    //  * Checks if property key can be accessed on given data object or
    //  * associative array.
    //  * 
    //  * :::Tip
    //  * The `$key` argument supports dot-notation to check nested properties.
    //  * :::
    //  *
    //  * @param array|object $data Data to check
    //  * @param string $key Property key to check
    //  *
    //  * @return bool
    //  */
    // public static function hasProperty(
    //     array|object $data,
    //     string $key,
    //     mixed &$value = null,
    // ): bool
    // {
    //     if (empty($key)) return false;

    //     $keys = explode('.', $key);

    //     while (($key = array_shift($keys)))
    //     {
    //         if (is_array($data))
    //         {
    //             if (!array_key_exists($key, $data)) {
    //                 return false;
    //             } else {
    //                 $data = $data[$key];
    //             }
    //         }

    //         // maybe nested property holds a scalar value...
    //         else if (!is_object($data)) {
    //             return false; // which don't have properties
    //         }

    //         else if ($data instanceof BaseObject)
    //         {
    //             if (!$data->canGetProperty($key, true)) {
    //                 return false;
    //             } else {
    //                 $data = $data->$key;
    //             }
    //         }

    //         else if (!property_exists($data, $key)) {
    //             return false;
    //         }

    //         else {
    //             $data = $data->$key;
    //         }
    //     }

    //     $value = $data; // in case calling context wants to know
    //     return true;

    //     // if (is_array($data)) {
    //     //     return array_key_exists($key, $data);
    //     // }

    //     // if ($data instanceof BaseObject) {
    //     //     return $data->canGetProperty($key, true);
    //     // }

    //     // return property_exists($data, $key);
    // }

}