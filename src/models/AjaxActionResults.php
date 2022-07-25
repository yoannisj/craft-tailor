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

    public array|object|null $params;

    /**
     * @var array
     */

    private ?array $_snippets;

    // =Public Methods
    // ======================≠======================≠======================≠===

    /**
     * @inheritdoc
     */

    public function init(): void
    {
        parent::init();

        if (!isset($this->params)) {
            $this->params = Craft::$app->getRequest()->getBodyParams();
        }
    }

    /**
     * @inheritdoc
     */

    public function fields(): array
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
        if (!isset($this->_snippets)) {
            return Tailor::$plugin->ajax->renderSnippets($this->params);
        }

        return $this->_snippets;
    }

}