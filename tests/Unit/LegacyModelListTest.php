<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\Database;
use App\Fec\Legacy\Joomla\Mvc\ModelList;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * JModelList as Joomla 2.5 paged a list (com_partnerdesk's desk): the context from the
 * class, items of the page, the total, and a start past the end pulled back to the last page.
 */
final class LegacyModelListTest extends TestCase
{
    private function db(): Database
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE jos_fec_brand_submissions (id INTEGER PRIMARY KEY, brand_name TEXT, status INTEGER)');
        for ($i = 1; $i <= 7; $i++) {
            $pdo->exec("INSERT INTO jos_fec_brand_submissions (id, brand_name, status) VALUES ($i, 'Brand $i', ".($i % 2).')');
        }
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

    private function model(Database $db, int $start, int $limit): ModelList
    {
        $model = new FecTestModelSubmissions(['dbo' => $db, 'filter_fields' => ['id']]);
        $model->fixed = ['list.start' => $start, 'list.limit' => $limit, 'list.ordering' => 'id', 'list.direction' => 'ASC'];

        return $model;
    }

    public function test_context_items_total_and_start(): void
    {
        $db = $this->db();

        $model = $this->model($db, 3, 3);
        $this->assertSame('com_fectest.submissions', $model->context());
        $this->assertSame(7, $model->getTotal());
        $this->assertSame(3, $model->getStart());
        $this->assertSame([4, 5, 6], array_map(fn ($r) => (int) $r->id, $model->getItems()));

        // a start past the last page goes back to the last page, as in Joomla 2.5
        $model = $this->model($db, 9, 3);
        $this->assertSame(6, $model->getStart());
        $this->assertSame([7], array_map(fn ($r) => (int) $r->id, $model->getItems()));

        // limit 0: everything
        $model = $this->model($db, 0, 0);
        $this->assertCount(7, $model->getItems());
    }
}

/** A list model named as Joomla names them, with its state fixed instead of read from the request. */
final class FecTestModelSubmissions extends ModelList
{
    /** @var array<string, mixed> */
    public array $fixed = [];

    public function context(): string
    {
        return (string) $this->context;
    }

    protected function populateState($ordering = null, $direction = null)
    {
        foreach ($this->fixed as $k => $v) {
            $this->setState($k, $v);
        }
    }

    protected function getListQuery()
    {
        $db = $this->getDbo();

        return $db->getQuery(true)->select('a.*')->from($db->quoteName('#__fec_brand_submissions').' AS a')
            ->order($db->escape((string) $this->getState('list.ordering')).' '.$db->escape((string) $this->getState('list.direction')));
    }
}
