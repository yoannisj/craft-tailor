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
use craft\helpers\ArrayHelper;

use yoannisj\tailor\Tailor;
use yoannisj\tailor\helpers\ParamHelper;

/**
 *
 */

class TailorVariable
{
    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isQuery( mixed $value ): bool
    {
        return ($value instanceof Query);
    }

    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isElement( mixed $value ): bool
    {
        return ($value instanceof ElementInterface);
    }

    /**
     *@param mixed $value
     * 
     * @return bool
     */

    public function isBlock( mixed $value ): bool
    {
        return ($value instanceof BlockElementInterface);
    }

    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isGlobalset( mixed $value ): bool
    {
        return ($value instanceof Globalset);
    }

    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isEntry( mixed $value ): bool
    {
        return ($value instanceof Entry);
    }

    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isAsset( mixed $value ): bool
    {
        return ($value instanceof Asset);
    }

    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isCategory( mixed $value ): bool
    {
        return ($value instanceof Category);
    }

    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isTag( mixed $value ): bool
    {
        return ($value instanceof Tag);
    }

    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isMatrixBlock( mixed $value ): bool
    {
        return ($value instanceof MatrixBlock);
    }

    /**
     * @param mixed $value
     * 
     * @return bool
     */

    public function isUser( mixed $value ): bool
    {
        return ($value instanceof User);
    }

    /**
     * @param mixed $value
     * 
     * @return string
     */

    public function getType( mixed $value ): string
    {
        return gettype($value);
    }

    /**
     * @param mixed $object
     * 
     * @return ?string
     */

    public function getClass( mixed $object ): ?string
    {
        if (!is_object($object)) {
            return null;
        }

        return get_class($object);
    }

    /**
     * @param string|array|null
     */

    public function parseParams( string|array|null $params ): array
    {
        return ParamHelper::parseParams($params);
    }

    /**
     * 
     */

    public function getRequestParams( array $paramNames, mixed $defaults = null ): array
    {
        if (!is_array($defaults) && !ArrayHelper::isAssociative($defaults))
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
            $params[$name] = $request->getParam($name, $defaults[$name] ?? null);
        }

        return $params;
    }

    /**
     * 
     */

    public function matchRequestParams( string|array|null $sample, bool $strict = false ): bool
    {
        $requestParams = $this->getRequestParams(array_keys($sample));
        return $this->matchParams($sample, $requestParams, $strict);
    }

    /**
     *
     */

    public function matchParams(
        string|array|null $sample, string|array|null $master, bool $strict = false ): bool
    {
        return ParamHelper::matchParams($sample, $master, $strict);
    }

    /**
     *
     */

    public function parsePathmask( string $mask, array|object|null $object = null, array $vars = [] )
    {
        return Tailor::$plugin->pathmasks->parsePathmask($mask, $object, $vars);
    }

    /**
     *
     */

    public function resolvePathmask( string $mask, array|object|null $object = null, array $vars = [] ): array
    {
        return Tailor::$plugin->pathmasks->resolvePathmask($mask, $object, $vars);
    }

}