<?php

namespace Kolossal\Multiplex\Tests;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Kolossal\Multiplex\Tests\Mocks\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PrimaryKeyTypesTest extends TestCase
{
    protected function refreshDatabaseWithType($type): void
    {
        config()->set('multiplex.morph_type', $type);

        $this->artisan('migrate:fresh');

        $this->defineDatabaseMigrations();
    }

    #[Test]
    #[DataProvider('morphTypes')]
    public function it_uses_the_configured_column_type(string $type, string $column_type): void
    {
        $this->refreshDatabaseWithType($type);

        if (version_compare(app()->version(), '10.0.0', '>')) {
            $this->assertSame($column_type, Schema::getColumnType('meta', 'id'));
        }

        $this->assertSame($type, config('multiplex.morph_type'));

        $meta = Post::factory()->create()->saveMeta('foo', 'bar');

        if (config('multiplex.morph_type') === 'uuid') {
            $this->assertTrue(Str::isUuid($meta->id));
            $this->assertEquals('string', $meta->getKeyType());
        } elseif (config('multiplex.morph_type') === 'ulid') {
            $this->assertTrue(Str::isUlid($meta->id));
            $this->assertEquals('string', $meta->getKeyType());
        } else {
            $this->assertIsInt($meta->id);
            $this->assertEquals('int', $meta->getKeyType());
        }
    }

    #[Test]
    #[DataProvider('stringMorphTypes')]
    public function it_throws_error_for_invalid_unique_ids(string $type): void
    {
        $this->refreshDatabaseWithType($type);

        $meta = Post::factory()->create()->saveMeta('foo', 'bar');

        $this->expectException(ModelNotFoundException::class);

        $meta->resolveRouteBinding('abc-123', 'id');
    }

    #[Test]
    #[DataProvider('stringMorphTypes')]
    public function it_throws_error_for_invalid_unique_ids_with_implicit_key_name(string $type): void
    {
        $this->refreshDatabaseWithType($type);

        $meta = Post::factory()->create()->saveMeta('foo', 'bar');

        $this->expectException(ModelNotFoundException::class);

        $meta->resolveRouteBinding('abc-123');
    }

    #[Test]
    public function it_resolves_unique_id_models_by_key(): void
    {
        $this->refreshDatabaseWithType('integer');

        $meta = Post::factory()->create()->saveMeta('foo', 'bar');

        $this->assertTrue($meta->is($meta->resolveRouteBinding($meta->id)));
        $this->assertTrue($meta->is($meta->resolveRouteBinding($meta->id, 'id')));
    }

    #[Test]
    #[DataProvider('stringMorphTypes')]
    public function it_resolves_integer_id_models_by_key(string $type): void
    {
        $this->refreshDatabaseWithType($type);

        $meta = Post::factory()->create()->saveMeta('foo', 'bar');

        $this->assertTrue($meta->is($meta->resolveRouteBinding($meta->id)));
        $this->assertTrue($meta->is($meta->resolveRouteBinding($meta->id, 'id')));
    }

    #[Test]
    public function it_throws_exception_for_invalid_morph_type_configuration(): void
    {
        $this->expectException(Exception::class);

        $this->refreshDatabaseWithType(null);
    }

    public static function morphTypes(): array
    {
        return [
            'integer' => ['integer', 'integer'],
            'uuid' => ['uuid', 'varchar'],
            'ulid' => ['ulid', 'varchar'],
        ];
    }

    public static function stringMorphTypes(): array
    {
        return [
            'uuid' => ['uuid'],
            'ulid' => ['ulid'],
        ];
    }
}
