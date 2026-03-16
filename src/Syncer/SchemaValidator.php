<?php

namespace Blocks\Database\Syncer;

class SchemaValidator {

    /**
     * Validates a parsed YAML schema array.
     * Returns an array of error messages. Empty array means valid.
     */
    public function validate(array $schema, string $schemaPath): array {
        $errors = [];
        $identifier = "File: " . basename($schemaPath);

        // 1. Table definition
        if (!isset($schema['table']['name'])) {
            $errors[] = "$identifier - Missing 'table.name'.";
        }

        // 2. Columns definition
        if (!isset($schema['columns']) || !is_array($schema['columns'])) {
            $errors[] = "$identifier - Missing or invalid 'columns' block.";
            return $errors; // Can't validate columns if it's missing
        }

        foreach ($schema['columns'] as $colName => $colDef) {
            if (!isset($colDef['type'])) {
                $errors[] = "$identifier (Column: $colName) - Missing 'type'.";
                continue;
            }

            // Check for valid MySQL type
            $typeError = $this->validateMySqlType($colDef['type']);
            if ($typeError) {
                $errors[] = "$identifier (Column: $colName) - $typeError";
            }
        }

        // 3. Indexes definition
        if (isset($schema['indexes'])) {
            if (!is_array($schema['indexes'])) {
                $errors[] = "$identifier - 'indexes' block must be an array.";
            } else {
                foreach ($schema['indexes'] as $idxName => $idxDef) {
                    if (!isset($idxDef['columns'])) {
                        $errors[] = "$identifier (Index: $idxName) - Missing 'columns' array.";
                    } elseif (!is_array($idxDef['columns'])) {
                        $errors[] = "$identifier (Index: $idxName) - 'columns' must be a list/array of column names.";
                    } else {
                        // Ensure index columns actually exist in the table definition
                        foreach ($idxDef['columns'] as $idxCol) {
                            if (!isset($schema['columns'][$idxCol])) {
                                $errors[] = "$identifier (Index: $idxName) - References non-existent column '$idxCol'.";
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Helper to catch common invalid types (like 'string' instead of 'varchar')
     */
    private function validateMySqlType(string $typeDef): ?string {
        $type = strtolower(trim($typeDef));

        // Extract base type before parenthesis (e.g. "string(255)" -> "string")
        $baseType = preg_replace('/\(.*\)/', '', $type);

        $commonMistakes = [
            'string' => 'VARCHAR',
            'integer' => 'INT',
            'boolean' => 'TINYINT(1)',
        ];

        if (array_key_exists($baseType, $commonMistakes)) {
            return "Invalid type '{$baseType}'. Did you mean '" . $commonMistakes[$baseType] . "'?";
        }

        // List of allowed base MySQL types (non-exhaustive but covers the primary ones)
        $allowedBaseTypes = [
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint',
            'decimal', 'numeric', 'float', 'double', 'bit',
            'date', 'time', 'datetime', 'timestamp', 'year',
            'char', 'varchar', 'binary', 'varbinary',
            'tinyblob', 'blob', 'mediumblob', 'longblob',
            'tinytext', 'text', 'mediumtext', 'longtext',
            'enum', 'set', 'json'
        ];

        if (!in_array($baseType, $allowedBaseTypes)) {
            return "Unsupported MySQL type '$baseType'.";
        }

        return null;
    }
}
