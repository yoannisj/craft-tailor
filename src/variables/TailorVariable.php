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

namespace yoannisj\tailor\variables;

use Craft;
use craft\base\ElementInterface;
use craft\base\BlockElementInterface;
use craft\db\Query;
use craft\elements\Globalset;
use craft\elements\Entry;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Tag;
use craft\elements\MatrixBlock;
use craft\elements\User;

use yoannisj\tailor\Tailor;
use yoannisj\tailor\helpers\ParamHelper;

/**
 *
 */

class TailorVariable
{
    /**
     * @return bool
     */

    public function isQuery( $value ): bool
    {
        return ($value instanceof Query);
    }

    /**
     * @return bool
     */

    public function isElement( $value ): bool
    {
        return ($value instanceof ElementInterface);
    }

    /**
     * @return bool
     */

    public function isBlock( $value ): bool
    {
        return ($value instanceof BlockElementInterface);
    }

    /**
     * @return bool
     */

    public function isGlobalset( $value ): bool
    {
        return ($value instanceof Globalset);
    }

    /**
     * @return bool
     */

    public function isEntry( $value ): bool
    {
        return ($value instanceof Entry);
    }

    /**
     * @return bool
     */

    public function isAsset( $value ): bool
    {
        return ($value instanceof Asset);
    }

    /**
     * @return bool
     */

    public function isCategory( $category ): bool
    {
        return ($value instanceof Category);
    }

    /**
     * @return bool
     */

    public function isTag( $value ): bool
    {
        return ($value instanceof Tag);
    }

    /**
     * @return bool
     */

    public function isMatrixBlock( $value ): bool
    {
        return ($value instanceof MatrixBlock);
    }

    /**
     * @return bool
     */

    public function isUser( $value ): bool
    {
        return ($value instanceof User);
    }

    /**
     * @return string
     */

    public function getType( $value )
    {
        return gettype($value);
    }

    /**
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
     * 
     */

    public function parseParams( $params )
    {
        return ParamHelper::parseParams($params);
    }

    /**
     * 
     */

    public function getRequestParams( array $paramNames, $defaults = null )
    {
        if (!is_array($defaults))
        {
            $defaultParams = [];

            foreach ($paramNames as $name) {
                $defaultParams[$name] = $defaults;
            }

            $defaults = $defaultParams;
        }

        $request = Craft::$app->getRequest();
        $params = [];

        foreach ($paramNames as $name) {
            $params[$name] = $request->getParam($name, $defaults[$name]);
        }

        return $params;
    }

    /**
     * 
     */

    public function matchRequestParams( $sample, bool $strict = false )
    {
        $requestParams = $this->getRequestParams(array_keys($sample));
        return $this->matchParams($sample, $requestParams, $strict);
    }

    /**
     *
     */

    public function matchParams( $sample, $master, bool $strict = false )
    {
        return ParamHelper::matchParams($sample, $master, $strict);
    }

    /**
     *
     */

    public function parsePathmask( string $mask, $object = null, array $vars = [] )
    {
        return Tailor::$plugin->pathmasks->parsePathmask($mask, $object, $vars);
    }

    /**
     *
     */

    public function resolvePathmask( string $mask, $object = null, array $vars = [] )
    {
        return Tailor::$plugin->pathmasks->resolvePathmask($mask, $object, $vars);
    }

    /**
     *
     */

    public function getAttribute( array $attrs, string $key )
    {
        return Tailor::$plugin->markup->getAttribute($attrs, $key);
    }

    /**
     *
     */

    public function addAttribute( array $attrs, string $key, $values )
    {
        return Tailor::$plugin->markup->addAttribute($attrs, $key, $value);
    }

    /**
     *
     */

    public function setAttribute( array $attrs, string $key, $value )
    {
        return Tailor::$plugin->markup->setAttribute($attrs, $key, $value);
    }

}