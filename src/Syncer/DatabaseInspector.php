<?php

namespace Blocks\Database\Syncer;

class DatabaseInspector {
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function inspectTable(string $tableName): ?array {
        // Check if table exists
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        if (!$stmt->fetch()) {
            return null; // table doesn't exist
        }

        // Fetch columns
        $columns = [];
        $stmt = $this->pdo->query("SHOW FULL COLUMNS FROM `" . $tableName . "`");
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Type normalisation for int
            $type = strtolower($row['Type']);
            if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)$/i', $type, $matches)) {
                $type = $matches[1]; // discard the (11) part for ints
            }

            $columns[$row['Field']] = [
                'type' => strtolower($type),
                'collation' => $row['Collation'],
                'nullable' => $row['Null'] === 'YES',
                'default' => $row['Default'],
                'extra' => $row['Extra'], // e.g. auto_increment
            ];
        }

        // Fetch indices
        $indexes = [];
        $stmt = $this->pdo->query("SHOW INDEX FROM `" . $tableName . "`");
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $indexName = $row['Key_name'];
            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'name' => $indexName,
                    'unique' => $row['Non_unique'] == 0,
                    'columns' => []
                ];
            }
            $indexes[$indexName]['columns'][] = $row['Column_name'];
        }

        return [
            'columns' => $columns,
            'indexes' => $indexes
        ];
    }
}
