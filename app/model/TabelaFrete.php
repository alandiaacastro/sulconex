<?php

class TabelaFrete extends TRecord
{
    const TABLENAME  = 'tabela_fretes';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';
    const TIPOS      = ['NAC', 'INTL'];
    const COMBO_MASK = '{origem} -> {destino}';
    const COMBO_MASK_WITH_TYPE = '{tipo_veiculo} | {origem} -> {destino}';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('origem');
        parent::addAttribute('fronteira');
        parent::addAttribute('destino');
        parent::addAttribute('tipo_veiculo');
        parent::addAttribute('tipo');
        parent::addAttribute('valor_frete');
        parent::addAttribute('atualizacao');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public static function parseMoney($value): float
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return 0.0;
        }

        if (strpos($raw, ',') !== false) {
            $raw = str_replace('.', '', $raw);
        }

        $raw = str_replace(',', '.', $raw);

        return (float) $raw;
    }

    public static function normalizeUpper($value): string
    {
        return trim(mb_strtoupper((string) $value, 'UTF-8'));
    }

    public static function formatAtualizacao($value): string
    {
        if (empty($value)) {
            return '';
        }

        return (new DateTime($value))->format('d/m/Y H:i');
    }

    public static function loadCidadeOptions(string $database = 'default'): array
    {
        $cidades = [];

        TTransaction::open($database);

        try {
            $lista = (new TRepository('CidadeUf'))->load(null, false);

            foreach ($lista ?? [] as $cidade) {
                $label = "{$cidade->nome},{$cidade->uf}";
                $cidades[$label] = $label;
            }

            TTransaction::close();

            return $cidades;
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }

    public static function loadTipoVeiculoOptions(string $database = 'sample'): array
    {
        $tipos = [];

        TTransaction::open($database);

        try {
            $stmt = TTransaction::get()->query(
                "SELECT DISTINCT tipo_veiculo
                   FROM tabela_fretes
                  WHERE COALESCE(tipo_veiculo, '') <> ''
               ORDER BY tipo_veiculo"
            );

            foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                $tipo = trim((string) ($row['tipo_veiculo'] ?? ''));
                if ($tipo !== '') {
                    $tipos[$tipo] = $tipo;
                }
            }

            TTransaction::close();

            return $tipos;
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }

    public static function findExistingRouteId(PDO $connection, string $origem, string $destino, string $tipoVeiculo): ?int
    {
        $stmt = $connection->prepare(
            'SELECT id FROM tabela_fretes WHERE origem = :origem AND destino = :destino AND tipo_veiculo = :tipo_veiculo LIMIT 1'
        );
        $stmt->execute([
            ':origem'       => $origem,
            ':destino'      => $destino,
            ':tipo_veiculo' => $tipoVeiculo,
        ]);

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    public static function findLatestRouteIdByData(
        PDO $connection,
        string $origem,
        string $destino,
        ?string $tipoVeiculo = null,
        ?float $valorFrete = null
    ): ?int {
        $sql = "SELECT id
                  FROM tabela_fretes
                 WHERE origem = :origem
                   AND destino = :destino";

        $params = [
            ':origem'  => self::normalizeUpper($origem),
            ':destino' => self::normalizeUpper($destino),
        ];

        if (!empty($tipoVeiculo)) {
            $sql .= ' AND tipo_veiculo = :tipo_veiculo';
            $params[':tipo_veiculo'] = self::normalizeUpper($tipoVeiculo);
        }

        if ($valorFrete !== null && $valorFrete > 0) {
            $sql .= ' AND ABS(COALESCE(valor_frete, 0) - :valor_frete) < 0.01';
            $params[':valor_frete'] = $valorFrete;
        }

        $sql .= ' ORDER BY COALESCE(updated_at, atualizacao, created_at) DESC, id DESC LIMIT 1';

        $stmt = $connection->prepare($sql);
        $stmt->execute($params);

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }
}
