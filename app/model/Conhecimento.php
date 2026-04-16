<?php
use Adianti\Database\TRecord;

class Conhecimento extends TRecord
{
    use SystemChangeLogTrait;

    const TABLENAME  = 'conhecimento';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial'; // SQLite com AUTOINCREMENT

    // Lazy loading
    private $permisso_obj;
    private $remetente_obj;
    private $destinatario_obj;
    private $consignatario_obj;
    private $notificar_obj;
    private $pagador_obj;
    private $status_crt_obj;

    public static function ensureSchema(): void
    {
        $openedHere = false;

        if (!TTransaction::get()) {
            TTransaction::open('sample');
            $openedHere = true;
        }

        try {
            $conn = TTransaction::get();
            $columns = $conn->query("PRAGMA table_info(conhecimento)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $missingColumns = [
                'tipo_cobranca'  => "ALTER TABLE conhecimento ADD COLUMN tipo_cobranca TEXT DEFAULT 'FRETE_FIXO'",
                'toneladas_carga'=> "ALTER TABLE conhecimento ADD COLUMN toneladas_carga REAL DEFAULT 0",
                'valor_por_ton'  => "ALTER TABLE conhecimento ADD COLUMN valor_por_ton REAL DEFAULT 0",
            ];

            foreach ($missingColumns as $column => $sql) {
                if (!in_array($column, $columns, true)) {
                    $conn->exec($sql);
                }
            }

            if ($openedHere) {
                TTransaction::close();
            }
        } catch (Exception $e) {
            if ($openedHere) {
                TTransaction::rollback();
            }
            throw $e;
        }
    }

    public function get_permisso()
    {
        if (empty($this->permisso_obj) && $this->permisso_id) {
            $this->permisso_obj = new Permisso($this->permisso_id);
        }
        return $this->permisso_obj;
    }

    public function get_remetente()
    {
        if (empty($this->remetente_obj) && $this->remetente_id) {
            $this->remetente_obj = new Clientes($this->remetente_id);
        }
        return $this->remetente_obj;
    }

    public function get_destinatario()
    {
        if (empty($this->destinatario_obj) && $this->destinatario_id) {
            $this->destinatario_obj = new Clientes($this->destinatario_id);
        }
        return $this->destinatario_obj;
    }

    public function get_consignatario()
    {
        if (empty($this->consignatario_obj) && $this->consignatario_id) {
            $this->consignatario_obj = new Clientes($this->consignatario_id);
        }
        return $this->consignatario_obj;
    }

    public function get_notificar()
    {
        if (empty($this->notificar_obj) && $this->notificar_id) {
            $this->notificar_obj = new Clientes($this->notificar_id);
        }
        return $this->notificar_obj;
    }

    public function get_pagador()
    {
        if (empty($this->pagador_obj) && $this->pagador_id) {
            $this->pagador_obj = new Clientes($this->pagador_id);
        }
        return $this->pagador_obj;
    }

    public function get_status_crt()
    {
        if (empty($this->status_crt_obj) && $this->status_crt_id) {
            $this->status_crt_obj = new StatusCrt($this->status_crt_id);
        }
        return $this->status_crt_obj;
    }

    public function loadNames()
    {
        if ($this->remetente_id && empty($this->nome_remetente)) {
            $this->nome_remetente = $this->get_remetente()->nome;
        }
        if ($this->destinatario_id && empty($this->nome_destinatario)) {
            $this->nome_destinatario = $this->get_destinatario()->nome;
        }
        if ($this->consignatario_id && empty($this->nome_consignatario)) {
            $this->nome_consignatario = $this->get_consignatario()->nome;
        }
        if ($this->pagador_id && empty($this->nome_pagador)) {
            $this->nome_pagador = $this->get_pagador()->nome;
        }
    }

    public static function convertKgToToneladas($pesoKg): float
    {
        $peso = is_numeric($pesoKg) ? (float) $pesoKg : 0.0;
        return $peso > 0 ? $peso / 1000 : 0.0;
    }

    public function getToneladasCalculadas(): float
    {
        return self::convertKgToToneladas($this->peso_bruto_kg ?? 0);
    }

    public function __get($property)
    {
        if ($property == 'numero_crt') {
            return $this->numero;
        }
        if ($property == 'toneladas_carga_calculada') {
            return $this->getToneladasCalculadas();
        }
        return parent::__get($property);
    }
}
