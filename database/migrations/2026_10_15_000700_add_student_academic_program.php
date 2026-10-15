<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('course')->nullable()->after('department');
            $table->string('major')->nullable()->after('course');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn(['course', 'major']));
    }
};
