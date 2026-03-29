<?php

class PortalMotoristaLocationResolver
{
    private const EXACT_MATCH_DISTANCE_KM = 1.0;
    private const NEAR_MATCH_DISTANCE_KM = 25.0;

    public static function describe(?PDO $connection, float $latitude, float $longitude): array
    {
        $coordinatesLabel = self::formatCoordinates($latitude, $longitude);
        $closestReference = self::findClosestReference($connection, $latitude, $longitude);

        $locationLabel = 'Coordenadas capturadas';
        $locationDetail = $coordinatesLabel;
        $referenceLabel = null;
        $referenceDistanceKm = null;

        if ($closestReference) {
            $referenceLabel = (string) $closestReference['label'];
            $referenceDistanceKm = round((float) $closestReference['distance_km'], 1);

            if ($closestReference['distance_km'] <= self::EXACT_MATCH_DISTANCE_KM) {
                $locationLabel = $referenceLabel;
            } elseif ($closestReference['distance_km'] <= self::NEAR_MATCH_DISTANCE_KM) {
                $locationLabel = 'Proximo de ' . $referenceLabel;
            }

            if ($closestReference['distance_km'] <= self::NEAR_MATCH_DISTANCE_KM) {
                $locationDetail = $coordinatesLabel . ' | ' . self::formatDistance((float) $closestReference['distance_km']) . ' da referencia';
            }
        }

        return [
            'localizacao_label' => $locationLabel,
            'localizacao_detalhe' => $locationDetail,
            'coordenadas_label' => $coordinatesLabel,
            'referencia_label' => $referenceLabel,
            'referencia_distancia_km' => $referenceDistanceKm,
        ];
    }

    private static function findClosestReference(?PDO $connection, float $latitude, float $longitude): ?array
    {
        $closest = null;

        foreach (self::loadReferences($connection) as $reference) {
            $distanceKm = self::calculateDistanceKm(
                $latitude,
                $longitude,
                (float) $reference['latitude'],
                (float) $reference['longitude']
            );

            if ($closest === null || $distanceKm < $closest['distance_km']) {
                $closest = [
                    'label' => (string) $reference['label'],
                    'distance_km' => $distanceKm,
                ];
            }
        }

        return $closest;
    }

    private static function loadReferences(?PDO $connection): array
    {
        $references = self::loadStaticReferences();
        $seen = [];

        foreach ($references as $reference) {
            $seen[self::buildReferenceKey($reference)] = true;
        }

        foreach (self::loadDatabaseReferences($connection) as $reference) {
            $key = self::buildReferenceKey($reference);
            if (isset($seen[$key])) {
                continue;
            }

            $references[] = $reference;
            $seen[$key] = true;
        }

        return $references;
    }

    private static function loadDatabaseReferences(?PDO $connection): array
    {
        if (!$connection) {
            return [];
        }

        try {
            $stmt = $connection->query('
                SELECT nome, cidade, estado, pais, latitude, longitude
                FROM localizacao
                WHERE latitude IS NOT NULL
                  AND longitude IS NOT NULL
            ');

            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $references = [];

            foreach ($rows as $row) {
                $label = self::buildDatabaseReferenceLabel($row);
                if ($label === '') {
                    continue;
                }

                $references[] = [
                    'label' => $label,
                    'latitude' => (float) $row['latitude'],
                    'longitude' => (float) $row['longitude'],
                ];
            }

            return $references;
        } catch (Exception $e) {
            return [];
        }
    }

    private static function buildDatabaseReferenceLabel(array $row): string
    {
        $nome = trim((string) ($row['nome'] ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $cidade = trim((string) ($row['cidade'] ?? ''));
        $estado = trim((string) ($row['estado'] ?? ''));
        $pais = trim((string) ($row['pais'] ?? ''));

        $parts = [];
        if ($cidade !== '') {
            $parts[] = $cidade;
        }
        if ($estado !== '') {
            $parts[] = $estado;
        } elseif ($pais !== '') {
            $parts[] = $pais;
        }

        return trim(implode('/', $parts));
    }

    private static function loadStaticReferences(): array
    {
        return [
            ['label' => 'Multilog Uruguaiana/RS', 'latitude' => -29.781797, 'longitude' => -57.070356],
            ['label' => 'Uruguaiana/RS', 'latitude' => -29.759997, 'longitude' => -57.085609],
            ['label' => 'Cotecar Paso de los Libres', 'latitude' => -29.711972, 'longitude' => -57.086012],
            ['label' => 'Paso de los Libres/AR', 'latitude' => -29.712511, 'longitude' => -57.090645],
            ['label' => 'Santo Tome/AR', 'latitude' => -28.549389, 'longitude' => -56.045914],
            ['label' => 'Sao Borja/RS', 'latitude' => -28.661668, 'longitude' => -56.004440],
            ['label' => 'Mendoza/AR', 'latitude' => -32.889458, 'longitude' => -68.845839],
            ['label' => 'Cordoba/AR', 'latitude' => -31.416668, 'longitude' => -64.183334],
            ['label' => 'Los Andes/CL', 'latitude' => -32.833692, 'longitude' => -70.598273],
            ['label' => 'Los Libertadores/CL', 'latitude' => -32.823122, 'longitude' => -70.090314],
            ['label' => 'Aduana Argentina', 'latitude' => -29.712511, 'longitude' => -57.090645],
            ['label' => 'Aduana Brasil', 'latitude' => -29.711972, 'longitude' => -57.086012],
            ['label' => 'Santiago/CL', 'latitude' => -33.448890, 'longitude' => -70.669265],
            ['label' => 'Porto Alegre/RS', 'latitude' => -30.034647, 'longitude' => -51.217658],
        ];
    }

    private static function buildReferenceKey(array $reference): string
    {
        return implode('|', [
            trim((string) ($reference['label'] ?? '')),
            number_format((float) ($reference['latitude'] ?? 0), 6, '.', ''),
            number_format((float) ($reference['longitude'] ?? 0), 6, '.', ''),
        ]);
    }

    private static function formatCoordinates(float $latitude, float $longitude): string
    {
        return number_format($latitude, 6, '.', '') . ', ' . number_format($longitude, 6, '.', '');
    }

    private static function formatDistance(float $distanceKm): string
    {
        return number_format($distanceKm, 1, ',', '.') . ' km';
    }

    private static function calculateDistanceKm(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadiusKm = 6371;
        $latDiff = deg2rad($latitudeB - $latitudeA);
        $lonDiff = deg2rad($longitudeB - $longitudeA);

        $a = sin($latDiff / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($lonDiff / 2) ** 2;

        return 2 * $earthRadiusKm * asin(min(1, sqrt($a)));
    }
}
