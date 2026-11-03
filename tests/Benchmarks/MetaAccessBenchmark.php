<?php

/**
 * Micro benchmark for reading meta values from eager loaded models.
 * Not part of the default test suite, run it explicitly:
 *
 * vendor/bin/pest tests/Benchmarks/MetaAccessBenchmark.php
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\PostWithManyMetaKeys;

uses(RefreshDatabase::class);

function benchmarkMetaAccess(string $class, int $models, int $metaCount, int $reads, int $runs = 5): array
{
    $now = Carbon::now()->subDay();

    for ($i = 0; $i < $models; $i++) {
        $post = $class::query()->forceCreate(['title' => "Post {$i}"]);

        foreach (range(1, $metaCount) as $n) {
            $post->allMeta()->forceCreate([
                'key' => "meta_{$n}",
                'value' => "value {$n}",
                'published_at' => $now,
            ]);
        }
    }

    $timings = [];

    for ($run = 0; $run < $runs; $run++) {
        $posts = $class::with('meta')->get();

        $start = hrtime(true);

        foreach ($posts as $post) {
            for ($r = 0; $r < $reads; $r++) {
                foreach (range(1, $metaCount) as $n) {
                    $post->{"meta_{$n}"};
                }

                $post->title;
                $post->id;
            }
        }

        $timings[] = (hrtime(true) - $start) / 1e6;
    }

    sort($timings);

    return [
        'median' => $timings[intdiv(count($timings), 2)],
        'min' => $timings[0],
    ];
}

it('benchmarks meta access', function (string $class, int $models) {
    $result = benchmarkMetaAccess($class, $models, metaCount: 30, reads: 10);

    fwrite(STDERR, sprintf(
        "\n%s, %d model(s) × 30 keys × 10 reads: median %.2f ms, min %.2f ms\n",
        class_basename($class),
        $models,
        $result['median'],
        $result['min'],
    ));

    expect($result['median'])->toBeGreaterThan(0);
})->with([
    'wildcard, 1 model' => [Post::class, 1],
    'explicit keys, 1 model' => [PostWithManyMetaKeys::class, 1],
    'wildcard, 100 models' => [Post::class, 100],
    'explicit keys, 100 models' => [PostWithManyMetaKeys::class, 100],
]);
