<?php

class PortalMotoristaDocumento extends TRecord
{
    const TABLENAME  = 'portal_motorista_documento';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    const TIPO_CNH              = 'cnh';
    const TIPO_CAVALO           = 'documento_cavalo';
    const TIPO_SEMI_REBOQUE     = 'documento_semi_reboque';
    const TIPO_PORTA_MOTORISTA  = 'foto_porta_motorista';
    const TIPO_VARANDA_VEICULO  = 'foto_varanda_veiculo';
    const TIPO_LONAS            = 'foto_lonas';

    private static $schemaChecked = false;

    public function __construct($id = null, $callObjectLoad = true)
    {
        self::ensureSchemaIfPossible();
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('motorista_id');
        parent::addAttribute('veiculo_id');
        parent::addAttribute('tipo_documento');
        parent::addAttribute('titulo');
        parent::addAttribute('arquivo');
        parent::addAttribute('arquivo_original');
        parent::addAttribute('mime_type');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public static function getSlotDefinitions(): array
    {
        return [
            self::TIPO_CNH => [
                'slot' => 'cnh',
                'title' => 'CNH',
                'hint' => 'Envie a foto ou PDF da sua habilitacao.',
                'requires_vehicle' => false,
                'allow_pdf' => true,
                'accept' => '.jpg,.jpeg,.png,.webp,.gif,.bmp,.svg,.pdf',
                'capture' => null,
                'kind' => 'document',
            ],
            self::TIPO_CAVALO => [
                'slot' => 'cavalo',
                'title' => 'Documento do Cavalo',
                'hint' => 'Envie CRLV ou documento equivalente do cavalo.',
                'requires_vehicle' => true,
                'allow_pdf' => true,
                'accept' => '.jpg,.jpeg,.png,.webp,.gif,.bmp,.svg,.pdf',
                'capture' => null,
                'kind' => 'document',
            ],
            self::TIPO_SEMI_REBOQUE => [
                'slot' => 'semi',
                'title' => 'Documento do Semi-Reboque',
                'hint' => 'Envie CRLV ou documento equivalente do semi-reboque.',
                'requires_vehicle' => true,
                'allow_pdf' => true,
                'accept' => '.jpg,.jpeg,.png,.webp,.gif,.bmp,.svg,.pdf',
                'capture' => null,
                'kind' => 'document',
            ],
            self::TIPO_PORTA_MOTORISTA => [
                'slot' => 'porta_motorista',
                'title' => 'Porta do Motorista',
                'hint' => 'Tire uma foto da porta do motorista com a identificacao do cadastro.',
                'requires_vehicle' => true,
                'allow_pdf' => false,
                'accept' => 'image/*',
                'capture' => 'environment',
                'kind' => 'photo',
            ],
            self::TIPO_VARANDA_VEICULO => [
                'slot' => 'varanda_veiculo',
                'title' => 'Varanda do Veiculo',
                'hint' => 'Tire uma foto da varanda do veiculo.',
                'requires_vehicle' => true,
                'allow_pdf' => false,
                'accept' => 'image/*',
                'capture' => 'environment',
                'kind' => 'photo',
            ],
            self::TIPO_LONAS => [
                'slot' => 'lonas',
                'title' => 'Lonas',
                'hint' => 'Tire uma foto das lonas do veiculo.',
                'requires_vehicle' => true,
                'allow_pdf' => false,
                'accept' => 'image/*',
                'capture' => 'environment',
                'kind' => 'photo',
            ],
        ];
    }

    public static function getTipoLabels(): array
    {
        $labels = [];

        foreach (self::getSlotDefinitions() as $type => $definition) {
            $labels[$type] = (string) ($definition['title'] ?? 'Documento');
        }

        return $labels;
    }

    public static function getTypeDefinition(string $tipoDocumento): ?array
    {
        $definitions = self::getSlotDefinitions();
        return $definitions[$tipoDocumento] ?? null;
    }

    public static function isVehicleBoundType(string $tipoDocumento): bool
    {
        $definition = self::getTypeDefinition($tipoDocumento);
        return !empty($definition['requires_vehicle']);
    }

    public static function allowsPdf(string $tipoDocumento): bool
    {
        $definition = self::getTypeDefinition($tipoDocumento);
        return !empty($definition['allow_pdf']);
    }

    public function onBeforeStore($object)
    {
        $now = date('Y-m-d H:i:s');
        if (empty($object->created_at)) {
            $object->created_at = $now;
        }
        $object->updated_at = $now;
    }

    public static function ensureTables(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        $conn = TTransaction::get();

        $conn->exec("CREATE TABLE IF NOT EXISTS portal_motorista_documento (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            motorista_id INTEGER NOT NULL,
            veiculo_id INTEGER,
            tipo_documento TEXT NOT NULL,
            titulo TEXT,
            arquivo TEXT NOT NULL,
            arquivo_original TEXT,
            mime_type TEXT,
            created_at TEXT,
            updated_at TEXT
        )");

        $conn->exec("CREATE INDEX IF NOT EXISTS idx_portal_motorista_doc_motorista ON portal_motorista_documento(motorista_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_portal_motorista_doc_tipo ON portal_motorista_documento(tipo_documento)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_portal_motorista_doc_veiculo ON portal_motorista_documento(veiculo_id)");

        self::$schemaChecked = true;
    }

    public static function findByContext(int $motoristaId, string $tipoDocumento, ?int $veiculoId = null): ?self
    {
        $repo = new TRepository(__CLASS__);
        $criteria = new TCriteria;
        $criteria->add(new TFilter('motorista_id', '=', $motoristaId));
        $criteria->add(new TFilter('tipo_documento', '=', $tipoDocumento));
        $criteria->add(new TFilter('veiculo_id', $veiculoId ? '=' : 'IS', $veiculoId));
        $criteria->setProperty('limit', 1);

        $items = $repo->load($criteria, false);
        return $items ? $items[0] : null;
    }

    public static function ensureStorageDirectory(): string
    {
        $dir = 'tmp/portal_motorista_documentos';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function ensureSchemaIfPossible(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        try {
            if (TTransaction::get()) {
                self::ensureTables();
            }
        } catch (Throwable $e) {
        }
    }
}
