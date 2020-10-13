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

    public function getFunctions()
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

            // // {{ get_node_attrs(key) }}
            // new TwigFunction('get_node_attrs', [$this, 'getNodeAttributes'], [
            //     'needs_context' => true,
            // ]),

            // // {{ set_node_attrs(key[, value ]) }}
            // new TwigFunction('set_node_attrs', [$this, 'setNodeAttributes'], [
            //     'needs_context' => true,
            // ]),

            // // {{ add_node_attrs(key[, value, $merge ]) }}
            // new TwigFunction('add_node_attrs', [$this, 'addNodeAttributes'], [
            //     'needs_context' => true,
            // ]),

            // // {{ node_attrs(key[, value ]) }}
            // new TwigFunction('node_attrs', [$this, 'nodeAttributes'], [
            //     'needs_context' => true,
            // ]),

        ];
    }

    /**
     * @inheritdoc
     */

    public function getFilters()
    {
        return [
            new TwigFilter('replaceTag', [$this, 'replaceTag'], [
            ]),
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

    public function getType( $value )
    {
        return gettype($value);
    }

    /**
     * Returns the full classnames of given PHP object. It's acts like a
     * wrapper around PHP's builtin `get_classname` function, with the
     * difference that it will return `null` if given value is not an object.
     *
     * @param mixed $value
     *
     * @return string | null
     */

    public function getClass( $object )
    {
        if (!is_object($object)) {
            return null;
        }

        return get_classname($object);
    }

    /**
     * Gets value for given property or key of an object or associative array.
     * Supports nested keys using dot notation, and an array of preferred keys
     * to return the first non-null (or optionally non-empty) value.
     *
     * @param object | array $data
     * @param string | array $key
     * @param bool $allowEmpty
     *
     * @return mixed
     */

    public function prop( $data, $key, $allowEmpty = true )
    {
        return DataHelper::prop($data, $key, $allowEmpty);
    }

    /**
     * Shortcut to execute a database Query fetch operation on given object or 
     * associative array property value. Supports eager-loaded list of values,
     * by filtering items as expected by given fetch method.
     *
     * @param array | object $data object or associative array to access
     * @param string | array $key name of property on which to run the fetch method
     * @param bool $allowEmpty (optional) whether empty property values can be considered
     * @param string $method Fetch method to use
     * @param array $criteria Query criteria to apply
     * @param args.. arguments passed to the fetch method (after property value)
     *
     * @return mixed Fetch results
     */

    public function fetchProp( $data, $key, $allowEmpty, $method = null )
    {
        return forward_static_call_array([DataHelper::class, 'fetchProp'], func_get_args());
    }

    /**
     * Fetches all resutlfs for given query.
     * Supports eager-loaded list of values.
     *
     * @param yii\db\Query | array $query the base query used to fetch results
     * @param array $criteria
     *
     * @return array
     */

    public function fetchAll( $query, array $criteria = null ): array
    {
        return DataHelper::fetchAll($query, $criteria);
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

    public function fetchCount( $query, array $criteria = null ): int
    {
        return DataHelper::fetchCount($query, $criteria);
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
        return DataHelper::fetchExists($query, $criteria);
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

    public function fetchOne( $query, array $criteria = null )
    {
        return DataHelper::fetchOne($query, $criteria);
    }

    /**
     * Alias for `fetchOne()`
     */

    public function fetchFirst( $query, array $criteria = null )
    {
        return DataHelper::fetchFirst($query, $criteria);
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

    public function fetchLast( $query, array $criteria = null )
    {
        return DataHelper::fetchLast($query, $criteria);
    }

    /**
     * Fetches nth result for given query.
     * Supports eager-loaded list of values.
     *
     * @param yii\db\Query | array the abse query used to fetch results
     * @param array $criteria
     *
     * @return mixed
     */

    public function fetchNth( $query, int $index, array $criteria = null ): array
    {
        return DataHelper::fetchNth($query, $index, $criteria);
    }

    /**
     *
     */

    public function snippets( string $position )
    {
        $markup = Tailor::$plugin->markup->renderSnippets( $position );
        return $markup;
    }

    /**
     *
     */

    public function addSnippet( string $position, string $path, array $vars = null, bool $unique = false )
    {
        return Tailor::$plugin->markup->addSnippet($position, $path, $vars, $unique);
    }

    /**
     * Callable for twig `pathmask` function, which resolves given path mask
     *
     * @param string $mask
     * @param mixed $object
     * @param array $vars
     *
     * @return string | array
     */

    public function pathmask( string $mask, $object = null, array $vars = [] )
    {
        return Tailor::$plugin->pathmasks->resolvePathmask($mask, $object, $vars);
    }

    /**
     *
     */

    public function replaceTag( string $markup, string $tag, string $replacement, bool $preserveAttrs = true )
    {
        return MarkupHelper::replaceTag($markup, $tag, $replacement, $preserveAttrs);
    }

    /**
     * Callable for twig `compose_classnames` function, which merges given
     * classnames and returns a new formatted array, WITHOUT MODIFYING THE CONTEXT
     * If given $classnames argument is a string, it will use the classname values
     * found under that key in the context.
     *
     * @param array $context
     * @param array | string $classnames
     * @param array | string $values
     *
     * @return array
     */

    public function composeClassnames( &$context, $classnames, $values = null ): array
    {
        // accept string as `$classnames` argument
        if (is_string($classnames)) {
            $classnames = $context['classnames'][$classnames] ?? [];
        }

        return MarkupHelper::composeClassnames($classnames, $values);
    }

    /**
     * Callable for twig `addClassnames` function, which adds given
     * classname values to list of registered classnames.
     * If no key is given, the values will be merged into the root
     * classnames in current context.
     *
     * Returns the new list of registered classnames for $key
     *
     * @param array &$context
     * @param string $key
     * @param string | array $values
     *
     * @return array
     */

    public function addClassnames( &$context, $key, $values = null ): array
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
     * @param string $key [null]
     *
     * @return array
     */

    public function getClassnames( &$context, $key = null )
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
     * @param Array $context
     * @param string $key
     *
     * @return String 
     */

    public function classnames( &$context, $key = 'root' )
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

    /**
     * Callable for twig function 'get_node_attrs', which sets the value for a given
     * attribute keypath
     * 
     * @param Mixed $context
     * @param string $key
     */

    public function getNodeAttributes( &$context, string $keypath )
    {
        $keypath = 'attrs.' . $keypath;
        return ArrayHelper::getValue($context, $keypath, null);
    }

    /**
     * Callable for twig function 'set_node_attrs', which sets the value for a given
     * attribute keypath (overrides whatever value was registered before)
     *
     * @param Mixed $context
     * @param string $keypath
     * @param Mixed $value
     */

    public function setNodeAttributes( &$context, string $keypath, $value )
    {

    }

    /**
     * Callable for twig function 'add_node_attrs', which adds value to given attribute
     * keypath (preserves previously registered values)
     * 
     * @param Mixed $context
     * @param string $keypath
     * @param Mixed $value
     * @param Bool | String | Array $concat
     */

    public function addNodeAttributes( &$context, string $keypath, $value, $concat = false )
    {

    }

    /**
     * Callable for twig function 'node_attrs', which outputs the attributes 
     * previously registed for given keypath
     *
     * @param Mixed $context
     * @param string $keypath
     */

    public function nodeAttrributes( &$context, string $keypath )
    {

    }

}