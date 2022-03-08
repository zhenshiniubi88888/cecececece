<?php

namespace frontend\controllers;

/**
 * Index controller
 */
class IndexController extends BaseController
{

    public function actionIndex()
    {
        exit(json_encode(array('state'=>1)));
    }
}
