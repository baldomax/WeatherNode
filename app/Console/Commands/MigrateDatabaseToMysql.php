<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateDatabaseToMysql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:migrate-to-mysql 
                            {--sqlite-path= : Path to SQLite database file}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from SQLite database to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if MySQL is already configured
        if (config('database.default') !== 'mysql') {
            $this->error('Current database connection is not MySQL. Please configure MySQL in .env first.');
            $this->info('Set DB_CONNECTION=mysql and configure DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD');
            return 1;
        }

        // Get SQLite database path
        $sqlitePath = $this->option('sqlite-path');
        if (!$sqlitePath) {
            // Try to get from current .env or default location
            $sqlitePath = config('database.connections.sqlite.database');
            if (!$sqlitePath || !file_exists($sqlitePath)) {
                $sqlitePath = $this->ask('Enter path to SQLite database file', database_path('database.sqlite'));
            }
        }

        if (!file_exists($sqlitePath)) {
            $this->error("SQLite database file not found: {$sqlitePath}");
            return 1;
        }

        $this->info("Source SQLite: {$sqlitePath}");
        $this->info("Target MySQL: " . config('database.connections.mysql.database'));

        if (!$this->option('force')) {
            if (!$this->confirm('This will copy all data from SQLite to MySQL. Continue?')) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        // Configure SQLite connection temporarily
        config(['database.connections.sqlite_migrate' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        try {
            // Test connections
            $this->info('Testing database connections...');
            DB::connection('sqlite_migrate')->getPdo();
            DB::connection('mysql')->getPdo();
            $this->info('✓ Both connections successful');

            // Get list of tables to migrate
            $tables = $this->getTables('sqlite_migrate');

            if (empty($tables)) {
                $this->warn('No tables found in SQLite database.');
                return 0;
            }

            $this->info('Found ' . count($tables) . ' tables to migrate: ' . implode(', ', $tables));

            // Ensure MySQL tables exist (run migrations first)
            if (!$this->option('force')) {
                if (!$this->confirm('Have you run migrations on MySQL database? (php artisan migrate --force)')) {
                    $this->warn('Please run migrations first: php artisan migrate --force');
                    return 1;
                }
            }

            // Migrate each table
            $totalRecords = 0;
            foreach ($tables as $table) {
                $count = $this->migrateTable($table);
                $totalRecords += $count;
                $this->info("  ✓ {$table}: {$count} records");
            }

            $this->newLine();
            $this->info("✓ Migration complete! Migrated {$totalRecords} total records across " . count($tables) . " tables.");

            return 0;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Get list of tables from database
     */
    protected function getTables(string $connection): array
    {
        $tables = DB::connection($connection)
            ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

        return array_map(fn($table) => $table->name, $tables);
    }

    /**
     * Migrate a single table
     */
    protected function migrateTable(string $tableName): int
    {
        // Check if table exists in MySQL
        if (!Schema::connection('mysql')->hasTable($tableName)) {
            $this->warn("  ⚠ Table {$tableName} does not exist in MySQL. Skipping.");
            return 0;
        }

        // Clear existing data (optional - comment out if you want to append)
        // DB::connection('mysql')->table($tableName)->truncate();

        // Get all records from SQLite
        $records = DB::connection('sqlite_migrate')->table($tableName)->get();

        if ($records->isEmpty()) {
            return 0;
        }

        // Get column names
        $columns = Schema::connection('mysql')->getColumnListing($tableName);

        // Insert in chunks to avoid memory issues
        $chunks = $records->chunk(100);
        $count = 0;

        foreach ($chunks as $chunk) {
            $insertData = [];
            foreach ($chunk as $record) {
                $row = (array) $record;
                // Only include columns that exist in MySQL
                $filteredRow = array_intersect_key($row, array_flip($columns));
                
                // Handle timestamps - SQLite stores as strings, MySQL needs proper format
                if (isset($filteredRow['created_at']) && is_string($filteredRow['created_at'])) {
                    $filteredRow['created_at'] = $this->normalizeTimestamp($filteredRow['created_at']);
                }
                if (isset($filteredRow['updated_at']) && is_string($filteredRow['updated_at'])) {
                    $filteredRow['updated_at'] = $this->normalizeTimestamp($filteredRow['updated_at']);
                }
                if (isset($filteredRow['recorded_at']) && is_string($filteredRow['recorded_at'])) {
                    $filteredRow['recorded_at'] = $this->normalizeTimestamp($filteredRow['recorded_at']);
                }
                if (isset($filteredRow['lightning_time']) && is_string($filteredRow['lightning_time'])) {
                    $filteredRow['lightning_time'] = $this->normalizeTimestamp($filteredRow['lightning_time']);
                }

                // Handle JSON columns
                if (isset($filteredRow['battery_status']) && is_string($filteredRow['battery_status'])) {
                    $decoded = json_decode($filteredRow['battery_status'], true);
                    $filteredRow['battery_status'] = $decoded !== null ? json_encode($decoded) : null;
                }

                $insertData[] = $filteredRow;
            }

            if (!empty($insertData)) {
                try {
                    DB::connection('mysql')->table($tableName)->insert($insertData);
                    $count += count($insertData);
                } catch (\Exception $e) {
                    $this->warn("  ⚠ Error inserting chunk into {$tableName}: " . $e->getMessage());
                    // Try inserting one by one to find the problematic record
                    foreach ($insertData as $row) {
                        try {
                            DB::connection('mysql')->table($tableName)->insert($row);
                            $count++;
                        } catch (\Exception $e2) {
                            $this->warn("  ⚠ Skipped record: " . $e2->getMessage());
                        }
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Normalize timestamp format for MySQL
     */
    protected function normalizeTimestamp(?string $timestamp): ?string
    {
        if (empty($timestamp)) {
            return null;
        }

        // SQLite timestamps might be in various formats
        // Try to parse and reformat to MySQL format
        try {
            $dt = new \DateTime($timestamp);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // If parsing fails, return as-is (might be null or already correct)
            return $timestamp;
        }
    }
}
