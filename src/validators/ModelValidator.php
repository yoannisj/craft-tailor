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

namespace yoannisj\tailor\validators;

use yii\base\InvalidConfigException;
use yii\validators\Validator;

use Craft;

/**
 * 
 */

class ModelValidator extends Validator
{

    /**
     * @var string
     */

    public $model;

    /**
     * @var bool
     */

    public $mustValidate = true;

    /**
     * @inheritdoc
     */

    public function init()
    {
        if (!isset($this->model)) {
            throw new InvalidConfigException('Missing required `model` parameter.');
        }

        if ($this->message === null)
        {
            if ($this->mustValidate) {
                $this->message = '`{attribute}` must be a valid instance of {model}';
            } else {
                $this->message = '`{attribute}` must be an instance of {model}';
            }
        }
    }

    /**
     * @inheritdoc
     */

    public function validateValue( $value )
    {
        if (!is_a($value, $this->model, true)) {
            return [ $this->message, [ 'model' => $this->model ] ];
        }
        
        else if ($this->mustValidate && !$value->validate()) {
            return [ $this->message, [ 'model' => $this->model ] ];
        }
    }
}