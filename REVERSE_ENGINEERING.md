# Symfony Reverse Engineering Workflow

## Step 1: Configure database connection (`.env`)

Use a generic DSN format (replace placeholders with your own values):

```dotenv
DATABASE_URL="mysql://DB_USER:DB_PASSWORD@127.0.0.1:3306/DB_NAME?serverVersion=8.0.37&charset=utf8mb4"
```

Examples:
- MySQL 8: `serverVersion=8.0.x`
- MariaDB 10.11: `serverVersion=10.11.2-MariaDB`

## Method 1: Custom command

1. Create command scaffold (one time):

```bash
symfony console make:command app:generate:entities
```

2. Command implementation file:
- `src/Command/GenerateEntitiesCommand.php`
- It calls `App\Service\DatabaseReverseEngineer` to:
  - connect with Doctrine DBAL
  - read schema dynamically
  - generate entities in `src/Entity`
  - generate repositories in `src/Repository`
  - convert foreign keys to relations (`ManyToOne`, `OneToOne`, `ManyToMany` for pure join tables)

3. Run generation:

```bash
symfony console app:generate:entities
```

## Method 2: Script approach

1. Script file:
- `reverse-engineer.php`

2. Run script:

```bash
php reverse-engineer.php
```

3. Regenerate accessors/mutators if needed:

```bash
php bin/console make:entity --regenerate
```

## Migrations

1. Generate migration:

```bash
symfony console doctrine:migrations:diff
```

2. Execute migration:

```bash
symfony console doctrine:migrations:migrate
```

## How FK become ORM relationships

- `FK column` with unique constraint (or FK = PK) becomes `#[ORM\OneToOne]`.
- Regular `FK column` becomes `#[ORM\ManyToOne]`.
- A pure join table (exactly 2 FK columns, no extra columns, composite PK = both FK columns) becomes `#[ORM\ManyToMany]` with `#[ORM\JoinTable]`.
- Inverse sides are generated as:
  - `OneToMany` for `ManyToOne`
  - inverse `OneToOne` for `OneToOne`
  - inverse `ManyToMany` for `ManyToMany`
