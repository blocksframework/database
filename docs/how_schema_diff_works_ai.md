# AI Context: Blocks PHP Framework Database Schema Synchronization

**System Prompt / AI Instructions**
You are working within the "Blocks PHP Framework," a proprietary PHP framework. This document outlines how you should handle database schema creations and migrations. Do not assume standard Laravel or Symfony migration patterns.

## 1. Schema Definition Rules
Database schemas are defined entirely in YAML `.yml` files, not in PHP migration classes. 

**File Locations:**
- Application level schemas: `/database/*.yml`
- Module level schemas: `/vendor/blocksframework/<module>/database/*.yml` (or `modules/<module>/database/*.yml` during local development)

**Naming Conventions:**
The engine automatically prefixes the table name with the module or application name. Therefore, inside the YAML file, **only specify the base table name**. The engine will automatically translate it to `` `module_name.table_name` `` or `` `app_name.table_name` `` in the database to prevent collisions.

## 2. YAML Schema Structure
When asked to create or update a database table, you must create or modify a `.yml` file.

**CRITICAL SYNTAX RULES (Differences from other frameworks):**
- **Types:** Do not use `string`, `integer`, or `boolean`. You MUST use valid raw MySQL identifiers (e.g., `VARCHAR(255)`, `INT`, `TINYINT(1)`).
- **Primary Keys:** Use `index: primary` inside the column definition, NOT `primary_key: true`.
- **Auto Increment:** Use `autoincrement: true`, NOT `auto_increment: true`.
- **Nullability:** Columns are `NOT NULL` by default. Use `nullable: true` to allow nulls. DO NOT use `not_null: true`.
- **Nesting:** The top-level `table` key must contain the `name` key as a nested property.

### Example Correct Structure:
```yaml
table:
    name: users    # Engine automatically prefixes this (e.g., account.users)
    type: InnoDB
    collation: utf8mb4_general_ci
    comment: User accounts table

columns:
    user_id:
        type: INT
        index: primary
        autoincrement: true

    email:
        type: VARCHAR(255)
        collation: utf8mb4_general_ci
        # nullable is false by default

    status:
        type: TINYINT(1)
        default: 1
        index: index  # Creates a simple single-column index inline

    created_at:
        type: DATETIME
        default: CURRENT_TIMESTAMP

# For compound or explicitly named indexes
indexes:
    idx_email_status:
        type: unique # or 'index'
        columns:
            - email
            - status
```

## 3. Applying Changes (The Synchronization Engine)
There are no manual `up()` or `down()` migrations. The framework uses a declarative state-comparison engine.

**Command to run:**
When you have finished creating or modifying a `.yml` schema file, you must run the following command in the terminal to synchronize the MySQL/MariaDB database:
```bash
./bin/console check-database-structure
```

**Handling Destructive Changes:**
- If your YAML changes remove a column or index, the engine will prompt for confirmation (`Do you want to execute these destructive changes? (y/n)`). 
- If you are running the command autonomously via tools (e.g., run_in_terminal) and need to force drops or apply blindly, append `-y`:
  ```bash
  ./bin/console check-database-structure -y
  ```

## 4. Troubleshooting
- **Exit Code 1:** If the synchronization command fails immediately, check the syntax of your generated YAML file. The `SchemaValidator` is strict about valid MySQL types and required keys. Always read the CLI output for exact validation errors.
- **Infinite Alter Loops:** Modern MariaDB/MySQL ignores lengths like `INT(11)`. The comparison engine normalizes this behind the scenes so that writing `INT(11)` in YAML vs MySQL reporting `int` doesn't cause constant alteration.
