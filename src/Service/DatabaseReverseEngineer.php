<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\HttpKernel\KernelInterface;

final class DatabaseReverseEngineer
{
    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * Reverse engineer entities/repositories from the current database.
     *
     * @return array{
     *     entities: list<string>,
     *     repositories: list<string>,
     *     warnings: list<string>,
     *     skipped_join_tables: list<string>
     * }
     */
    public function generate(bool $withMethods = true): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTables();
        $joinTables = $this->detectJoinTables($tables);

        $projectDir = $this->kernel->getProjectDir();
        $entityDir = $projectDir . '/src/Entity';
        $repositoryDir = $projectDir . '/src/Repository';
        if (!is_dir($entityDir)) {
            mkdir($entityDir, 0777, true);
        }
        if (!is_dir($repositoryDir)) {
            mkdir($repositoryDir, 0777, true);
        }

        $entitiesByTable = [];
        foreach ($tables as $table) {
            $tableName = $table->getName();
            if (isset($joinTables[$tableName])) {
                continue;
            }

            $className = $this->toClassName($tableName);
            $entitiesByTable[$tableName] = [
                'table' => $table,
                'className' => $className,
                'repositoryClassName' => $className . 'Repository',
                'fields' => [],
                'toOneRelations' => [],
                'inverseOneToMany' => [],
                'inverseOneToOne' => [],
                'manyToManyOwning' => [],
                'manyToManyInverse' => [],
                'usedPropertyNames' => [],
            ];
        }

        $warnings = [];
        $this->collectFieldsAndToOneRelations($entitiesByTable, $warnings);
        $this->collectInverseRelations($entitiesByTable);
        $this->collectManyToManyRelations($joinTables, $entitiesByTable, $warnings);

        $entityFiles = [];
        $repositoryFiles = [];
        foreach ($entitiesByTable as $entityMeta) {
            $entityClassName = $entityMeta['className'];
            $repositoryClassName = $entityMeta['repositoryClassName'];

            $entityCode = $this->renderEntity($entityMeta, $withMethods);
            $repositoryCode = $this->renderRepository($entityClassName, $repositoryClassName);

            $entityPath = $entityDir . '/' . $entityClassName . '.php';
            $repositoryPath = $repositoryDir . '/' . $repositoryClassName . '.php';

            file_put_contents($entityPath, $entityCode);
            file_put_contents($repositoryPath, $repositoryCode);

            $entityFiles[] = $entityClassName;
            $repositoryFiles[] = $repositoryClassName;
        }

        return [
            'entities' => $entityFiles,
            'repositories' => $repositoryFiles,
            'warnings' => $warnings,
            'skipped_join_tables' => array_keys($joinTables),
        ];
    }

    /**
     * @param list<Table> $tables
     *
     * @return array<string, Table>
     */
    private function detectJoinTables(array $tables): array
    {
        $joinTables = [];

        foreach ($tables as $table) {
            $foreignKeys = $table->getForeignKeys();
            if (count($foreignKeys) !== 2) {
                continue;
            }

            if (!$this->isPureJoinTable($table, $foreignKeys)) {
                continue;
            }

            $joinTables[$table->getName()] = $table;
        }

        return $joinTables;
    }

    /**
     * @param list<ForeignKeyConstraint> $foreignKeys
     */
    private function isPureJoinTable(Table $table, array $foreignKeys): bool
    {
        $allColumns = array_keys($table->getColumns());
        $fkColumns = [];

        foreach ($foreignKeys as $foreignKey) {
            if (count($foreignKey->getLocalColumns()) !== 1 || count($foreignKey->getForeignColumns()) !== 1) {
                return false;
            }
            $fkColumns[] = $foreignKey->getLocalColumns()[0];
        }

        sort($allColumns);
        sort($fkColumns);
        if ($allColumns !== $fkColumns) {
            return false;
        }

        $primaryColumns = $table->getPrimaryKey()?->getColumns() ?? [];
        sort($primaryColumns);

        return $primaryColumns === $fkColumns;
    }

    /**
     * @param array<string, array<string, mixed>> $entitiesByTable
     * @param list<string>                        $warnings
     */
    private function collectFieldsAndToOneRelations(array &$entitiesByTable, array &$warnings): void
    {
        foreach ($entitiesByTable as $tableName => &$entityMeta) {
            /** @var Table $table */
            $table = $entityMeta['table'];
            $foreignKeysByLocalColumn = $this->foreignKeysByLocalColumn($table);
            $primaryColumns = $table->getPrimaryKey()?->getColumns() ?? [];

            foreach ($table->getColumns() as $column) {
                $columnName = $column->getName();

                if (isset($foreignKeysByLocalColumn[$columnName])) {
                    $fk = $foreignKeysByLocalColumn[$columnName];
                    $targetTable = $fk->getForeignTableName();

                    if (!isset($entitiesByTable[$targetTable])) {
                        $warnings[] = sprintf(
                            'Skipped foreign key %s.%s -> %s because target table is not mapped.',
                            $tableName,
                            $columnName,
                            $targetTable
                        );
                        continue;
                    }

                    $relationName = $this->allocatePropertyName(
                        $entityMeta['usedPropertyNames'],
                        $this->relationPropertyFromColumn($columnName, $targetTable)
                    );
                    $targetClass = $entitiesByTable[$targetTable]['className'];
                    $isOneToOne = $this->isSingleColumnUnique($table, $columnName);

                    $entityMeta['toOneRelations'][] = [
                        'type' => $isOneToOne ? 'oneToOne' : 'manyToOne',
                        'propertyName' => $relationName,
                        'targetClass' => $targetClass,
                        'targetTable' => $targetTable,
                        'joinColumnName' => $columnName,
                        'referencedColumn' => $fk->getForeignColumns()[0],
                        'nullable' => !$column->getNotnull(),
                    ];

                    continue;
                }

                $fieldName = $this->allocatePropertyName(
                    $entityMeta['usedPropertyNames'],
                    $this->toPropertyName($columnName)
                );
                $phpType = $this->mapPhpType($column);
                $isPrimaryKey = in_array($columnName, $primaryColumns, true);

                $entityMeta['fields'][] = [
                    'propertyName' => $fieldName,
                    'columnName' => $columnName,
                    'dbalType' => $this->mapDbalTypeName($column),
                    'phpType' => $phpType,
                    'length' => $column->getLength(),
                    'precision' => $column->getPrecision(),
                    'scale' => $column->getScale(),
                    'nullable' => !$column->getNotnull(),
                    'autoincrement' => $column->getAutoincrement(),
                    'primary' => $isPrimaryKey,
                ];
            }
        }
        unset($entityMeta);
    }

    /**
     * @param array<string, array<string, mixed>> $entitiesByTable
     */
    private function collectInverseRelations(array &$entitiesByTable): void
    {
        foreach ($entitiesByTable as $sourceTable => $sourceMeta) {
            foreach ($sourceMeta['toOneRelations'] as $toOneRelation) {
                $targetTable = $toOneRelation['targetTable'];
                if (!isset($entitiesByTable[$targetTable])) {
                    continue;
                }

                $targetNameBase = $this->toPropertyName($sourceTable);

                if ($toOneRelation['type'] === 'oneToOne') {
                    $inverseName = $this->allocatePropertyName(
                        $entitiesByTable[$targetTable]['usedPropertyNames'],
                        $targetNameBase
                    );

                    $entitiesByTable[$targetTable]['inverseOneToOne'][] = [
                        'propertyName' => $inverseName,
                        'targetClass' => $sourceMeta['className'],
                        'mappedBy' => $toOneRelation['propertyName'],
                    ];
                } else {
                    $inverseName = $this->allocatePropertyName(
                        $entitiesByTable[$targetTable]['usedPropertyNames'],
                        $this->pluralize($targetNameBase)
                    );

                    $entitiesByTable[$targetTable]['inverseOneToMany'][] = [
                        'propertyName' => $inverseName,
                        'targetClass' => $sourceMeta['className'],
                        'mappedBy' => $toOneRelation['propertyName'],
                        'owningSetter' => 'set' . ucfirst($toOneRelation['propertyName']),
                    ];
                }
            }
        }
    }

    /**
     * @param array<string, Table>                $joinTables
     * @param array<string, array<string, mixed>> $entitiesByTable
     * @param list<string>                        $warnings
     */
    private function collectManyToManyRelations(array $joinTables, array &$entitiesByTable, array &$warnings): void
    {
        foreach ($joinTables as $joinTableName => $joinTable) {
            $foreignKeys = array_values($joinTable->getForeignKeys());
            if (count($foreignKeys) !== 2) {
                continue;
            }

            $leftFk = $foreignKeys[0];
            $rightFk = $foreignKeys[1];
            $leftTable = $leftFk->getForeignTableName();
            $rightTable = $rightFk->getForeignTableName();

            if (!isset($entitiesByTable[$leftTable]) || !isset($entitiesByTable[$rightTable])) {
                $warnings[] = sprintf(
                    'Skipped join table %s because one side is not a mapped entity (%s, %s).',
                    $joinTableName,
                    $leftTable,
                    $rightTable
                );
                continue;
            }

            $owningProperty = $this->allocatePropertyName(
                $entitiesByTable[$leftTable]['usedPropertyNames'],
                $this->pluralize($this->toPropertyName($rightTable))
            );
            $inverseProperty = $this->allocatePropertyName(
                $entitiesByTable[$rightTable]['usedPropertyNames'],
                $this->pluralize($this->toPropertyName($leftTable))
            );

            $entitiesByTable[$leftTable]['manyToManyOwning'][] = [
                'propertyName' => $owningProperty,
                'targetClass' => $entitiesByTable[$rightTable]['className'],
                'inversedBy' => $inverseProperty,
                'joinTableName' => $joinTableName,
                'joinColumnName' => $leftFk->getLocalColumns()[0],
                'joinReferencedColumn' => $leftFk->getForeignColumns()[0],
                'inverseJoinColumnName' => $rightFk->getLocalColumns()[0],
                'inverseJoinReferencedColumn' => $rightFk->getForeignColumns()[0],
            ];

            $entitiesByTable[$rightTable]['manyToManyInverse'][] = [
                'propertyName' => $inverseProperty,
                'targetClass' => $entitiesByTable[$leftTable]['className'],
                'mappedBy' => $owningProperty,
            ];
        }
    }

    /**
     * @param array<string, mixed> $entityMeta
     */
    private function renderEntity(array $entityMeta, bool $withMethods): string
    {
        $className = $entityMeta['className'];
        $repositoryClassName = $entityMeta['repositoryClassName'];
        /** @var Table $table */
        $table = $entityMeta['table'];

        $uses = [
            "use App\\Repository\\{$repositoryClassName};",
            'use Doctrine\ORM\Mapping as ORM;',
        ];

        $hasCollections = $entityMeta['inverseOneToMany'] !== []
            || $entityMeta['manyToManyOwning'] !== []
            || $entityMeta['manyToManyInverse'] !== [];
        if ($hasCollections) {
            $uses[] = 'use Doctrine\Common\Collections\ArrayCollection;';
            $uses[] = 'use Doctrine\Common\Collections\Collection;';
        }

        $propertyBlocks = [];
        $methodBlocks = [];
        $constructorLines = [];

        foreach ($entityMeta['fields'] as $field) {
            $propertyBlocks[] = $this->renderFieldProperty($field);
            if ($withMethods) {
                $methodBlocks[] = $this->renderFieldMethods($field);
            }
        }

        foreach ($entityMeta['toOneRelations'] as $relation) {
            $propertyBlocks[] = $this->renderToOneProperty($relation);
            if ($withMethods) {
                $methodBlocks[] = $this->renderToOneMethods($relation);
            }
        }

        foreach ($entityMeta['inverseOneToOne'] as $relation) {
            $propertyBlocks[] = implode("\n", [
                "    #[ORM\\OneToOne(mappedBy: '{$relation['mappedBy']}', targetEntity: {$relation['targetClass']}::class)]",
                "    private ?{$relation['targetClass']} \${$relation['propertyName']} = null;",
            ]);
            if ($withMethods) {
                $methodBlocks[] = implode("\n", [
                    "    public function get" . ucfirst($relation['propertyName']) . "(): ?{$relation['targetClass']}",
                    '    {',
                    "        return \$this->{$relation['propertyName']};",
                    '    }',
                    '',
                    "    public function set" . ucfirst($relation['propertyName']) . "(?{$relation['targetClass']} \${$relation['propertyName']}): static",
                    '    {',
                    "        \$this->{$relation['propertyName']} = \${$relation['propertyName']};",
                    '',
                    '        return $this;',
                    '    }',
                ]);
            }
        }

        foreach ($entityMeta['inverseOneToMany'] as $relation) {
            $constructorLines[] = "        \$this->{$relation['propertyName']} = new ArrayCollection();";
            $propertyBlocks[] = implode("\n", [
                "    #[ORM\\OneToMany(mappedBy: '{$relation['mappedBy']}', targetEntity: {$relation['targetClass']}::class)]",
                "    private Collection \${$relation['propertyName']};",
            ]);
            if ($withMethods) {
                $methodBlocks[] = $this->renderCollectionMethods(
                    $relation['propertyName'],
                    $relation['targetClass'],
                    $relation['owningSetter']
                );
            }
        }

        foreach ($entityMeta['manyToManyOwning'] as $relation) {
            $constructorLines[] = "        \$this->{$relation['propertyName']} = new ArrayCollection();";
            $propertyBlocks[] = implode("\n", [
                "    #[ORM\\ManyToMany(targetEntity: {$relation['targetClass']}::class, inversedBy: '{$relation['inversedBy']}')]",
                "    #[ORM\\JoinTable(name: '{$relation['joinTableName']}')]",
                "    #[ORM\\JoinColumn(name: '{$relation['joinColumnName']}', referencedColumnName: '{$relation['joinReferencedColumn']}')]",
                "    #[ORM\\InverseJoinColumn(name: '{$relation['inverseJoinColumnName']}', referencedColumnName: '{$relation['inverseJoinReferencedColumn']}')]",
                "    private Collection \${$relation['propertyName']};",
            ]);
            if ($withMethods) {
                $methodBlocks[] = $this->renderManyToManyMethods(
                    $relation['propertyName'],
                    $relation['targetClass'],
                    'add' . ucfirst($relation['inversedBy']),
                    'remove' . ucfirst($relation['inversedBy'])
                );
            }
        }

        foreach ($entityMeta['manyToManyInverse'] as $relation) {
            $constructorLines[] = "        \$this->{$relation['propertyName']} = new ArrayCollection();";
            $propertyBlocks[] = implode("\n", [
                "    #[ORM\\ManyToMany(targetEntity: {$relation['targetClass']}::class, mappedBy: '{$relation['mappedBy']}')]",
                "    private Collection \${$relation['propertyName']};",
            ]);
            if ($withMethods) {
                $methodBlocks[] = $this->renderManyToManyMethods(
                    $relation['propertyName'],
                    $relation['targetClass'],
                    'add' . ucfirst($relation['mappedBy']),
                    'remove' . ucfirst($relation['mappedBy'])
                );
            }
        }

        $constructorBlock = '';
        if ($withMethods && $constructorLines !== []) {
            $constructorBlock = implode("\n", array_merge([
                '    public function __construct()',
                '    {',
            ], $constructorLines, [
                '    }',
                '',
            ]));
        }

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace App\Entity;',
            '',
            ...$uses,
            '',
            sprintf("#[ORM\\Entity(repositoryClass: %s::class)]", $repositoryClassName),
            sprintf("#[ORM\\Table(name: '%s')]", $table->getName()),
            "class {$className}",
            '{',
            $propertyBlocks !== [] ? implode("\n\n", $propertyBlocks) : '',
            '',
            $constructorBlock,
            $withMethods && $methodBlocks !== [] ? implode("\n\n", $methodBlocks) : '',
            '}',
            '',
        ]);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderFieldProperty(array $field): string
    {
        $attributeParts = [
            "name: '{$field['columnName']}'",
            "type: '{$field['dbalType']}'",
        ];

        if ($field['length'] !== null) {
            $attributeParts[] = 'length: ' . $field['length'];
        }
        if ($field['precision'] > 0) {
            $attributeParts[] = 'precision: ' . $field['precision'];
        }
        if ($field['scale'] > 0) {
            $attributeParts[] = 'scale: ' . $field['scale'];
        }
        if ($field['nullable'] === true) {
            $attributeParts[] = 'nullable: true';
        }

        $lines = [];
        if ($field['primary'] === true) {
            $lines[] = '    #[ORM\Id]';
            if ($field['autoincrement'] === true) {
                $lines[] = '    #[ORM\GeneratedValue]';
            }
        }

        $lines[] = '    #[ORM\Column(' . implode(', ', $attributeParts) . ')]';

        $phpType = $field['phpType'];
        $propertyType = $field['nullable'] ? '?' . $phpType : $phpType;
        $default = $field['nullable'] || $field['autoincrement'] ? ' = null' : '';
        $lines[] = "    private {$propertyType} \${$field['propertyName']}{$default};";

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderFieldMethods(array $field): string
    {
        $property = $field['propertyName'];
        $methodSuffix = ucfirst($property);
        $phpType = $field['phpType'];
        $methodType = $field['nullable'] || $field['autoincrement'] ? '?' . $phpType : $phpType;

        $getter = implode("\n", [
            "    public function get{$methodSuffix}(): {$methodType}",
            '    {',
            "        return \$this->{$property};",
            '    }',
        ]);

        if ($field['primary'] === true && $field['autoincrement'] === true) {
            return $getter;
        }

        $setter = implode("\n", [
            "    public function set{$methodSuffix}({$methodType} \${$property}): static",
            '    {',
            "        \$this->{$property} = \${$property};",
            '',
            '        return $this;',
            '    }',
        ]);

        return $getter . "\n\n" . $setter;
    }

    /**
     * @param array<string, mixed> $relation
     */
    private function renderToOneProperty(array $relation): string
    {
        $relationType = $relation['type'] === 'oneToOne' ? 'OneToOne' : 'ManyToOne';
        $nullable = $relation['nullable'] ? 'true' : 'false';
        $propertyType = '?' . $relation['targetClass'];

        return implode("\n", [
            "    #[ORM\\{$relationType}(targetEntity: {$relation['targetClass']}::class)]",
            "    #[ORM\\JoinColumn(name: '{$relation['joinColumnName']}', referencedColumnName: '{$relation['referencedColumn']}', nullable: {$nullable})]",
            "    private {$propertyType} \${$relation['propertyName']} = null;",
        ]);
    }

    /**
     * @param array<string, mixed> $relation
     */
    private function renderToOneMethods(array $relation): string
    {
        $property = $relation['propertyName'];
        $targetClass = $relation['targetClass'];
        $methodSuffix = ucfirst($property);
        $setterType = $relation['nullable'] ? '?' . $targetClass : $targetClass;

        return implode("\n", [
            "    public function get{$methodSuffix}(): ?{$targetClass}",
            '    {',
            "        return \$this->{$property};",
            '    }',
            '',
            "    public function set{$methodSuffix}({$setterType} \${$property}): static",
            '    {',
            "        \$this->{$property} = \${$property};",
            '',
            '        return $this;',
            '    }',
        ]);
    }

    private function renderCollectionMethods(string $propertyName, string $targetClass, string $owningSetter): string
    {
        $singular = $this->singularize($propertyName);
        $methodSuffix = ucfirst($propertyName);
        $itemSuffix = ucfirst($singular);

        return implode("\n", [
            "    /**",
            "     * @return Collection<int, {$targetClass}>",
            '     */',
            "    public function get{$methodSuffix}(): Collection",
            '    {',
            "        return \$this->{$propertyName};",
            '    }',
            '',
            "    public function add{$itemSuffix}({$targetClass} \${$singular}): static",
            '    {',
            "        if (!\$this->{$propertyName}->contains(\${$singular})) {",
            "            \$this->{$propertyName}->add(\${$singular});",
            "            \${$singular}->{$owningSetter}(\$this);",
            '        }',
            '',
            '        return $this;',
            '    }',
            '',
            "    public function remove{$itemSuffix}({$targetClass} \${$singular}): static",
            '    {',
            "        if (\$this->{$propertyName}->removeElement(\${$singular})) {",
            "            \${$singular}->{$owningSetter}(null);",
            '        }',
            '',
            '        return $this;',
            '    }',
        ]);
    }

    private function renderManyToManyMethods(string $propertyName, string $targetClass, string $inverseAdd, string $inverseRemove): string
    {
        $singular = $this->singularize($propertyName);
        $methodSuffix = ucfirst($propertyName);
        $itemSuffix = ucfirst($singular);

        return implode("\n", [
            "    /**",
            "     * @return Collection<int, {$targetClass}>",
            '     */',
            "    public function get{$methodSuffix}(): Collection",
            '    {',
            "        return \$this->{$propertyName};",
            '    }',
            '',
            "    public function add{$itemSuffix}({$targetClass} \${$singular}): static",
            '    {',
            "        if (!\$this->{$propertyName}->contains(\${$singular})) {",
            "            \$this->{$propertyName}->add(\${$singular});",
            "            \${$singular}->{$inverseAdd}(\$this);",
            '        }',
            '',
            '        return $this;',
            '    }',
            '',
            "    public function remove{$itemSuffix}({$targetClass} \${$singular}): static",
            '    {',
            "        if (\$this->{$propertyName}->removeElement(\${$singular})) {",
            "            \${$singular}->{$inverseRemove}(\$this);",
            '        }',
            '',
            '        return $this;',
            '    }',
        ]);
    }

    private function renderRepository(string $className, string $repositoryClassName): string
    {
        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace App\Repository;',
            '',
            "use App\Entity\\{$className};",
            'use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;',
            'use Doctrine\Persistence\ManagerRegistry;',
            '',
            "/**",
            " * @extends ServiceEntityRepository<{$className}>",
            ' */',
            "class {$repositoryClassName} extends ServiceEntityRepository",
            '{',
            '    public function __construct(ManagerRegistry $registry)',
            '    {',
            "        parent::__construct(\$registry, {$className}::class);",
            '    }',
            '}',
            '',
        ]);
    }

    /**
     * @return array<string, ForeignKeyConstraint>
     */
    private function foreignKeysByLocalColumn(Table $table): array
    {
        $indexed = [];
        foreach ($table->getForeignKeys() as $foreignKey) {
            if (count($foreignKey->getLocalColumns()) !== 1 || count($foreignKey->getForeignColumns()) !== 1) {
                continue;
            }
            $indexed[$foreignKey->getLocalColumns()[0]] = $foreignKey;
        }

        return $indexed;
    }

    private function isSingleColumnUnique(Table $table, string $columnName): bool
    {
        $pkColumns = $table->getPrimaryKey()?->getColumns() ?? [];
        if ($pkColumns === [$columnName]) {
            return true;
        }

        foreach ($table->getIndexes() as $index) {
            if (!$index->isUnique()) {
                continue;
            }

            if ($index->getColumns() === [$columnName]) {
                return true;
            }
        }

        return false;
    }

    private function toClassName(string $tableName): string
    {
        $parts = preg_split('/[_\W]+/', strtolower($tableName), -1, PREG_SPLIT_NO_EMPTY) ?: [$tableName];
        return implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));
    }

    private function toPropertyName(string $name): string
    {
        $parts = preg_split('/[_\W]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [$name];
        $first = array_shift($parts) ?? 'field';
        $property = $first . implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));

        if (!preg_match('/^[a-zA-Z_]/', $property)) {
            $property = 'field' . ucfirst($property);
        }

        $reserved = [
            'class', 'trait', 'function', 'public', 'private', 'protected',
            'namespace', 'extends', 'implements', 'new', 'clone', 'default',
            'switch', 'case', 'match', 'if', 'else', 'elseif', 'while', 'for',
            'foreach', 'do', 'try', 'catch', 'finally', 'throw', 'return',
        ];
        if (in_array(strtolower($property), $reserved, true)) {
            $property .= 'Field';
        }

        return $property;
    }

    private function relationPropertyFromColumn(string $columnName, string $targetTable): string
    {
        if (str_ends_with(strtolower($columnName), '_id')) {
            return $this->toPropertyName(substr($columnName, 0, -3));
        }

        return $this->toPropertyName($targetTable);
    }

    private function pluralize(string $name): string
    {
        if (str_ends_with($name, 'y')) {
            return substr($name, 0, -1) . 'ies';
        }
        if (str_ends_with($name, 's')) {
            return $name . 'es';
        }

        return $name . 's';
    }

    private function singularize(string $name): string
    {
        if (str_ends_with($name, 'ies')) {
            return substr($name, 0, -3) . 'y';
        }
        if (str_ends_with($name, 'ses')) {
            return substr($name, 0, -2);
        }
        if (str_ends_with($name, 's') && strlen($name) > 1) {
            return substr($name, 0, -1);
        }

        return $name;
    }

    /**
     * @param array<string, true> $usedNames
     */
    private function allocatePropertyName(array &$usedNames, string $requestedName): string
    {
        $name = $requestedName;
        $counter = 2;
        while (isset($usedNames[$name])) {
            $name = $requestedName . $counter;
            ++$counter;
        }

        $usedNames[$name] = true;

        return $name;
    }

    private function mapPhpType(Column $column): string
    {
        return match ($this->mapDbalTypeName($column)) {
            'smallint', 'integer', 'bigint' => 'int',
            'float' => 'float',
            'boolean' => 'bool',
            'json' => 'array',
            'date', 'datetime', 'datetimetz', 'date_immutable', 'datetime_immutable', 'time', 'time_immutable' => '\DateTimeInterface',
            'decimal' => 'string',
            default => 'string',
        };
    }

    private function mapDbalTypeName(Column $column): string
    {
        return match ($column->getType()::class) {
            'Doctrine\DBAL\Types\SmallIntType' => 'smallint',
            'Doctrine\DBAL\Types\IntegerType' => 'integer',
            'Doctrine\DBAL\Types\BigIntType' => 'bigint',
            'Doctrine\DBAL\Types\FloatType' => 'float',
            'Doctrine\DBAL\Types\BooleanType' => 'boolean',
            'Doctrine\DBAL\Types\JsonType' => 'json',
            'Doctrine\DBAL\Types\DateType', 'Doctrine\DBAL\Types\DateImmutableType' => 'date',
            'Doctrine\DBAL\Types\DateTimeType' => 'datetime',
            'Doctrine\DBAL\Types\DateTimeImmutableType' => 'datetime_immutable',
            'Doctrine\DBAL\Types\DateTimeTzType' => 'datetimetz',
            'Doctrine\DBAL\Types\DateTimeTzImmutableType' => 'datetimetz_immutable',
            'Doctrine\DBAL\Types\TimeType' => 'time',
            'Doctrine\DBAL\Types\TimeImmutableType' => 'time_immutable',
            'Doctrine\DBAL\Types\DecimalType' => 'decimal',
            'Doctrine\DBAL\Types\TextType' => 'text',
            'Doctrine\DBAL\Types\GuidType' => 'guid',
            'Doctrine\DBAL\Types\BinaryType' => 'binary',
            'Doctrine\DBAL\Types\BlobType' => 'blob',
            default => 'string',
        };
    }
}
