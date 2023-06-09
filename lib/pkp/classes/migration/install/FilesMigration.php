<?php

/**
 * @file classes/migration install/FilesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilesMigration
 *
 * @brief Create the files database table
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FilesMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->comment('Records information in the database about files tracked by the system, linking them to the local filesystem.');
            $table->bigIncrements('file_id');
            $table->string('path', 255);
            $table->string('mimetype', 255);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('files');
    }
}
