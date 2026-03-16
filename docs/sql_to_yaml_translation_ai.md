# Blocks PHP Framework: SQL to YAML Schema Translation (AI Reference)

This document is intended for AI coding assistants and developers. It provides strict rules and examples for translating standard MySQL `CREATE TABLE` and `ALTER TABLE` statements into the declarative YAML schema format required by the Blocks PHP Framework.

## CRITICAL RULES FOR AI ASSISTANTS

1. **Strict MySQL Types:** Never use pseudo-types or ORM-specific types (e.g., `string`, `integer`, `boolean`). You MUST use explicit MySQL data types (e.g., `VARCHAR(255)`, `INT(11)`, `TINYINT(1)`).
2. **Boolean representation:** Always represent booleans as `TINYINT(1)`.
3. **No Migration Files:** Do not generate PHP based up/down migrations (e.g., Doctrine, Laravel). Schema state is solely defined by `.yml` files in the `database/` directory of the application or module.
4. **Naming:** The `table.name` property in YAML should be the base name without the framework/module prefix. The Syncer tool automatically prefixes the table name based on the `composer.json` of the module/app.

## YAML Format Structure

The schema file consists of three main top-level nodes: `table`, `columns`, and `indexes`.

### 1. The `table` Node

Defines table-level metadata.

```yaml
table:
    name: user_profiles        # Required: Base name of the table
    type: InnoDB               # Optional: Storage engine
    collation: utf8mb4_general_ci # Optional: Default character set and collation
    comment: User profile data # Optional: Table comment
```

### 2. The `columns` Node

A key-value map representing the columns of the table. The key is the exact column name.

**Properties per column:**
* `type` (Required): Exact MySQL type (e.g., `INT(11)`, `VARCHAR(255)`, `DECIMAL(10,2)`, `TEXT`, `DATETIME`).
* `nullable` (Optional): Boolean `true` or `false` (Default is assumed `false` unless explicitly defined or if type implicitly allows).
* `default` (Optional): Default value. E.g., `0`, `"unnamed"`, `CURRENT_TIMESTAMP`. Null defaults should be written as `NULL`.
* `index` (Optional): Used for single-column indexes. Allowed values: `primary`, `unique`, `index`.
* `autoincrement` (Optional): Boolean `true` for `AUTO_INCREMENT` columns.
* `collation` (Optional): Column-specific collation.

### 3. The `indexes` Node (Optional)

Used for defining multi-column or complex indexes. The key is the index name.

**Properties per index:**
* `type`: Either `index` or `unique`.
* `columns`: A sequence (array) of column names that make up the index.

---

## Translation Examples: SQL to YAML

### Example 1: Basic Users Table

**SQL Create Query:**
```sql
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**Equivalent YAML:**
```yaml
table:
    name: users
    type: InnoDB
    collation: utf8mb4_general_ci

columns:
    id:
        type: INT(11)
        index: primary
        autoincrement: true

    email:
        type: VARCHAR(255)
        nullable: false
        index: unique

    password_hash:
        type: VARCHAR(255)
        nullable: false

    is_active:
        type: TINYINT(1)
        default: 1

    created_at:
        type: DATETIME
        default: CURRENT_TIMESTAMP
```

### Example 2: Composite Indexes and Enums

**SQL Create Query:**
```sql
CREATE TABLE `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `total_amount` DECIMAL(10,2) NULL DEFAULT NULL,
  `status` ENUM('pending', 'paid', 'shipped', 'cancelled') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB;
```

**Equivalent YAML:**
```yaml
table:
    name: orders
    type: InnoDB

columns:
    id:
        type: INT(11)
        index: primary
        autoincrement: true

    user_id:
        type: INT(11)
        nullable: false

    total_amount:
        type: DECIMAL(10,2)
        nullable: true
        default: NULL

    status:
        type: ENUM('pending', 'paid', 'shipped', 'cancelled')
        default: 'pending'

indexes:
    idx_user_status:
        type: index
        columns:
            - user_id
            - status
```

## AI Validation Checklist

Before providing a YAML schema, confirm:
- [ ] No pseudo-types are used (No `string`, `integer`, `boolean`).
- [ ] Primary keys are set via `index: primary` on the column.
- [ ] Auto-incrementing columns have `autoincrement: true`.
- [ ] `indexes` block is only used for multi-column grouping, or if a named single-column index is strictly needed. Otherwise use the inline column `index: ...` property.
- [ ] Default `NULL` is specified as `default: NULL` and usually accompanied by `nullable: true`.
