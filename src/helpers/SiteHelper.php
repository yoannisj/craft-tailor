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

class SiteHelper
{
    // =Properties
    // ========================================================================

    // =Methods
    // ========================================================================

    /**
     * @param int | string | \craft\models\Site | null $site
     * @return \craft\models\Site | null
     */

    public static function siteModel( $site = null )
    {
        if (!$site) {
            return Craft::$app->getSites()->getCurrentSite();
        }

        else if ($site instanceof Site) {
            return $site;
        }

        else if (is_numeric($site)) {
            return Craft::$app->getSites()->getSiteById($site);
        }

        else if (is_string($site)) {
            return Craft::$app->getSites()->getSiteByHandle($site);
        }

        else if (is_array($site)) {
            // @todo: get site based on query criteria
        }

        return null;
    }

    /**
     * @param int | string | \craft\models\Site | null $site
     * @return  int | null
     */

    public static function siteId( $site = null )
    {
        if (is_numeric($site)) {
            return (int)$site;
        }

        $site = static::siteModel($site);

        if ($site) {
            return $site->id;
        }

        return null;
    }

    /**
     * @param int | string | \craft\models\Site | null $site
     * @return string | null
     */

    public static function siteHandle( $site = null )
    {
        if (is_string($site) && !is_numeric($site)) {
            return $site;
        }

        $site = static::siteModel($site);

        if ($site) {
            return $site->handle;
        }

        return null;
    }
}