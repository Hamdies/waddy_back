<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('f_name', 100)->nullable();
            $table->string('l_name', 100)->nullable();
            $table->string('phone', 25)->nullable()->unique();
            $table->string('email', 100)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('interest')->nullable();
            $table->string('cm_firebase_token')->nullable();
            $table->string('image', 100)->nullable();
            $table->boolean('is_phone_verified')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->integer('order_count')->default(0);
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->boolean('login_medium')->nullable();
            $table->string('social_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
