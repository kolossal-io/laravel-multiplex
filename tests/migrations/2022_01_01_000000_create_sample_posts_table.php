<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $callback = function (Blueprint $table) {
            if (config('multiplex.morph_type') === 'ulid') {
                $table->ulid('id')->primary();
                $table->ulid('user_id')->nullable();
            } elseif (config('multiplex.morph_type') === 'uuid') {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
            } else {
                $table->increments('id');
                $table->unsignedInteger('user_id')->nullable();
            }

            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->integer('integer_field')->nullable();
            $table->float('float_field')->nullable();
            $table->boolean('boolean_field')->nullable();
            $table->dateTime('datetime_field')->nullable();

            $table->softDeletes();
            $table->timestamps();
        };

        Schema::create('sample_posts', $callback);
        Schema::create('sample_posts_accessor', $callback);
        Schema::create('sample_posts_existing_column', $callback);
        Schema::create('sample_posts_without_soft_delete', $callback);
    }

    public function down()
    {
        Schema::dropIfExists('sample_posts');
        Schema::dropIfExists('sample_posts_accessor');
        Schema::dropIfExists('sample_posts_existing_column');
        Schema::dropIfExists('sample_posts_without_soft_delete');
    }
};
