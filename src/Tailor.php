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

use yii\base\Event;

use Craft;
use craft\base\Component;
use craft\base\Plugin;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;

use yoannisj\tailor\services\Markup;
use yoannisj\tailor\services\Pathmasks;
use yoannisj\tailor\services\Ajax;
use yoannisj\tailor\variables\TailorVariable;
use yoannisj\tailor\twigextensions\TailorTwigExtension;

/**
 * Plugin class for Craft Tailor, loading all of the plugins functionality in the system.
 * Gets instanciated at the beginning of every request to Craft, if the plugin is installed and enabled
 */

class Tailor extends Plugin
{
    // =Static
    // =========================================================================

    /**
     * Normalizes value as an instance of given object class
     *
     * @param string $class Class the normalized object should be an instance of
     * @param array | object $value Existing object or configuration array
     * @param array $defaults Optional values for missing object properties
     *
     * @return object | null Class instance object if value could be normalized
     */

    public static function normalizeObject( string $class, $object, array $defaults = [] )
    {
        if (is_array($object))
        {
            $props = $object;
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
     * reference to the plugin's instance
     * @var Plugin
     */

    public static $plugin;

    // =Public Methods
    // =========================================================================

    /**
     * Method running when the plugin gets initialized
     * This is where all of the plugin's functionality gets loaded into the system
     */

    public function init()
    {
        parent::init();

        // store reference to plugin instance
        self::$plugin = $this;

        // register plugin services as components
        $this->setComponents([
            'markup' => Markup::class,
            'pathmasks' => Pathmasks::class,
            'ajax' => Ajax::class,
        ]);

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
}