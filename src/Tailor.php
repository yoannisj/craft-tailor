<?php

/**
 * Tailor plugin for Craft
 *
 * @author Yoannis Jamar
 * @copyright Copyright (c) 2019 Yoannis Jamar
 * @link https://github.com/yoannisj/
 * @package craft-tailor
 */

namespace yoannisj\tailor;

use yii\base\BaseObject;
use yii\base\Event;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use craft\services\Elements;
use craft\events\EagerLoadElementsEvent;
use craft\helpers\App as AppHelper;

use yoannisj\tailor\models\Settings;
use yoannisj\tailor\services\Markup;
use yoannisj\tailor\services\Pathmasks;
use yoannisj\tailor\services\Ajax;
use yoannisj\tailor\variables\TailorVariable;
use yoannisj\tailor\twigextensions\TailorTwigExtension;
use yoannisj\tailor\helpers\ContentHelper;

/**
 * Plugin class for Craft Tailor, loading all of the plugins functionality in the system.
 * Gets instanciated at the beginning of every request to Craft, if the plugin is installed and enabled
 * 
 * @property-read Markup $markup The plugin's Markup service instance (singleton)
 * @property-read Pathmasks $pathmasks The plugin's Pathmasks service instance (singleton)
 * @property-read Ajax $ajax The plugin's Ajax service instance (singleton)
 */

class Tailor extends Plugin
{
    // =Static
    // =========================================================================

    /**
     * @var Plugin Reference to the plugin's instance
     */

    public static Plugin $plugin;

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'markup' => Markup::class,
                'pathmasks' => Pathmasks::class,
                'ajax' => Ajax::class,
            ],
        ];
    }

    /**
     * Normalizes value as an instance of given object class
     *
     * @param string $class Class the normalized object should be an instance of
     * @param array|object $value Existing object or configuration array
     * @param array $defaults Optional values for missing object properties
     *
     * @return ?object Class instance object if value could be normalized
     */

    public static function normalizeObject(
        string $class, array|object $object, array $defaults = [] ): ?object
    {
        if (is_array($object))
        {
            $props = $object;

            /** @var BaseObject $object */
            $object = Craft::createObject($class);

            foreach ($props as $name => $value)
            {
                if ($object->canSetProperty($name)) {
                    $object->$name = $value;
                }
            }
        }

        else if (!is_a($object, $class, false)) {
            return null;
        }

        if (!empty($defaults))
        {
            foreach ($defaults as $prop => $value)
            {
                if (!isset($object->$prop) && $object->canSetProperty($name)) {
                    $object->$prop = $value;
                }
            }
        }

        return $object;
    }

    // =Properties
    // =========================================================================

    /**
     * @var bool Whether the SEOmatic plugin for Craft is installed
     * @see https://plugins.craftcms.com/seomatic
     */
    public bool $isSeomaticInstalled = false;

    /**
     * @var bool Whether the SEOmatic plugin for Craft is enabled
     * * @see https://plugins.craftcms.com/seomatic
     */
    public bool $isSeomaticEnabled = false;

    /**
     * @var bool Whether the Neo plugin for Craft is installed
     * @see https://plugins.craftcms.com/neo
     */
    public bool $isNeoInstalled = false;

    /**
     * @var bool Whether the Neo plugin for Craft is enabled
     * @see https://plugins.craftcms.com/neo
     *
     * @var bool
     */
    public bool $isNeoEnabled = false;

    /**
     * @var bool Whether the installed Neo plugin version has an issue where
     * Neo Block fields can return duplicate blocks in some scenarios.
     * @see https://github.com/spicywebau/craft-neo/issues/387
     *
     * @var bool
     */
    public bool $neoVersionHasDuplicatesIssue = false;

    // =Public Methods
    // =========================================================================

    /**
     * Method running when the plugin gets initialized
     * This is where all of the plugin's functionality gets loaded into the system
     */

    public function init(): void
    {
        parent::init();

        // store reference to plugin instance
        self::$plugin = $this;

        // collect useful information about available plugins
        $this->loadPluginsInfo();

        // register twig extensions provided by the plugin
        $extension = new TailorTwigExtension();
        Craft::$app->view->registerTwigExtension($extension);

        // register plugin (template) variables to (e.g. `craft.tailor.foo()`)
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $e) {
                /** @var CraftVariable $variable */
                $variable = $e->sender;
                $variable->set('tailor', TailorVariable::class);
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_EAGER_LOAD_ELEMENTS,
            function( EagerLoadElementsEvent $event )
            {
                $elementType = $event->elementType;
                $sourceElements = $event->elements;

                // only include eager-loadable plans
                $event->with = ContentHelper::selectEagerLoadingPlans(
                    $elementType, $sourceElements, $event->with);
            }
        );
    }

    /**
     *
     */

    public function getMarkup(): Markup
    {
        return $this->get('markup');
    }

    /**
     *
     */

    public function getPathmasks(): Pathmasks
    {
        return $this->get('pathmasks');
    }

    /**
     *
     */

    public function getAjax(): Ajax
    {
        return $this->get('ajax');
    }

    // =Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Model
    {
        return new Settings();
    }

    /**
     * Collect useful information about available plugins
     *
     * @return void
     */
    protected function loadPluginsInfo()
    {
        $craftPlugins = Craft::$app->getPlugins();

        $this->isNeoInstalled = $craftPlugins->isPluginInstalled('neo');
        $this->isNeoEnabled = $craftPlugins->isPluginEnabled('neo');
        $this->neoVersionHasDuplicatesIssue = false;

        if ($this->isNeoInstalled)
        {
            $neoPlugin = $craftPlugins->getPlugin('neo');
            $neoVersion = AppHelper::normalizeVersion($neoPlugin->getVersion());
            $this->neoVersionHasDuplicatesIssue = version_compare($neoVersion, '2.8.10', '<');
        }

        $this->isSeomaticInstalled = $craftPlugins->isPluginInstalled('seomatic');
        $this->isSeomaticEnabled = $craftPlugins->isPluginEnabled('seomatic');
    }
}