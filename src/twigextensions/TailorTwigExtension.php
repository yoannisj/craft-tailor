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

namespace yoannisj\tailor\twigextensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

use yii\db\Query;

use Craft;
use craft\helpers\ArrayHelper;

use yoannisj\tailor\Tailor;
use yoannisj\tailor\helpers\DataHelper;
use yoannisj\tailor\helpers\MarkupHelper;

/**
 *
 */

class TailorTwigExtension extends AbstractExtension
{
    // =Static
    // ========================================================================

    // =Functions
    // ========================================================================

    /**
     * @inheritdoc
     */
    public function getFunctions(): array
    {
        return [

            // {{ getType(value) }}
            new TwigFunction('getType', [$this, 'getType'], []),

            // {{ getClass(value) }}
            new TwigFunction('getClass', [$this, 'getClass'], []),

            // {{ prop( query ) }}
            new TwigFunction('prop', [$this, 'prop'], []),

            // {{ fetchAll( query, criteria ) }}
            new TwigFunction('fetchAll', [$this, 'fetchAll'], []),

            // {{ fetchCount( query, criteria) }}
            new TwigFunction('fetchCount', [$this, 'fetchCount'], []),

            // {{ fetchExists( query, criteria) }}
            new TwigFunction('fetchExists', [$this, 'fetchExists'], []),

            // {{ fetchOne( query, criteria ) }}
            new TwigFunction('fetchOne', [$this, 'fetchOne'], []),

            // {{ fetchFirst( query, criteria ) }}
            new TwigFunction('fetchFirst', [$this, 'fetchFirst'], []),

            // {{ fetchLast( query, criteria ) }}
            new TwigFunction('fetchLast', [$this, 'fetchLast'], []),

            // {{ fetchNth( query, index, criteria ) }}
            new TwigFunction('fetchNth', [$this, 'fetchNth'], []),

            // {{ fetchProp( data, key, method, … ) }}
            new TwigFunction('fetchProp', [$this, 'fetchProp'], []),

            //  {{ snippets(name) }}
            new TwigFunction('snippets', [$this, 'snippets'], []),

            //  {{ snippets(name) }}
            new TwigFunction('addSnippet', [$this, 'addSnippet'], []),

            // {{ pathmask(mask[, object, vars]) }}
            new TwigFunction('pathmask', [$this, 'pathmask'], []),
            
            // {{ addClassnames([key,] value) }}
            new TwigFunction('addClassnames', [$this, 'addClassnames'], [
                'needs_context' => true,
            ]),

            // {{ getClassnames([key, value]) }}
            new TwigFunction('getClassnames', [$this, 'getClassnames'], [
                'needs_context' => true,
            ]),

             // {{ composeClassnames(key|classnames, value) }}
            new TwigFunction('composeClassnames', [$this, 'composeClassnames'], [
                'needs_context' => true,
            ]),

            // {{ classnames([key]) }}
            new TwigFunction('classnames', [$this, 'classnames'], [
                'needs_context' => true,
            ]),

        ];
    }

    /**
     * @inheritdoc
     */

    public function getFilters(): array
    {
        return [
            new TwigFilter('replaceTag', [$this, 'replaceTag'], []),
        ];
    }

    // =Callables
    // ========================================================================

    /**
     * Returns the PHP type of given value. It's a wrapper around
     * PHP's builtin `gettype` function
     *
     * @param mixed $value
     *
     * @return string
     */

    public function getType( mixed $value ): string
    {
        return gettype($value);
    }

    /**
     * Returns the full classnames of given PHP object. It's acts like a
     * wrapper around PHP's builtin `get_class` function, with the difference
     * that it will return `null` if given value is not an object.
     *
     * @param mixed $object
     *
     * @return string|null
     */

    public function getClass( mixed $object ): ?string
    {
        if (!is_object($object)) {
            return null;
        }

        return get_Class($object);
    }

    /**
     * Gets value for given property or key of an object or associative array.
     * Supports nested keys using dot notation, and an array of preferred keys
     * to return the first non-null (or optionally non-empty) value.
     *
     * @param object|array $data
     * @param string|array $key
     * @param bool $allowEmpty
     *
     * @return mixed
     */

    public function prop( object|array $data, string|array $key,
        bool $allowEmpty = true ): mixed
    {
        return DataHelper::prop($data, $key, $allowEmpty);
    }

    /**
     * Shortcut to execute a database Query fetch operation on given object or 
     * associative array property value. Supports eager-loaded list of values,
     * by filtering items as expected by given fetch method.
     *
     * @param array|object $data object or associative array to access
     * @param string|array $key name of property on which to run the fetch method
     * @param bool $allowEmpty (optional) whether empty property values can be considered
     * @param string|null $method Fetch method to use
     * @param array $criteria Query criteria to apply
     * @param args.. arguments passed to the fetch method (after property value)
     *
     * @return mixed Fetch results
     */

    public function fetchProp( object|array $data, string|array $key,
        bool $allowEmpty = true, string $method = null ): mixed
    {
        return forward_static_call_array([DataHelper::class, 'fetchProp'], func_get_args());
    }

    /**
     * Fetches all resutlfs for given query.
     * Supports eager-loaded list of values.
     *
     * @param array|Query|null $query the base query used to fetch results
     * @param array|null $criteria
     *
     * @return array
     */

    public function fetchAll( array|Query|null $query, array $criteria = null ): array
    {
        return DataHelper::fetchAll($query, $criteria);
    }

    /**
     * Fetches total count of given query results
     * Supports eager-loaded list of values.
     *
     * @param array|Query|null the base query used to fetch results
     * @param array $criteria
     *
     * @return integer
     */

    public function fetchCount( array|Query|null $query, array $criteria = null ): int
    {
        return DataHelper::fetchCount($query, $criteria);
    }

    /**
     * Fetches whether given query returns any results
     *
     * @param array|Query|null $query
     * @param array|null $criteria
     *
     * @return bool
     */

    public function fetchExists( array|Query|null $query, array $criteria = null ): bool
    {
        return DataHelper::fetchExists($query, $criteria);
    }

    /**
     * Fetches one of given query's results.
     * Supports eager-loaded list of values.
     *
     * @param array|object|null the base query used to fetch results
     * @param array|null $criteria
     *
     * @return mixed
     */

    public function fetchOne( array|object|null $query, array $criteria = null ): mixed
    {
        return DataHelper::fetchOne($query, $criteria);
    }

    /**
     * @alias for `fetchOne()`
     */

    public function fetchFirst( array|object|null $query, array $criteria = null ): mixed
    {
        return DataHelper::fetchFirst($query, $criteria);
    }

    /**
     * Fetches last of given query's results.
     * Supports eager-loaded list of values.
     *
     * @param array|object|null $query The base query used to fetch results
     * @param array $criteria
     *
     * @return mixed
     */

    public function fetchLast( array|object|null $query, array $criteria = null ): mixed
    {
        return DataHelper::fetchLast($query, $criteria);
    }

    /**
     * Fetches nth result for given query. Supports eager-loaded list of values.
     *
     * @param array|object|null $query the base query used to fetch results
     * @param array|null $criteria
     *
     * @return mixed
     */

    public function fetchNth( array|object|null $query, int $index, array $criteria = null ): array
    {
        return DataHelper::fetchNth($query, $index, $criteria);
    }

    /**
     * @see `craft\tailor\services\Markup::renderSnippets()`
     */

    public function snippets( string $position ): string
    {
        return Tailor::$plugin->markup->renderSnippets( $position );
    }

    /**
     * @see `craft\tailor\services\Markup::addSnippet()`
     */

    public function addSnippet(
        string $position, string $path, array $vars = null, bool $unique = false ): bool
    {
        return Tailor::$plugin->markup->addSnippet($position, $path, $vars, $unique);
    }

    /**
     * @see `Pathmasks::resolvePathmask()`
     */

    public function pathmask(
        string $mask, $object = null, array $vars = [] ): string|array
    {
        return Tailor::$plugin->pathmasks->resolvePathmask($mask, $object, $vars);
    }

    /**
     * @see `craft\tailor\helpers\MarkupHelper::resolvePathmask()`
     */

    public function replaceTag(
        string $markup, string $tag, string $replacement, bool $preserveAttrs = true ): string
    {
        return MarkupHelper::replaceTag($markup, $tag, $replacement, $preserveAttrs);
    }

    /**
     * Callable for twig `compose_classnames` function, which merges given
     * classnames and returns a new formatted array, WITHOUT MODIFYING THE CONTEXT
     * If given $classnames argument is a string, it will use the classname values
     * found under that key in the context.
     *
     * @param array $context Current Twig context (i.e. variables)
     * @param string|array $classnames Classnames to compose
     * @param string|array|null $values Classname values when composing a sub-key (i.e. node)
     *
     * @return array
     */

    public function composeClassnames(
        array &$context, string|array $classnames, string|array|null $values = null ): array
    {
        // accept string as `$classnames` argument
        if (is_string($classnames)) {
            $classnames = $context['classnames'][$classnames] ?? [];
        }

        return MarkupHelper::composeClassnames($classnames, $values);
    }

    /**
     * Callable for twig `addClassnames` function, which adds given classname
     * values to list of registered classnames. If no key is given, the values
     * will be merged into the root classnames in current context.
     *
     * Returns the new list of registered classnames for $key
     *
     * @param array &$context
     * @param string|array $key
     * @param string|array|null $values
     *
     * @return array
     */

    public function addClassnames(
        array &$context, string|array $key, string|array|null $values = null ): array
    {
        // allow omitting the `$key` argument
        if (is_null($values))
        {
            $values = $key;
            $key = null;
        }

        // make sure we are working with classname maps
        $classnames = MarkupHelper::parseClassMap($context['classnames'] ?? []);
        $values = MarkupHelper::parseClassMap($values) ?? [];

        if ($key && $key != 'root') {
            // scope added values to given key
            $values = [ $key => $values ];
        }

        // merge added classnames into context
        $context['classnames'] = array_merge_recursive($classnames, $values);

        // return modified classnames key, or complete modified classnames map
        return $key ? $context['classnames'][$key] : $context['classnames'];
    }

    /** 
     * Callable for twig `getClassnames` function, which returns the classname
     * values registered for given key (supports nested keys with '.' notation)
     * If key is not given, it will return all classnames registered in current
     * context. 
     *
     * @param array $context
     * @param string|null $key [null]
     *
     * @return array
     */

    public function getClassnames( array &$context, string|null $key = null ): array
    {
        $classnames = $context['classnames'] ?? [];

        if ($key === null) {
           return MarkupHelper::parseClassMap($classnames);
        }

        if (is_array($classnames))
        {
            $classnames = ArrayHelper::getValue($classnames, $key);
            return MarkupHelper::parseClassMap($classnames);
        }

        if (is_string($classnames))
        {
            return [
                'root' => $classnames
            ];
        }

        return null;
    }

    /** 
     * Callable for twig `classnames` function, which returns the html attribute
     * value for classnames registered for given key (supports nested keys with
     * '.' notation).
     * If no key is given, it will return 'root' classnames from current context
     *
     * @param array $context
     * @param string $key
     *
     * @return string|null
     */

    public function classnames( array &$context, string $key = 'root' ): ?string
    {
        if ($classnames = $this->getClassnames($context, $key))
        {
            if (is_array($classnames))
            {
                if (ArrayHelper::isAssociative($classnames)) {
                    $classnames = array_key_exists('root', $classnames) ? $classnames['root'] : null;
                }

                if (ArrayHelper::isIndexed($classnames)) {
                    return implode(' ', $classnames);
                }
            }

            if (is_string($classnames)) {
                return $classnames;
            }
        }

        return null;
    }

}