<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\Database;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * JDatabase::query() answers as mysqli_query() did on Joomla 2.5: true for any
 * statement that ran, whatever it changed; false only on an error. The models
 * test it with "if (!$db->query())" (the RTP dashboard's upsert, the Atlas
 * dashboard's writes).
 */
final class LegacyDatabaseQueryResultTest extends TestCase
{
    private function db(): Database
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE jos_slot_rtp (id INTEGER PRIMARY KEY, gid INTEGER UNIQUE, notes TEXT)');
        $pdo->exec("INSERT INTO jos_slot_rtp (id, gid, notes) VALUES (1, 7, 'a')");
        $connection = new SQLiteConnection($pdo, ':memory:', 'jos_');

        return new class($connection) extends Database
        {
            public function __construct(private readonly ConnectionInterface $sqlite)
            {
                parent::__construct('test');
            }

            public function connection(): ConnectionInterface
            {
                return $this->sqlite;
            }
        };
    }

    public function test_a_write_that_changes_nothing_is_a_success(): void
    {
        $db = $this->db();

        $db->setQuery("UPDATE #__slot_rtp SET notes = 'a' WHERE gid = 99");
        $this->assertTrue($db->query());
        $this->assertSame(0, $db->getAffectedRows());

        $db->setQuery('DELETE FROM #__slot_rtp WHERE gid = 99');
        $this->assertTrue($db->query());

        $db->setQuery("UPDATE #__slot_rtp SET notes = 'b' WHERE gid = 7");
        $this->assertTrue($db->query());
        $this->assertSame(1, $db->getAffectedRows());
    }

    public function test_a_read_with_no_rows_is_a_success_and_an_error_is_false(): void
    {
        $db = $this->db();

        $db->setQuery('SELECT id FROM #__slot_rtp WHERE gid = 99');
        $this->assertTrue($db->query());

        $db->setQuery('UPDATE #__no_such_table SET x = 1');
        $this->assertFalse(@$db->query());
        $this->assertNotSame(0, $db->getErrorNum());
    }
}
