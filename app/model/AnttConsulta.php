<?php

use Adianti\Database\TRecord;

/**
 * ActiveRecord para a tabela `antt_consulta`, seguindo padrao TRecord
 */
class AnttConsulta extends TRecord
{
    const TABLENAME  = 'antt_consulta';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial'; // 'serial' ou 'max'

    /**
     * Constructor method
     *
     * @param mixed $id
     * @param boolean $callObjectLoad
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        // Campos definidos na tabela
        parent::addAttribute('placa');
        parent::addAttribute('tipo');
        parent::addAttribute('marca');
        parent::addAttribute('carroceria');
        parent::addAttribute('eixos');
        parent::addAttribute('chassi_motor');
        parent::addAttribute('ano');
        parent::addAttribute('ccu');
        parent::addAttribute('cnpj');
        parent::addAttribute('razao_social');
        parent::addAttribute('nome_fantasia');
        parent::addAttribute('endereco');
        parent::addAttribute('bairro');
        parent::addAttribute('cidade');
        parent::addAttribute('pais_origem');
        parent::addAttribute('situacao_licencas');
        parent::addAttribute('data_consulta');
    }
}
/**
 * Você pode adicionar métodos personalizados aqui, se necessário.
 * Por exemplo, métodos para regras de negócio ou manipulação de dados.
 */
