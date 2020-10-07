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

namespace yoannisj\tailor\models;

use Craft;
use craft\base\Model;
use craft\helpers\ArrayHelper;

use yoannisj\tailor\Tailor;

/**
 * 
 */

class AjaxActionResults extends Model
{
    // =Properties
    // ======================≠======================≠======================≠===

    /**
     * @var array
     */

    public $params;

    /**
     * @var array
     */

    private $_snippets;

    // =Public Methods
    // ======================≠======================≠======================≠===

    /**
     * @inheritdoc
     */

    public function init()
    {
        if (!isset($this->params)) {
            $this->params = Craft::$app->getRequest()->getBodyParams();
        }

        parent::init();
    }

    /**
     * @inheritdoc
     */

    public function fields()
    {
        $fields = parent::fields();

        $fields[] = 'snippets';

        return $fields;
    }

    /**
     * @return array
     */

    public function getSnippets(): array
    {
        if (!isset($this->_snippets))
        {
            return Tailor::$plugin->getAjax()->renderSnippets($this->params);
        }

        return $this->_snippets;
    }

}