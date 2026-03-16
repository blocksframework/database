<?php

namespace Blocks\Database\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Blocks\Database\Syncer\SchemaSynchronizer;
use Blocks\Database\Syncer\DatabaseInspector;

class DatabaseSyncIntegrationTest extends TestCase {
    private \PDO $pdo;
    private SchemaSynchronizer $synchronizer;

    protected function setUp(): void {
        // Force CLI YES flag for destructive changes in tests to bypass standard input prompts
        if (!isset($_SERVER['argv'])) {
            $_SERVER['argv'] = [];
        }
        if (!in_array('-y', $_SERVER['argv'], true)) {
            $_SERVER['argv'][] = '-y';
        }

        // Connect to Docker Compose MariaDB
        $dsn = "mysql:host=127.0.0.1;port=33066;dbname=test_integration;charset=utf8mb4";
        
        try {
            $this->pdo = new \PDO($dsn, 'root', 'root');
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            $this->markTestSkipped("Cannot connect to integration test database: " . $e->getMessage() . "\nPlease run `docker-compose up -d` in the tests directory first.");
        }

        // Clean database before each test
        $this->pdo->exec("DROP TABLE IF EXISTS `test_users_sync`");

        $this->synchronizer = new SchemaSynchronizer($this->pdo);
    }

    protected function tearDown(): void {
        if (isset($this->pdo)) {
            $this->pdo->exec("DROP TABLE IF EXISTS `test_users_sync`");
        }
    }

    public function testFullSyncLifecycle() {
        $tableName = 'test_users_sync';

        // ----------------------------------------------------
        // PHASE 1: CREATE TABLE FROM SCRATCH
        // ----------------------------------------------------
        $initialSchema = [
            'table' => [
                'name' => 'test_users_sync',
                'type' => 'InnoDB'
            ],
            'columns' => [
                'id' => [
                    'type' => 'INT(11)',
                    'index' => 'primary',
                    'autoincrement' => true
                ],
                'email' => [
                    'type' => 'VARCHAR(255)',
                    'nullable' => false,
                    'index' => 'unique' // Inline unique index mapping
                ],
                'status' => [
                    'type' => "ENUM('active', 'pending')",
                    'default' => 'pending'
                ]
            ]
        ];

        // Buffer CLI output to keep PHPUnit console clean
        ob_start();
        $this->synchronizer->sync([$tableName => $initialSchema]);
        ob_end_clean();

        // Query Database Inspector to Assert Creation
        $inspector = new DatabaseInspector($this->pdo);
        $dbSchema1 = $inspector->inspectTable($tableName);
        
        $this->assertNotNull($dbSchema1, "Table should have been created.");
        $this->assertArrayHasKey('email', $dbSchema1['columns']);
        $this->assertEquals('varchar(255)', $dbSchema1['columns']['email']['type']);

        // ----------------------------------------------------
        // PHASE 2: ALTER TABLE (Safe adjustments)
        // ----------------------------------------------------
        $alteredSchema = $initialSchema;
        // Strip the old inline index and add a new top level one
        unset($alteredSchema['columns']['email']['index']);
        
        // Add new column
        $alteredSchema['columns']['new_text_col'] = [
            'type' => 'TEXT',
            'nullable' => true
        ];
        
        // Add explicitly mapped index block
        $alteredSchema['indexes'] = [
            'uniq_email' => [
                'type' => 'unique',
                'columns' => ['email']
            ],
            'idx_status' => [
                'type' => 'index',
                'columns' => ['status']
            ]
        ];

        ob_start();
        $this->synchronizer->sync([$tableName => $alteredSchema]);
        ob_end_clean();

        $dbSchema2 = $inspector->inspectTable($tableName);
        
        $this->assertArrayHasKey('new_text_col', $dbSchema2['columns'], "The new column should have been added safely via ALTER TABLE.");
        $this->assertArrayHasKey('idx_status', $dbSchema2['indexes'], "Top level idx_status index should have been added.");

        // ----------------------------------------------------
        // PHASE 3: DESTRUCTIVE CHANGE (Dropping Column)
        // ----------------------------------------------------
        $droppedSchema = $alteredSchema;
        unset($droppedSchema['columns']['email']); // drop email column

        // Execution here will auto-accept because of `-y` injected in setUp()
        ob_start();
        $this->synchronizer->sync([$tableName => $droppedSchema]);
        ob_end_clean();

        $dbSchema3 = $inspector->inspectTable($tableName);
        
        $this->assertArrayNotHasKey('email', $dbSchema3['columns'], "The email column should have been destroyed.");
    }
}
