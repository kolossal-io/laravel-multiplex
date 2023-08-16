<?php

namespace Kolossal\Multiplex\DataType;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Handle serialization of Eloquent collections.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class ModelCollectionHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'collection';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        return $value instanceof Collection;
    }

    /**
     * Convert the value to a string, so that it can be stored in the database.
     *
     * @param  Collection  $value
     */
    public function serializeValue($value): string
    {
        if (!($value instanceof Collection)) {
            return '';
        }

        $items = $value->mapWithKeys(
            fn ($model, $key) => [$key => [
                'class' => get_class($model),
                'key' => $model->exists ? $model->getKey() : null,
            ]]
        );

        return json_encode(['class' => get_class($value), 'items' => $items]) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(?string $value)
    {
        if (!is_string($value) || empty($value)) {
            return null;
        }

        $data = json_decode($value, true);

        if (is_null($data) || !is_array($data) || !isset($data['class'])) {
            return null;
        }

        /** @var Collection */
        $collection = new $data['class']();
        $models = $this->loadModels($data['items']);

        // Repopulate collection keys with loaded models.
        foreach ($data['items'] as $key => $item) {
            if (is_null($item['key']) && ($model = new $item['class']()) instanceof Model) {
                $collection->put($key, $model);
            } elseif (isset($models[$item['class']][$item['key']])) {
                $collection->put($key, $models[$item['class']][$item['key']]);
            }
        }

        return $collection;
    }

    /**
     * Load each model instance, grouped by class.
     *
     * @return array
     */
    private function loadModels(array $items)
    {
        $classes = [];
        $results = [];

        // Retrieve a list of keys to load from each class.
        foreach ($items as $item) {
            if (!is_null($item['key'])) {
                $classes[$item['class']][] = $item['key'];
            }
        }

        // Iterate list of classes and load all records matching a key.
        foreach ($classes as $class => $keys) {
            /** @var \Illuminate\Database\Eloquent\Model */
            $model = new $class();

            $results[$class] = $model
                ->whereIn($model->getKeyName(), $keys)
                ->get()
                ->keyBy($model->getKeyName());
        }

        return $results;
    }
}
