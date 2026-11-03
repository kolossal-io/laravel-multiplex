<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sample_posts_custom_key', function (Blueprint $table) {
            if (config('multiplex.morph_type') === 'ulid') {
                $table->ulid('post_id')->primary();
            } elseif (config('multiplex.morph_type') === 'uuid') {
                $table->uuid('post_id')->primary();
            } else {
                $table->increments('post_id');
            }

            $table->string('title')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sample_posts_custom_key');
    }
};
