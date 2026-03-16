<?php

namespace Blocks\Database\Syncer;

class Differ {

    public function generateCreateTable(string $tableName, array $schema): string {
        $lines = [];
        
        $columns = $schema['columns'] ?? [];
        foreach ($columns as $colName => $colDef) {
            $lines[] = $this->buildColumnDefinition($colName, $colDef);
        }

        // Handle inline indexes and primary keys
        $primaryKeys = [];
        foreach ($columns as $colName => $colDef) {
            if (isset($colDef['index']) && strtolower($colDef['index']) === 'primary') {
                $primaryKeys[] = "`$colName`";
            }
        }
        if (!empty($primaryKeys)) {
            $lines[] = "PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
        }
        
        // Additional single-column indexes inline
        foreach ($columns as $colName => $colDef) {
            if (isset($colDef['index'])) {
                $idxType = strtolower($colDef['index']);
                if ($idxType === 'unique') {
                    $lines[] = "UNIQUE INDEX `{$colName}_idx` (`$colName`)";
                } elseif ($idxType === 'index') {
                    $lines[] = "INDEX `{$colName}_idx` (`$colName`)";
                }
            }
        }

        // Schema top-level indexes
        if (isset($schema['indexes']) && is_array($schema['indexes'])) {
            foreach ($schema['indexes'] as $idxName => $idxDef) {
                $cols = array_map(fn($c) => "`$c`", $idxDef['columns']);
                $type = isset($idxDef['type']) && strtolower($idxDef['type']) === 'unique' ? 'UNIQUE INDEX' : 'INDEX';
                $lines[] = "$type `$idxName` (" . implode(', ', $cols) . ")";
            }
        }

        $sql = "CREATE TABLE `$tableName` (\n  " . implode(",\n  ", $lines) . "\n)";
        
        $tableDef = $schema['table'] ?? [];
        $options = [];
        if (isset($tableDef['type'])) {
            $options[] = "ENGINE=" . $tableDef['type'];
        }
        if (isset($tableDef['collation'])) {
            $parts = explode('_', $tableDef['collation']);
            $options[] = "DEFAULT CHARSET=" . $parts[0];
            $options[] = "COLLATE=" . $tableDef['collation'];
        }
        if (isset($tableDef['comment'])) {
            $options[] = "COMMENT='" . addslashes($tableDef['comment']) . "'";
        }
        
        if (!empty($options)) {
            $sql .= " " . implode(' ', $options);
        }
        
        $sql .= ";";
        
        return $sql;
    }

    public function diff(string $tableName, array $schema, array $dbSchema): array {
        $safe = [];
        $unsafe = []; // DROPs

        $expectedCols = $schema['columns'] ?? [];
        $dbCols = $dbSchema['columns'];

        // 1. Check added or modified columns
        foreach ($expectedCols as $colName => $colDef) {
            if (!isset($dbCols[$colName])) {
                // ADD Column
                $def = $this->buildColumnDefinition($colName, $colDef);
                $safe[] = "ALTER TABLE `$tableName` ADD COLUMN $def;";
            } else {
                // MODIFY Column
                $dbCol = $dbCols[$colName];
                if ($this->columnNeedsModification($colDef, $dbCol)) {
                    $def = $this->buildColumnDefinition($colName, $colDef);
                    $safe[] = "ALTER TABLE `$tableName` MODIFY COLUMN $def;";
                }
            }
        }

        // 2. Check dropped columns
        foreach ($dbCols as $dbColName => $dbColData) {
            if (!isset($expectedCols[$dbColName])) {
                $unsafe[] = "ALTER TABLE `$tableName` DROP COLUMN `$dbColName`;";
            }
        }

        // 3. Diff Indexes
        $expectedIndexes = $this->normalizeExpectedIndexes($schema);
        $dbIndexes = $this->normalizeDbIndexes($dbSchema['indexes']);

        // Check dropped indices
        foreach ($dbIndexes as $idxName => $idxDef) {
            if ($idxName === 'PRIMARY') continue; // Handled separately or usually constant
            if (!isset($expectedIndexes[$idxName])) {
                $safe[] = "ALTER TABLE `$tableName` DROP INDEX `$idxName`;";
            } else {
                // Check if index definition changed
                $eIdx = $expectedIndexes[$idxName];
                if ($eIdx['unique'] !== $idxDef['unique'] || $eIdx['columns'] !== $idxDef['columns']) {
                    $safe[] = "ALTER TABLE `$tableName` DROP INDEX `$idxName`;";
                    $safe[] = $this->buildAddIndex($tableName, $idxName, $eIdx);
                }
            }
        }

        // Check added indices
        foreach ($expectedIndexes as $idxName => $idxDef) {
            if ($idxName === 'PRIMARY') continue;
            if (!isset($dbIndexes[$idxName])) {
                $safe[] = $this->buildAddIndex($tableName, $idxName, $idxDef);
            }
        }

        return ['safe' => $safe, 'unsafe' => $unsafe];
    }

    private function normalizeExpectedIndexes(array $schema): array {
        $indexes = [];

        // Single columns
        $columns = $schema['columns'] ?? [];
        foreach ($columns as $colName => $colDef) {
            if (isset($colDef['index'])) {
                $type = strtolower($colDef['index']);
                if ($type === 'primary') {
                    $indexes['PRIMARY'] = ['unique' => true, 'columns' => [$colName]];
                } elseif ($type === 'unique' || $type === 'index') {
                    $indexes["{$colName}_idx"] = [
                        'unique' => $type === 'unique',
                        'columns' => [$colName]
                    ];
                }
            }
        }

        // Top level
        if (isset($schema['indexes']) && is_array($schema['indexes'])) {
            foreach ($schema['indexes'] as $idxName => $idxDef) {
                $isUnique = isset($idxDef['type']) && strtolower($idxDef['type']) === 'unique';
                $columns = isset($idxDef['columns']) ? (is_array($idxDef['columns']) ? $idxDef['columns'] : [$idxDef['columns']]) : [];
                $indexes[$idxName] = [
                    'unique' => $isUnique,
                    'columns' => $columns
                ];
            }
        }

        return $indexes;
    }

    private function normalizeDbIndexes(array $dbIndexes): array {
        $indexes = [];
        foreach ($dbIndexes as $name => $data) {
            $indexes[$name] = [
                'unique' => $data['unique'],
                'columns' => $data['columns']
            ];
        }
        return $indexes;
    }

    private function buildAddIndex(string $tableName, string $idxName, array $idxDef): string {
        $type = $idxDef['unique'] ? 'UNIQUE INDEX' : 'INDEX';
        $cols = array_map(fn($c) => "`$c`", $idxDef['columns']);
        return "ALTER TABLE `$tableName` ADD $type `$idxName` (" . implode(', ', $cols) . ");";
    }

    private function buildColumnDefinition(string $colName, array $colDef): string {
        $type = $colDef['type'];
        if (preg_match('/^([A-Za-z_]+)(\(.*?\))$/', $type, $matches)) {
            $type = strtoupper($matches[1]) . $matches[2];
        } else {
            $type = strtoupper($type);
        }
        $sql = "`$colName` $type";

        if (isset($colDef['collation'])) {
            $parts = explode('_', $colDef['collation']);
            $sql .= " CHARACTER SET " . $parts[0] . " COLLATE " . $colDef['collation'];
        }

        if (isset($colDef['nullable']) && $colDef['nullable'] === true) {
            $sql .= " NULL";
        } else {
            $sql .= " NOT NULL";
        }

        if (array_key_exists('default', $colDef)) {
            $default = $colDef['default'];
            if ($default === null || strtoupper((string)$default) === 'NULL') {
                $sql .= " DEFAULT NULL";
            } elseif (is_string($default) && in_array(strtoupper($default), ['CURRENT_TIMESTAMP'])) {
                $sql .= " DEFAULT " . strtoupper($default);
            } elseif (is_numeric($default)) {
                $sql .= " DEFAULT " . $default;
            } else {
                $sql .= " DEFAULT '" . addslashes($default) . "'";
            }
        }

        if (isset($colDef['autoincrement']) && $colDef['autoincrement'] === true) {
            $sql .= " AUTO_INCREMENT";
        }

        return $sql;
    }

    private function columnNeedsModification(array $expected, array $dbData): bool {
        // Normalise type
        $eType = strtolower($expected['type']);
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)$/i', $eType, $matches)) {
            $eType = $matches[1];
        }
        
        if (strpos($eType, 'enum(') === 0) { $eType = preg_replace('/,\s+/', ',', $eType); }
        if ($eType !== $dbData['type']) return true;

        $eNullable = isset($expected['nullable']) && $expected['nullable'] === true;
        if ($eNullable !== $dbData['nullable']) return true;

        // Auto increment check
        $eAutoInit = isset($expected['autoincrement']) && $expected['autoincrement'] === true;
        $dbAutoInit = strpos(strtolower($dbData['extra']), 'auto_increment') !== false;
        if ($eAutoInit !== $dbAutoInit) return true;

        // Default check (loose comparison)
        $eDefault = $expected['default'] ?? null;
        $normE = strtoupper((string)$eDefault);
        $normDb = strtoupper((string)$dbData['default']);
        if ($normE === 'CURRENT_TIMESTAMP()') $normE = 'CURRENT_TIMESTAMP';
        if ($normDb === 'CURRENT_TIMESTAMP()') $normDb = 'CURRENT_TIMESTAMP';
        if ($normE === 'CURRENT_TIMESTAMP' && $normDb === 'CURRENT_TIMESTAMP') {
        } elseif ((string)$eDefault !== (string)$dbData['default']) {
             // Ignoring tiny nuances like `CURRENT_TIMESTAMP()` vs `CURRENT_TIMESTAMP` for now
            return true;
        }

        // Collation check
        if (isset($expected['collation']) && $dbData['collation']) {
            if ($expected['collation'] !== $dbData['collation']) return true;
        }

        return false;
    }
}
