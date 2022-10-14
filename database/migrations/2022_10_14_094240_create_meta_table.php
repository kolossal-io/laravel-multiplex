<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('meta')) {
            Schema::create('meta', function (Blueprint $table) {
                $table->increments('id');

                $table->morphs('metable');
                $table->string('key');
                $table->longtext('value')->nullable();
                $table->string('type')->nullable();
                $table->dateTimeTz('published_at')->nullable();

                $table->timestamps();

                $table->index(['metable_id', 'metable_type', 'published_at']);
                $table->index(['metable_id', 'metable_type', 'key', 'published_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('meta');
    }
};
