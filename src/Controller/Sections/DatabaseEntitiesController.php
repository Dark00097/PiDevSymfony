<?php

namespace App\Controller\Sections;

use Doctrine\Persistence\ManagerRegistry;

final class DatabaseEntitiesController
{
    private const PREVIEW_LIMIT = 12;

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function buildAdminData(): array
    {
        $manager = $this->managerRegistry->getManager();
        $connection = $manager->getConnection();
        $metadataList = $manager->getMetadataFactory()->getAllMetadata();

        $entities = [];
        $totalRows = 0;

        foreach ($metadataList as $metadata) {
            $className = $metadata->getName();
            if (!str_starts_with($className, 'App\\Entity\\')) {
                continue;
            }

            $shortName = $metadata->getReflectionClass()?->getShortName() ?? $className;
            $tableName = $metadata->getTableName();
            $identifierColumns = $metadata->getIdentifierColumnNames();

            // Sécurité : nom de table validé (issu des métadonnées Doctrine, jamais d'une entrée utilisateur)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                continue;
            }

            // Utilisation de SQL natif avec nom de table validé (pas de QueryBuilder pour éviter faux positifs)
            $totalCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM `' . $tableName . '`');
            $totalRows += $totalCount;

            $orderBy = '';
            if (!empty($identifierColumns)) {
                $cols = array_map(fn(string $c) => '`' . preg_replace('/[^a-zA-Z0-9_]/', '', $c) . '` DESC', $identifierColumns);
                $orderBy = ' ORDER BY ' . implode(', ', $cols);
            }

            $rows = $connection->fetchAllAssociative(
                'SELECT * FROM `' . $tableName . '`' . $orderBy . ' LIMIT ' . self::PREVIEW_LIMIT
            );
            $columns = $this->collectColumns($rows, $metadata->getColumnNames());

            $entities[] = [
                'entity_name' => $shortName,
                'table_name' => $tableName,
                'identifier_columns' => $identifierColumns,
                'total_count' => $totalCount,
                'preview_count' => count($rows),
                'columns' => $columns,
                'rows' => $rows,
            ];
        }

        usort($entities, static fn (array $left, array $right): int => strcmp($left['entity_name'], $right['entity_name']));

        return [
            'items' => $entities,
            'support' => [
                'entity_datasets' => $entities,
                'entity_preview_limit' => self::PREVIEW_LIMIT,
                'entity_total_tables' => count($entities),
                'entity_total_rows' => $totalRows,
            ],
        ];
    }

    private function collectColumns(array $rows, array $fallbackColumns): array
    {
        $columns = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        if ($columns !== []) {
            return $columns;
        }

        return array_values(array_unique($fallbackColumns));
    }
}
