<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            if (config('multiplex.morph_type') === 'ulid') {
                $table->ulid('id')->primary();
            } elseif (config('multiplex.morph_type') === 'uuid') {
                $table->uuid('id')->primary();
            } else {
                $table->increments('id');
            }

            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
