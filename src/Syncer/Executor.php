<?php

namespace Blocks\Database\Syncer;

class Executor {
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function execute(array $queries, string $message = ''): void {
        if (empty($queries)) {
            return;
        }

        if ($message) {
            echo "  -> " . $message . PHP_EOL;
        }

        foreach ($queries as $sql) {
            echo "     Executing: $sql" . PHP_EOL;
            try {
                $this->pdo->exec($sql);
            } catch (\PDOException $e) {
                echo "     [ERROR] " . $e->getMessage() . PHP_EOL;
            }
        }
    }

    public function promptAndExecute(array $queries): void {
        if (empty($queries)) {
            return;
        }

        echo "\n  [WARNING] The following DESTRUCTIVE changes are required:\n";
        foreach ($queries as $sql) {
            echo "    * " . $sql . PHP_EOL;
        }

        // Get command line args to see if -y was passed
        $argv = $_SERVER['argv'] ?? [];
        $autoYes = in_array('-y', $argv, true);

        if ($autoYes) {
            echo "  -> Auto-answering YES due to `-y` flag." . PHP_EOL;
            $this->execute($queries);
            return;
        }

        echo "  Do you want to execute these destructive changes? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim(strtolower($line)) === 'y') {
            $this->execute($queries);
        } else {
            echo "  -> Skipped destructive changes." . PHP_EOL;
        }
        fclose($handle);
    }
}
