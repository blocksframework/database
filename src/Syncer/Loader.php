<?php

namespace Blocks\Database\Syncer;

use Blocks\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class Loader {
    public function loadAll(): array {
        $schemas = [];
        $validator = new SchemaValidator();
        $validationErrors = [];

        // App schemas
        $appName = $this->getAppName();
        $appDatabaseDir = DIR_PATH . '/database';
        
        if (is_dir($appDatabaseDir)) {
            $files = Filesystem::globMatcher([$appDatabaseDir . '/*.yml', $appDatabaseDir . '/*.yaml'], Filesystem::SCOPE_FILE);
            foreach ($files as $fileInfo) {
                $file = $fileInfo->getPathname();
                try {
                    $yaml = Yaml::parseFile($file);
                    if (!is_array($yaml)) {
                        $validationErrors[] = "File: " . basename($file) . " - Schema is empty or not valid YAML.";
                        continue;
                    }
                    
                    $errors = $validator->validate($yaml, $file);
                    if (!empty($errors)) {
                        $validationErrors = array_merge($validationErrors, $errors);
                    } else if (isset($yaml['table']['name'])) {
                        $tableName = $appName . '.' . $yaml['table']['name'];
                        $schemas[$tableName] = $yaml;
                    }
                } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
                    $validationErrors[] = "File: " . basename($file) . " - YAML Parse Error: " . $e->getMessage();
                }
            }
        }

        // Framework Modules
        $modulesDir = DIR_PATH . '/vendor/blocksframework/*';
        $blocksframework_modules = glob($modulesDir, GLOB_ONLYDIR);
        
        if ($blocksframework_modules) {
            foreach ($blocksframework_modules as $modulePath) {
                $moduleName = basename($modulePath);
                $moduleDatabaseDir = $modulePath . '/database';
                
                if (is_dir($moduleDatabaseDir)) {
                    $files = Filesystem::globMatcher([$moduleDatabaseDir . '/*.yml', $moduleDatabaseDir . '/*.yaml'], Filesystem::SCOPE_FILE);
                    foreach ($files as $fileInfo) {
                        $file = $fileInfo->getPathname();
                        try {
                                $yaml = Yaml::parseFile($file);
                                if (!is_array($yaml)) {
                                    $validationErrors[] = "File: " . basename($file) . " - Schema is empty or not valid YAML.";
                                    continue;
                                }

                                $errors = $validator->validate($yaml, $file);
                                if (!empty($errors)) {
                                    $validationErrors = array_merge($validationErrors, $errors);
                                } else if (isset($yaml['table']['name'])) {
                                    $tableName = $moduleName . '.' . $yaml['table']['name'];
                                    $schemas[$tableName] = $yaml;
                                }
                            } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
                                $validationErrors[] = "File: " . basename($file) . " - YAML Parse Error: " . $e->getMessage();
                            }
                    }
                }
            }
        }

        if (!empty($validationErrors)) {
            echo "\n[ERROR] Database schema validation failed:\n";
            foreach ($validationErrors as $error) {
                echo "  - " . $error . PHP_EOL;
            }
            exit(1);
        }

        return $schemas;
    }

    private function getAppName(): string {
        $composerFile = DIR_PATH . '/composer.json';
        if (file_exists($composerFile)) {
            $data = json_decode(file_get_contents($composerFile), true);
            if (isset($data['name'])) {
                $name = $data['name'];
                $name = str_replace(['/', '-'], '_', $name);
                return $name;
            }
        }
        return 'app'; // fallback
    }
}
