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
        Schema::table('leads', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('state');
            $table->date('anniversary_date')->nullable()->after('birth_date');
            $table->smallInteger('last_birthday_wished_year')->nullable()->after('anniversary_date');
            $table->smallInteger('last_anniversary_wished_year')->nullable()->after('last_birthday_wished_year');

            $table->index('birth_date');
            $table->index('anniversary_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['birth_date']);
            $table->dropIndex(['anniversary_date']);
            $table->dropColumn([
                'birth_date',
                'anniversary_date',
                'last_birthday_wished_year',
                'last_anniversary_wished_year',
            ]);
        });
    }
};
