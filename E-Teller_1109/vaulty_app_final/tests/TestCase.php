<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario test case agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard to ensure tests run only against a database whose name ends with "_test".
     * This method is called by the RefreshDatabase trait before it refreshes the DB.
     */
    protected function beforeRefreshingDatabase()
    {
        $dbName = config('database.connections.mysql.database');
        if (!str_ends_with($dbName, '_test')) {
            $this->markTestSkipped("Database [$dbName] does not end with '_test' – aborting tests.");
        }
    }
}
