<?php

namespace steroids\swagger\extractors;

use steroids\core\components\SiteMapItem;
use steroids\swagger\helpers\ExtractorHelper;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\web\Controller;

/**
 * Class ControllerDocExtractor
 * @property-read string|null $actionId
 * @property-read string|null $controllerId
 * @property-read string|null $moduleId
 * @property-read string|null $siteMapPath
 */
class ControllerDocExtractor extends BaseDocExtractor
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $route;

    /**
     * @var string
     */
    public $url;

    /**
     * @var SiteMapItem
     */
    public $item;

    /**
     * @throws \ReflectionException
     * @throws \yii\base\InvalidConfigException
     */
    public function run()
    {
        $controller = \Yii::$app->createController($this->route)[0];
        $method = $this->findActionMethodInfo($controller, $this->actionId);
        if (!$method) {
            return null;
        }

        $url = $this->url;
        $httpMethod = 'get';
        if (preg_match('/^([A-Z,]+) .+/', $url, $match)) {
            $url = substr($url, strlen($match[1]) + 1);
            $httpMethod = strtolower(explode(',', $match[1])[0]);
        }
        $url = preg_replace('/^\/?api\/[^\\/]+\//', '', $url);
        $url = preg_replace('/<([^>:]+)(:[^>]+)?>/', '{$1}', $url);
        $this->swaggerJson->addPath($url, $httpMethod, [
            'summary' => $url,
            'description' => $this->title,
            'tags' => [
                $this->siteMapPath ?: $this->moduleId,
            ],
            'consumes' => [
                'application/json'
            ],
            'responses' => [
                200 => [
                    'description' => 'Successful operation',
                ],
            ],
        ]);

        if (preg_match('/@return ([a-z0-9_]+)/i', $method->getDocComment(), $match)) {
            $type = ExtractorHelper::resolveType($match[1], get_class($controller));
            $extractor = $this->createTypeExtractor($type, $url, $httpMethod);
            if ($extractor) {
                $phpDoc = $method->getDocComment();
                if (preg_match_all('/@request-listen-relation\s+([^\s]+)/i', $phpDoc, $match)) {
                    $extractor->listenRelations = $match[1];
                }

                $extractor->params = [];
                if (preg_match_all('/@param(-post)? +([^\s\n]+) +([^\s\n]+)( [^\n]+)?/i', $phpDoc, $match, PREG_SET_ORDER)) {
                    foreach ($match as $matchItem) {
                        if (strpos($matchItem[2], '$') === 0) {
                            $extractor->params[substr($matchItem[2], 1)] = [
                                'type' => 'string',
                                'description' => trim(trim($matchItem[3], '*')),
                            ];
                        }
                        if (strpos($matchItem[3], '$') === 0) {
                            $extractor->params[substr($matchItem[3], 1)] = [
                                'type' => SwaggerTypeExtractor::parseSingleType($matchItem[2]),
                                'description' => isset($matchItem[4]) ? trim(trim($matchItem[4], '*')) : '',
                            ];
                        }
                    }
                }

                try {
                    $extractor->run();
                } catch (\Exception $e) {
                    throw $e;
                    throw new Exception('Error on parse "' . $url . '" controller phpdoc: ' . $method->getDocComment() . '. ' . $e->getMessage(), 0, $e);
                }
            }

            // Find first comment line as title
            if (!$this->title) {
                foreach (explode("\n", $method->getDocComment()) as $line) {
                    $line = preg_replace('/^\s*\\/?\*+/', '', $line);
                    $line = trim($line);
                    if ($line && $line !== '/' && substr($line, 0, 1) !== '@') {
                        $this->title = $line;
                        $this->swaggerJson->updatePath($url, $httpMethod, [
                            'summary' => '/' . $url,
                            'description' => $this->title,
                        ]);
                        break;
                    }
                }
            }
        }
    }

    /**
     * @return string|null
     */
    public function getActionId()
    {
        $parts = explode('/', $this->route);
        return ArrayHelper::getValue($parts, count($parts) - 1);
    }

    /**
     * @return string|null
     */
    public function getControllerId()
    {
        $parts = explode('/', $this->route);
        return ArrayHelper::getValue($parts, count($parts) - 2);
    }

    /**
     * @return string|null
     */
    public function getModuleId()
    {
        $parts = explode('/', $this->route);
        return ArrayHelper::getValue($parts, count($parts) - 3);
    }

    public function getSiteMapPath()
    {
        $ids = $this->item->getPathIds();
        if (count($ids) === 0) {
            return null;
        }

        if ($ids[0] === 'api') {
            array_shift($ids);
        }

        // Remove action name
        if (count($ids) > 1) {
            array_pop($ids);
        }

        return implode('.', $ids);
    }

    /**
     * @param Controller $controller
     * @param string $actionId
     * @return \ReflectionMethod|null
     * @throws \ReflectionException
     */
    protected function findActionMethodInfo($controller, $actionId)
    {
        $actionMethodName = 'action' . Inflector::id2camel($actionId);
        $controllerInfo = new \ReflectionClass(get_class($controller));

        foreach ($controllerInfo->getMethods() as $method) {
            if ($method->name === $actionMethodName) {
                return $method;
            }
        }
        return null;
    }

}
