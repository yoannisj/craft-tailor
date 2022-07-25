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
 * Validator class used to validate a list of models
 */

class ModelListValidator extends Validator
{
    /**
     * @var string
     */

    public ?string $model;

    /**
     * @var bool
     */

    public bool $mustValidate = true;

    /**
     * @inheritdoc
     */

    public function init(): void
    {
        if (!isset($this->model)) {
            throw new InvalidConfigException('ModelListValidator missing required `model` parameter.');
        }

        if ($this->message === null)
        {
            if ($this->mustValidate) {
                $this->message = '`{attribute}` must be an array of valid {model} models.';
            } else {
                $this->message = '`{attribute}` must be an array of {model} models.';
            }
        }
    }

    /**
     * @inheritdoc
     */

    protected function validateValue( mixed $value ): ?array
    {
        if (!is_array($value)) {
            return [ $this->message, ['model' => $this->model] ];
        }

        foreach ($value as $item)
        {
            if (!is_a($item, $this->model, true))
            {
                return [ $this->message, ['model' => $this->model] ];
            }
            
            else if ($this->mustValidate && !$item->validate())
            {
                return [ $this->message, [ 'model' => $this->model ] ];
            }
        }

        return null;
    }
}