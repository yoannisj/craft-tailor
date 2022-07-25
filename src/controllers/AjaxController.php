<?php

/**
 * Craft module for Nanit website
 *
 * @author Yoannis Jamar
 * @copyright Copyright (c) 2019 Nanit
 * @link https://github.com/nanit/
 * @package www.nanit.com
 *
 */

namespace yoannisj\tailor\controllers;

use yii\base\Exception;
use yii\web\NotFoundHttpException;
use yii\web\Cookie;

use Craft;
use craft\web\Controller;
use craft\web\Request;
use craft\web\Response;

use yoannisj\tailor\models\AjaxActionResults;

/**
 * Controller class to handle Ajax requests
 */

class AjaxController extends Controller
{
    // =Properties
    // ======================≠======================≠======================≠===

    /**
     * @inheritdoc
     */

    protected array|int|bool $allowAnonymous = true;

    // =Public Methods
    // ======================≠======================≠======================≠===

    /**
     * @return \craft\web\Response
     */

    public function actionIndex( array $params = null ): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $results = new AjaxActionResults([
            'params'  => $params
        ]);

        return $this->asJson($results);
    }
}