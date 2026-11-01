<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseEngineConfigurationTest extends TestCase
{
    public function test_mysql_connections_default_new_tables_to_innodb(): void
    {
        $this->assertSame('InnoDB', config('database.connections.mysql.engine'));
        $this->assertSame('InnoDB', config('database.connections.mariadb.engine'));
    }
}
