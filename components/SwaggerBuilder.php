<?php

namespace steroids\swagger\components;

use steroids\core\components\SiteMapItem;
use steroids\swagger\models\SwaggerAction;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerResult;
use steroids\swagger\SwaggerModule;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

class SwaggerBuilder extends Component
{
    protected SwaggerResult $result;

    public function init()
    {
        parent::init();
    }

    public function buildJson()
    {
        $this->prepare();

        // Run extract
        $context = new SwaggerContext(['refsStorage' => $this->result->refsStorage]);
        foreach ($this->result->actions as $action) {
            $action->extract($context);
        }

        return $this->result->toArray();
    }

    /**
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    protected function prepare()
    {
        // Create json object
        $this->result = new SwaggerResult([
            'siteName' => \Yii::$app->name,
            'hostName' => \Yii::$app->request->hostName,
            'adminEmail' => ArrayHelper::getValue(\Yii::$app->params, 'adminEmail', ''),
        ]);

        // Prepare skip path, convert to regexp
        $skipPaths = SwaggerModule::getInstance()->skipPaths;
        $skipRegexpParts = [];
        foreach ($skipPaths as $skipPath) {
            $skipRegexpParts[] = implode('\\.', array_map(
                fn($str) => $str === '*' ? '[^\.]+' : preg_quote($str),
                explode('.', $skipPath),
            ));
        }
        $skipRegexp = '/^' . implode('|', $skipRegexpParts) . '/';

        // Store site map items (convert tree to single list)
        $siteMapItems = $this->fetchSiteMapItemsRecursive(
            ArrayHelper::getValue(\Yii::$app->siteMap->getItem('api'), 'items'),
            $skipRegexp
        );

        // Create actions
        foreach ($siteMapItems as $siteMapItem) {
            $this->result->addActions($this->createActions($siteMapItem));
        }
    }

    /**
     * @param SiteMapItem $item
     * @return SwaggerAction[]
     * @throws \yii\base\InvalidConfigException
     */
    protected function createActions(SiteMapItem $item)
    {
        // Get route
        $route = ArrayHelper::getValue($item->normalizedUrl, 0);
        if (!$route || !is_string($route)) {
            return [];
        }

        // Get controller class
        $controllerResult = \Yii::$app->createController($route);
        $controllerClass = $controllerResult ? get_class($controllerResult[0]) : null;
        if (!$controllerClass) {
            return [];
        }

        // Separate route to module, controller and action ids
        $moduleId = implode('.', array_filter(array_slice(explode('/', $route), 0, -2)));
        list($controllerId, $actionId) = array_slice(explode('/', $route), -2, 2);

        // Get method name
        $methodName = 'action' . Inflector::id2camel($actionId);
        if (!(new \ReflectionClass($controllerClass))->hasMethod($methodName)) {
            return [];
        }

        // Get normalized url
        $url = $item->urlRule;
        $url = preg_replace('/^([A-Z,]+)\s+/', '', $url);
        $url = preg_replace('/<([^>:]+)(:[^>]+)?>/', '{$1}', $url);
        $url = '/' . ltrim($url, '/');

        // Get HTTP methods
        $httpMethods = preg_match('/^([A-Z,]+)\s+.+/', $item->urlRule, $match)
            ? StringHelper::explode(strtolower($match[1]))
            : ['get'];

        // Create actions
        $actions = [];
        foreach ($httpMethods as $httpMethod) {
            $actions[] = new SwaggerAction([
                'controllerClass' => $controllerClass,
                'methodName' => $methodName,
                'moduleId' => $moduleId,
                'controllerId' => $controllerId,
                'actionId' => $actionId,
                'url' => $url,
                'httpMethod' => $httpMethod,
            ]);
        }
        return $actions;
    }

    protected function fetchSiteMapItemsRecursive($items, $skipRegexp)
    {
        $result = [];
        foreach ($items ?: [] as $item) {
            // Skipped paths (without "api" key)
            $path = implode('.', array_slice($item->pathIds, 1));
            if (preg_match($skipRegexp, $path)) {
                continue;
            }

            // Add
            $result[] = $item;
            $result = array_merge($result, $this->fetchSiteMapItemsRecursive($item->items, $skipRegexp));
        }
        return $result;
    }
}




