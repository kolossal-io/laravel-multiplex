<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function addKeys(Blueprint &$table): void
    {
        if (config('multiplex.morph_type') === 'uuid') {
            $table->uuid('id');
            $table->uuidMorphs('metable');

            return;
        }

        if (config('multiplex.morph_type') === 'ulid') {
            $table->ulid('id');
            $table->ulidMorphs('metable');

            return;
        }

        if (config('multiplex.morph_type') === 'integer') {
            $table->increments('id');
            $table->morphs('metable');

            return;
        }

        throw new Exception('Please use a valid option for `morph_type` inside the multiplex config file. Must be one of `integer`, `uuid` or `ulid`.');
    }

    public function up(): void
    {
        if (!Schema::hasTable('meta')) {
            Schema::create('meta', function (Blueprint $table) {
                $this->addKeys($table);

                $table->string('key');
                $table->longtext('value')->nullable();
                $table->string('type')->nullable();
                $table->dateTimeTz('published_at')->nullable();

                $table->timestamps();

                $table->index(['metable_type', 'metable_id', 'key', 'published_at', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta');
    }
};
