<?php

use Adianti\Database\TRecord;

class Veiculo extends TRecord
{
    const TABLENAME  = 'veiculo';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    private $antt_consulta_trator;
    private $antt_consulta_semi_reboque;
    private $motorista;
    private $proprietario;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('antt_consulta_trator_id');
        parent::addAttribute('antt_consulta_semi_reboque_id');
        parent::addAttribute('motorista_id');
        parent::addAttribute('ano_fabricacao');
        parent::addAttribute('modelo');
        parent::addAttribute('placa_trator');
    }
    
    public function get_proprietario()
    {
        if (empty($this->proprietario) && !empty($this->antt_consulta_trator_id)) {
            $this->proprietario = new AnttConsulta($this->antt_consulta_trator_id);
        }
        return $this->proprietario;
    }

    public function get_antt_consulta_trator()
    {
        if (empty($this->antt_consulta_trator) && !empty($this->antt_consulta_trator_id)) {
            $this->antt_consulta_trator = new AnttConsulta($this->antt_consulta_trator_id);
        }
        return $this->antt_consulta_trator;
    }
    
    public function get_antt_consulta_semi_reboque()
    {
        if (empty($this->antt_consulta_semi_reboque) && !empty($this->antt_consulta_semi_reboque_id)) {
            $this->antt_consulta_semi_reboque = new AnttConsulta($this->antt_consulta_semi_reboque_id);
        }
        return $this->antt_consulta_semi_reboque;
    }

    public function get_motorista()
    {
        if (empty($this->motorista) && !empty($this->motorista_id)) {
            $this->motorista = new Motorista($this->motorista_id);
        }
        return $this->motorista;
    }
}