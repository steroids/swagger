<?php

namespace steroids\docs\widgets\SwaggerUi;

use steroids\core\base\Widget;
use yii\helpers\Url;

class SwaggerUi extends Widget
{
    public function run()
    {
        return $this->renderReact([
            'swaggerUrl' => Url::to(['/docs/docs/json']),
        ]);
    }
}