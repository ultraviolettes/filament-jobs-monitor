<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('queue_monitors', function (Blueprint $table) {
            $table->string('batch_id')->nullable()->index()->after('queue');
            $table->string('chain_id', 64)->nullable()->index()->after('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_monitors', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'chain_id']);
        });
    }
};
