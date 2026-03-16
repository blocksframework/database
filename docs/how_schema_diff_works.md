# Database Schema Synchronization Engine

**Date:** March 16, 2026

## Overview

The Database Schema Synchronization Engine is a developer tool that automatically guarantees the live MySQL/MariaDB database matches predefined YAML `.yml` schemas located in the application and corresponding reusable framework modules.

Running `./bin/console check-database-structure` calculates the exact `CREATE`, `ALTER`, or `DROP` statements required and runs them so developers do not need to manage individual SQL up/down migrations manually during development.

## How It Works

### 1. Loader & Validation
The engine scans for YAML files in:
*   Application database directory: `/database/*.yml`
*   Framework Modules directories: `/vendor/blocksframework/*/database/*.yml`

Before any processing begins, **schemas are proactively validated**. The `SchemaValidator` will instantly throw a helpful formatting error and exit `(code 1)` if:
*   A YAML file contains syntax errors.
*   A type does not match a valid MySQL identifier (e.g. catches typos like `string(255)` and suggests `VARCHAR(255)`).
*   Keys like `table.name`, `columns`, or missing index variables are incomplete.

### 2. State Comparison (Diffing)
For every valid YAML schema, the tool runs a `DatabaseInspector` to pull the precise, actual table definition from the active database. 
The `Differ` class compares the two states logically:

*   **New Tables:** Generates complete `CREATE TABLE` structures.
*   **Safe Mutations:** Outputs `ADD COLUMN`, `MODIFY COLUMN` (if types, defaults, or collation change), and `ADD INDEX` operations dynamically.
*   **Unsafe Mutations:** If a column or index exists in the DB but was removed from YAML, it schedules a `DROP` command.
*   **Type Normalization:** Intelligently discounts hidden database display-widths. For example, modern MariaDB stripping `INT(11)` down to `int` will **not** trigger an infinite `ALTER` update loop.

### 3. Execution & Safety Controls
*   **Safe changes** are executed automatically without user intervention.
*   **Destructive changes** (such as dropping entire columns that may contain data) will pause the prompt and ask the user `"Do you want to execute these destructive changes? (y/n)"`.
*   Skipping the prompt gracefully logs the skip, while passing `-y` directly to the CLI command auto-confirms drops (useful in CI pipelines).

### Naming Conventions
Tables are constructed securely via backend prefixes to avoid collisions:
*   **Modules:** `module_name.table_name`
*   **Application:** `app_name_from_composer.table_name`

*(Note: These translate to tables containing literal dots in MySQL, which the engine always bounds with string-escaped backticks: `` `domain.domain` ``)*
