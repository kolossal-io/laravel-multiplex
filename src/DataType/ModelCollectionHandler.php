<?php

namespace Kolossal\Multiplex\DataType;

use Illuminate\Database\Eloquent\Collection;

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
        if (is_null($value)) {
            return $value;
        }

        $data = json_decode($value, true);

        if (!is_array($data)) {
            return $value;
        }

        /** @var Collection */
        $collection = new $data['class']();
        $models = $this->loadModels($data['items']);

        // Repopulate collection keys with loaded models.
        foreach ($data['items'] as $key => $item) {
            if (is_null($item['key'])) {
                $collection->put($key, new $item['class']());
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
