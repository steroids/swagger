<?php

namespace steroids\swagger\extractors;

use steroids\core\components\SiteMapItem;
use steroids\swagger\models\SwaggerRefsStorage;

class SiteMapDocExtractor extends BaseDocExtractor
{
    /**
     * @var SiteMapItem[]
     */
    public $items;

    public function run()
    {
        $this->recursiveExtract($this->items);
    }

    /**
     * @param SiteMapItem[] $items
     * @throws \ReflectionException
     * @throws \yii\base\InvalidConfigException
     */
    protected function recursiveExtract($items)
    {
        foreach ($items as $item) {
            $url = $item->normalizedUrl;

            if ($this->isRoute($url)) {
                (new ControllerDocExtractor([
                    'swaggerJson' => $this->swaggerJson,
                    'route' => $url[0],
                    'item' => $item,
                    'url' => $item->urlRule,
                    'title' => $item->label,
                    'refsStorage' => $this->swaggerJson->refsStorage,
                ]))->run();
            }
            if (is_array($item->items)) {
                $this->recursiveExtract($item->items);
            }
        }
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isRoute($value)
    {
        return is_array($value) && isset($value[0]) && is_string($value[0]);
    }
}




