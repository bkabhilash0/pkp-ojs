<?php

/**
 * @file classes/migration/install/TemporaryFilesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemporaryFilesMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TemporaryFilesMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Temporary file storage
        Schema::create('temporary_files', function (Blueprint $table) {
            $table->comment('Temporary files, e.g. where files are kept during an upload process before they are moved somewhere more appropriate.');
            $table->bigInteger('file_id')->autoIncrement();

            $table->bigInteger('user_id');
            $table->foreign('user_id', 'temporary_files_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'temporary_files_user_id');

            $table->string('file_name', 90);
            $table->string('file_type', 255)->nullable();
            $table->bigInteger('file_size');
            $table->string('original_file_name', 127)->nullable();
            $table->datetime('date_uploaded');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('temporary_files');
    }
}
