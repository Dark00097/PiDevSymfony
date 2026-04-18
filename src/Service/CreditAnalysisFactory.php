<?php

namespace App\Service;

use App\Entity\Credit;
use App\Entity\Garantiecredit;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class CreditAnalysisFactory
{
    /**
     * @param array<string, mixed> $creditData
     * @param array<int, array<string, mixed>> $garantieRows
     */
    public function createFromArray(array $creditData, array $garantieRows = []): Credit
    {
        $credit = new Credit();

        $this->writeProperty($credit, 'idcredit', $this->nullableInt($creditData['idCredit'] ?? null));
        $this->writeProperty($credit, 'typecredit', $this->stringValue($creditData['typeCredit'] ?? $creditData['typecredit'] ?? 'Consommation'));
        $this->writeProperty($credit, 'montantdemande', $this->floatValue($creditData['montantDemande'] ?? $creditData['montantdemande'] ?? 0));
        $this->writeProperty($credit, 'autofinancement', $this->nullableFloat($creditData['autofinancement'] ?? null));
        $this->writeProperty($credit, 'duree', max(0, $this->intValue($creditData['duree'] ?? 0)));
        $this->writeProperty($credit, 'tauxinteret', $this->floatValue($creditData['tauxInteret'] ?? $creditData['tauxinteret'] ?? 0));
        $this->writeProperty($credit, 'mensualite', $this->floatValue($creditData['mensualite'] ?? 0));
        $this->writeProperty($credit, 'montantaccorde', $this->floatValue($creditData['montantAccorde'] ?? $creditData['montantaccorde'] ?? $creditData['montantDemande'] ?? 0));
        $this->writeProperty($credit, 'datedemande', $this->stringValue($creditData['dateDemande'] ?? $creditData['datedemande'] ?? date('Y-m-d')));
        $this->writeProperty($credit, 'iduser', $this->nullableInt($creditData['idUser'] ?? $creditData['iduser'] ?? null));
        $this->writeProperty($credit, 'salaire', $this->nullableFloat($creditData['salaire'] ?? null));
        $this->writeProperty($credit, 'typecontrat', $this->stringValue($creditData['typeContrat'] ?? $creditData['typecontrat'] ?? 'Autre'));
        $this->writeProperty($credit, 'ancienneteannees', max(0, $this->intValue($creditData['ancienneteAnnees'] ?? $creditData['ancienneteannees'] ?? 0)));

        $garanties = [];
        foreach ($garantieRows as $garantieRow) {
            $garanties[] = $this->createGarantieFromArray($garantieRow, $credit);
        }

        $this->writeProperty($credit, 'garantiecredits', new ArrayCollection($garanties));

        return $credit;
    }

    /**
     * @param array<string, mixed> $creditRow
     * @param array<int, array<string, mixed>> $allGaranties
     */
    public function createFromDatabaseRow(array $creditRow, array $allGaranties = []): Credit
    {
        $creditId = $this->intValue($creditRow['idCredit'] ?? 0);
        $linkedGaranties = array_values(array_filter(
            $allGaranties,
            static fn (array $garantie): bool => (int) ($garantie['idCredit'] ?? 0) === $creditId
        ));

        return $this->createFromArray($creditRow, $linkedGaranties);
    }

    public function getFloat(Credit $credit, string ...$propertyNames): float
    {
        return $this->floatValue($this->readValue($credit, ...$propertyNames));
    }

    public function getNullableFloat(Credit $credit, string ...$propertyNames): ?float
    {
        return $this->nullableFloat($this->readValue($credit, ...$propertyNames));
    }

    public function getInt(Credit $credit, string ...$propertyNames): int
    {
        return $this->intValue($this->readValue($credit, ...$propertyNames));
    }

    public function getString(Credit $credit, string ...$propertyNames): string
    {
        return $this->stringValue($this->readValue($credit, ...$propertyNames));
    }

    /**
     * @return array<int, mixed>
     */
    public function getGuarantees(Credit $credit): array
    {
        $garanties = $this->readValue($credit, 'garantiecredits');
        if ($garanties instanceof Collection) {
            return $garanties->toArray();
        }

        return is_array($garanties) ? $garanties : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportForForm(Credit $credit): array
    {
        return [
            'typeCredit' => $this->getString($credit, 'typecredit'),
            'montantDemande' => $this->getFloat($credit, 'montantdemande'),
            'autofinancement' => $this->getNullableFloat($credit, 'autofinancement'),
            'duree' => $this->getInt($credit, 'duree'),
            'tauxInteret' => $this->getFloat($credit, 'tauxinteret'),
            'mensualite' => $this->getFloat($credit, 'mensualite'),
            'montantAccorde' => $this->getFloat($credit, 'montantaccorde'),
            'dateDemande' => $this->getString($credit, 'datedemande'),
            'salaire' => $this->getNullableFloat($credit, 'salaire'),
            'typeContrat' => $this->getString($credit, 'typecontrat'),
            'ancienneteAnnees' => $this->getInt($credit, 'ancienneteannees'),
        ];
    }

    /**
     * @param array<string, mixed> $garantieData
     */
    private function createGarantieFromArray(array $garantieData, Credit $credit): Garantiecredit
    {
        $garantie = new Garantiecredit();

        $estimated = $this->floatValue($garantieData['valeurEstimee'] ?? $garantieData['valeurestimee'] ?? 0);
        $retained = $this->floatValue($garantieData['valeurRetenue'] ?? $garantieData['valeurretenue'] ?? $estimated);

        $this->writeProperty($garantie, 'idgarantie', $this->nullableInt($garantieData['idGarantie'] ?? $garantieData['idgarantie'] ?? null));
        $this->writeProperty($garantie, 'typegarantie', $this->stringValue($garantieData['typeGarantie'] ?? $garantieData['typegarantie'] ?? 'Garantie libre'));
        $this->writeProperty($garantie, 'description', $this->nullableString($garantieData['description'] ?? null));
        $this->writeProperty($garantie, 'adressebien', $this->nullableString($garantieData['adresseBien'] ?? $garantieData['adressebien'] ?? null));
        $this->writeProperty($garantie, 'valeurestimee', $estimated);
        $this->writeProperty($garantie, 'valeurretenue', $retained);
        $this->writeProperty($garantie, 'documentjustificatif', $this->nullableString($garantieData['documentJustificatif'] ?? $garantieData['documentjustificatif'] ?? null));
        $this->writeProperty($garantie, 'dateevaluation', $this->stringValue($garantieData['dateEvaluation'] ?? $garantieData['dateevaluation'] ?? date('Y-m-d')));
        $this->writeProperty($garantie, 'nomgarant', $this->nullableString($garantieData['nomGarant'] ?? $garantieData['nomgarant'] ?? null));
        $this->writeProperty($garantie, 'statut', $this->stringValue($garantieData['statut'] ?? 'Active'));
        $this->writeProperty($garantie, 'iduser', $this->intValue($garantieData['idUser'] ?? $garantieData['iduser'] ?? 0));
        $this->writeProperty($garantie, 'credit', $credit);

        return $garantie;
    }

    private function readValue(object $object, string ...$propertyNames): mixed
    {
        $reflection = new \ReflectionObject($object);

        foreach ($propertyNames as $propertyName) {
            $getter = 'get'.ucfirst($propertyName);
            if (method_exists($object, $getter)) {
                return $object->{$getter}();
            }

            if (method_exists($object, $propertyName)) {
                return $object->{$propertyName}();
            }

            if (!$reflection->hasProperty($propertyName)) {
                continue;
            }

            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            if (!$property->isInitialized($object)) {
                continue;
            }

            return $property->getValue($object);
        }

        return null;
    }

    private function writeProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionObject($object);
        if (!$reflection->hasProperty($propertyName)) {
            return;
        }

        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->floatValue($value);
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->intValue($value);
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = $this->stringValue($value);

        return $normalized === '' ? null : $normalized;
    }
}
