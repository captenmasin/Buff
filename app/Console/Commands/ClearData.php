<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('app:clear-data {--force : Delete without prompting for confirmation}')]
#[Description('Delete all database data except user accounts')]
class ClearData extends Command
{
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all data except user accounts?')) {
            $this->info('No data was deleted.');

            return self::SUCCESS;
        }

        $preservedTables = [
            (new User)->getTable(),
            config('database.migrations.table', 'migrations'),
        ];

        DB::transaction(function () use ($preservedTables): void {
            collect(Schema::getTableListing(schemaQualified: false))
                ->reject(fn (string $table): bool => in_array($table, $preservedTables, true))
                ->each(fn (string $table): int => DB::table($table)->delete());
        });

        $this->info('All data except user accounts has been deleted.');

        return self::SUCCESS;
    }
}
