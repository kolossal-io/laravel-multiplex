<?php

namespace Kolossal\Multiplex\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Kolossal\Multiplex\Exceptions\MetaException;
use Kolossal\Multiplex\HasMeta;
use Kolossal\Multiplex\Meta;

/**
 * Create meta records from the values of existing table columns,
 * e.g. when moving columns of a model to meta data.
 *
 * Meant to be used inside a migration. Values are read from the raw table rows, without
 * any model events or global scopes (so soft deleted rows are included) and inserted
 * in bulk, so no `MetaHasBeenAdded` events are dispatched. Running it again will not
 * create duplicates, `null` values are skipped.
 */
class MetaBackfill
{
    protected string|DateTimeInterface|null $publishedAt = 'created_at';

    protected int $chunkSize = 500;

    /**
     * @param  class-string<Model>  $model
     * @param  array<string>  $columns
     */
    final public function __construct(protected string $model, protected array $columns)
    {
        if (!is_a($model, Model::class, true) || !in_array(HasMeta::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException("Model `{$model}` must be an Eloquent model using the `HasMeta` trait.");
        }
    }

    /**
     * Create meta records from the given columns of the given model’s table.
     *
     * @param  class-string<Model>  $model
     * @param  array<string>  $columns
     */
    public static function fromColumns(string $model, array $columns): static
    {
        return new static($model, $columns);
    }

    /**
     * Set the publishing time of the created meta records. Pass the name of a column
     * to take the time from each row, or a fixed point in time. Rows without a time
     * and `null` will be published now.
     */
    public function publishedAt(string|DateTimeInterface|null $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /**
     * Set the number of rows to process at once.
     */
    public function chunk(int $size): self
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Create the meta records and return the number of records created.
     */
    public function run(): int
    {
        $model = new $this->model;
        $keyName = $model->getKeyName();

        $columns = [$keyName, ...$this->columns];

        if (is_string($this->publishedAt)) {
            $columns[] = $this->publishedAt;
        }

        $created = 0;

        $model->newQueryWithoutScopes()
            ->toBase()
            ->select(array_values(array_unique($columns)))
            ->chunkById($this->chunkSize, function (Collection $rows) use ($model, $keyName, &$created) {
                $records = $this->withoutExisting($model, $rows->flatMap(
                    fn(object $row) => $this->makeMeta($model, $row, $row->{$keyName})
                ));

                if ($records->isNotEmpty()) {
                    $this->getMetaClassName()::query()->insert($records->all());
                    $created += $records->count();
                }
            }, $keyName);

        return $created;
    }

    /**
     * Make the raw meta attributes for all non-null columns of the given row.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function makeMeta(Model $model, object $row, mixed $id): array
    {
        $metaClassName = $this->getMetaClassName();
        $publishedAt = $this->getPublishedAt($row);

        return collect($this->columns)
            ->reject(fn(string $column) => is_null($row->{$column}))
            ->map(function (string $column) use ($model, $row, $id, $metaClassName, $publishedAt) {
                $key = strtolower($column);
                $cast = method_exists($model, 'getCastForMetaKey') ? $model->getCastForMetaKey($key) : null;

                $meta = (new $metaClassName)
                    ->forceType(is_string($cast) ? $cast : null)
                    ->forceFill([
                        'key' => $key,
                        'value' => $this->getValue($row->{$column}, is_string($cast) ? $cast : null),
                        'published_at' => $publishedAt,
                    ])
                    ->forceFill([
                        'metable_type' => $model->getMorphClass(),
                        'metable_id' => $id,
                    ]);

                if ($meta->usesUniqueIds()) {
                    $meta->setUniqueIds();
                }

                $meta->updateTimestamps();

                return $meta->getAttributes();
            })
            ->values()
            ->all();
    }

    /**
     * Get the value the same way it would have been used as a fallback value:
     * Typecast columns hold the serialized value of the type given in `$metaKeys`.
     */
    protected function getValue(mixed $value, ?string $cast): mixed
    {
        if ($value && is_scalar($value) && $cast) {
            return $this->getMetaClassName()::getDataTypeRegistry()
                ->getHandlerForType($cast)
                ->unserializeValue((string) $value);
        }

        return $value;
    }

    /**
     * Get the publishing time for the given row.
     */
    protected function getPublishedAt(object $row): Carbon
    {
        $value = is_string($this->publishedAt) ? $row->{$this->publishedAt} : $this->publishedAt;

        if ($value instanceof DateTimeInterface || is_string($value) || is_int($value)) {
            return Carbon::parse($value);
        }

        return Carbon::now();
    }

    /**
     * Remove records that already exist for the same model, key and publishing time.
     *
     * @param  Collection<int, array<string, mixed>>  $records
     * @return Collection<int, array<string, mixed>>
     */
    protected function withoutExisting(Model $model, Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return $records;
        }

        $existing = (new ($this->getMetaClassName()))
            ->newQueryWithoutScopes()
            ->toBase()
            ->where('metable_type', $model->getMorphClass())
            ->whereIn('metable_id', $records->pluck('metable_id')->unique()->values()->all())
            ->whereIn('key', $records->pluck('key')->unique()->values()->all())
            ->get(['metable_id', 'key', 'published_at'])
            ->map(fn(object $meta) => $this->identify((array) $meta))
            ->flip();

        return $records
            ->reject(fn(array $record) => $existing->has($this->identify($record)))
            ->values();
    }

    /**
     * Identify a record by its model, key and publishing time.
     *
     * @param  array<mixed>  $record
     */
    protected function identify(array $record): string
    {
        $id = $record['metable_id'];
        $key = $record['key'];
        $publishedAt = $record['published_at'];

        return implode('|', [
            is_scalar($id) ? (string) $id : '',
            is_scalar($key) ? (string) $key : '',
            is_string($publishedAt) || $publishedAt instanceof DateTimeInterface
                ? Carbon::parse($publishedAt)->getTimestamp()
                : '',
        ]);
    }

    /**
     * Get Meta model class name.
     *
     * @return class-string<Meta>
     */
    protected function getMetaClassName(): string
    {
        $class = config('multiplex.model', Meta::class);

        if (!is_string($class) || !is_a($class, Meta::class, true)) {
            throw MetaException::invalidModel();
        }

        return $class;
    }
}
