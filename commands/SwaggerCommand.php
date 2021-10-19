<?php

namespace steroids\swagger\commands;

use steroids\swagger\components\SwaggerBuilder;
use yii\console\Controller;

class SwaggerCommand extends Controller
{
    public function actionTypes()
    {
        (new SwaggerBuilder())->buildTypes();
    }
}




