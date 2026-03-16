<?php

namespace Blocks\Database\Syncer;

use Blocks\Database\MySQL;
use Symfony\Component\Yaml\Yaml;

class SchemaSynchronizer {

    public function run() {
        echo "Discovering schemas..." . PHP_EOL;

        // 1. Get parsed schemas
        $loader = new Loader();
        $expectedSchemas = $loader->loadAll();

        echo sprintf("Found %d tables managed by YAML schemas.", count($expectedSchemas)) . PHP_EOL;

        // 2. Diff and Execute
        $pdo = MySQL::getLink();
        $inspector = new DatabaseInspector($pdo);
        $differ = new Differ();
        $executor = new Executor($pdo);

        foreach ($expectedSchemas as $tableName => $schema) {
            echo PHP_EOL . "Checking table `$tableName`..." . PHP_EOL;
            
            $dbSchema = $inspector->inspectTable($tableName);
            
            if ($dbSchema === null) {
                // Determine missing / new table
                $sql = $differ->generateCreateTable($tableName, $schema);
                $executor->execute([$sql], "Creating table `$tableName`");
                continue;
            }

            // Exist in DB. Compare structures!
            $changes = $differ->diff($tableName, $schema, $dbSchema);

            if (empty($changes['safe']) && empty($changes['unsafe'])) {
                echo "  -> Table is up to date." . PHP_EOL;
                continue;
            }

            // Prompt for unsafe changes (drops)
            if (!empty($changes['unsafe'])) {
                $executor->promptAndExecute($changes['unsafe']);
            }

            if (!empty($changes['safe'])) {
                $executor->execute($changes['safe'], "Applying safe alterations to `$tableName`");
            }
        }
    }
}
