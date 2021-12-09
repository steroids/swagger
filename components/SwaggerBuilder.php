<?php

namespace steroids\swagger\components;

use steroids\core\components\SiteMapItem;
use steroids\core\helpers\ClassFile;
use steroids\gii\helpers\GiiHelper;
use steroids\swagger\helpers\TypeScriptHelper;
use steroids\swagger\models\SwaggerAction;
use steroids\swagger\models\SwaggerContext;
use steroids\swagger\models\SwaggerResult;
use steroids\swagger\SwaggerModule;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\web\JsExpression;
use yii\web\Request;

class SwaggerBuilder extends Component
{
    protected SwaggerResult $result;

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

    public function buildTypes()
    {
        $this->prepare();

        $module = SwaggerModule::getInstance();

        /** @var SwaggerAction[][] $controllerActions */
        $controllerActions = [];

        // Run extract
        $context = new SwaggerContext(['refsStorage' => $this->result->refsStorage]);
        foreach ($this->result->actions as $action) {
            $action->extract($context);

            // Controller file
            $relativePath = str_replace('.', DIRECTORY_SEPARATOR, $action->moduleId)
                . DIRECTORY_SEPARATOR . 'api'
                . DIRECTORY_SEPARATOR . $action->controllerId . '.ts';
            $controllerActions[$relativePath][] = $action;
        }

        $counts = [];
        foreach ($this->result->refsStorage->getAll() as $property) {
            if (is_array($property->items)) {
                foreach ($property->items as $item) {
                    if ($item->refName) {
                        if (!isset($counts[$item->refName])) {
                            $counts[$item->refName] = 0;
                        }
                        $counts[$item->refName]++;
                    }
                }
            }
        }

        foreach ($this->result->refsStorage->getAll() as $name => $property) {
            $relativePath = $this->result->refsStorage->getRefRelativePath($name);
            FileHelper::createDirectory(dirname($module->typesOutputDir . DIRECTORY_SEPARATOR . $relativePath));
            file_put_contents(
                $module->typesOutputDir . DIRECTORY_SEPARATOR . $relativePath,
                TypeScriptHelper::generateInterfaces([$name => $property], $relativePath, $this->result->refsStorage, [],true)
            );
        }

        foreach ($controllerActions as $relativePath => $actions) {
            FileHelper::createDirectory(dirname($module->typesOutputDir . DIRECTORY_SEPARATOR . $relativePath));
            file_put_contents(
                $module->typesOutputDir . DIRECTORY_SEPARATOR . $relativePath,
                TypeScriptHelper::generateApi($actions, $relativePath, $this->result->refsStorage)
            );
        }

        // Add utils.ts
        $path = $module->typesOutputDir . DIRECTORY_SEPARATOR . 'utils.ts';
        if (!file_exists($path)) {
            FileHelper::createDirectory(dirname($path));
            copy(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'typescript' . DIRECTORY_SEPARATOR . 'utils.ts', $path);
        }

        // Add index.ts
        $tree = [];
        foreach ($controllerActions as $relativePath => $actions) {
            foreach ($actions as $action) {
                $tree[$action->moduleId][$action->controllerId] = $relativePath;
            }
        }
        $content = "const api = {\n";
        foreach ($tree as $moduleId => $actions) {
            $content .= "    $moduleId: {\n";
            foreach ($actions as $controllerId => $relativePath) {
                $controllerId = lcfirst(Inflector::id2camel($controllerId));
                $content .= "        $controllerId: require('./$relativePath'),\n";
            }
            $content .= "    },\n";
        }
        $content .= "}\n\n";
        $content .= "export default api;\n";
        file_put_contents($module->typesOutputDir . DIRECTORY_SEPARATOR . 'index.ts', $content);
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
            'hostName' => \Yii::$app->request instanceof Request ? \Yii::$app->request->hostName : null,
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

        // Separate route to module, controller and action ids
        $moduleId = implode('.', array_filter(array_slice(explode('/', $route), 0, -2)));
        list($controllerId, $actionId) = array_slice(explode('/', $route), -2, 2);

        // Get controller class
        // TODO Нужно научиться правильно получать класс контроллера даже в консольном приложении
        $controllerClass = 'app\\' . str_replace('.', '\\', $moduleId) . '\\controllers\\' . ucfirst(Inflector::id2camel($controllerId)) . 'Controller';
        //$controllerResult = \Yii::$app->createController($route);
        //$controllerClass = $controllerResult ? get_class($controllerResult[0]) : null;
        if (!$controllerClass) {
            return [];
        }

        // Get method name
        $methodName = 'action' . Inflector::id2camel($actionId);
        if (!(new \ReflectionClass($controllerClass))->hasMethod($methodName)) {
            return [];
        }

        // Get normalized url
        $url = $item->urlRule;
        $url = preg_replace('/^([A-Z,]+)\s+/', '', $url);
        $url = preg_replace('/<([^>:]+)(:[^>]+)?>/', '{$1}', $url);
        $url = str_replace('{version}', $this->result->version, $url);
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




