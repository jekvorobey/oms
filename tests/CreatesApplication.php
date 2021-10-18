<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->silentDropForeignForSqlite();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function silentDropForeignForSqlite(): void
    {
        $connection = config('database.default');

        $driver = config("database.connections.{$connection}.driver");
        Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            return new class ($connection, $database, $prefix, $config) extends SQLiteConnection
            {
                public function getSchemaBuilder(): Builder
                {
                    if ($this->schemaGrammar === null) {
                        $this->useDefaultSchemaGrammar();
                    }

                    return new class ($this) extends SQLiteBuilder
                    {
                        protected function createBlueprint($table, ?\Closure $callback = null)
                        {
                            return new class ($table, $callback) extends Blueprint
                            {
                                /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter */
                                public function dropForeign($index): Fluent
                                {
                                    return new Fluent();
                                }

                                public function string($column, $length = null): Fluent
                                {
                                    return parent::string($column, $length)->default('');
                                }

                                public function tinyInteger($column, $autoIncrement = false, $unsigned = false): Fluent
                                {
                                    return parent::tinyInteger($column, $autoIncrement, $unsigned)->default(0);
                                }

                                public function integer($column, $autoIncrement = false, $unsigned = false)
                                {
                                    return parent::integer($column, $autoIncrement, $unsigned)->default(0);
                                }

                                public function bigInteger($column, $autoIncrement = false, $unsigned = false)
                                {
                                    return parent::bigInteger($column, $autoIncrement, $unsigned)->default(0);
                                }

                                public function boolean($column): Fluent
                                {
                                    return parent::boolean($column)->default(false);
                                }
                            };
                        }
                    };
                }
            };
        });
    }
}
