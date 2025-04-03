<?php

class PermissoxSeek extends TStandardSeek
{
    public function __construct()
    {
        parent::__construct();

        parent::setDatabase('sample');           // nome da conexão
        parent::setActiveRecord('Permissox');    // classe do modelo (TRecord)
        parent::addFilterField('id', '=', 'id');
        parent::addFilterField('permisso', 'like', 'permisso');
        parent::addFilterField('pais_destino', 'like', 'pais_destino');

        $this->setTitle('Buscar Permissões');

        $this->addColumn('id',           'ID',            'center', 70);
        $this->addColumn('permisso',     'Permisso',      'left',  100);
        $this->addColumn('pais_destino', 'País Destino',  'left',  120);
        $this->addColumn('numerocrt',    'Numeração CRT', 'center', 100);

        // Exibe no formulário o valor retornado
        $this->setDisplayMask('{permisso}'); // o que será exibido no campo
        $this->setValueField('permisso');    // o valor que será enviado para o formulário
        $this->setSearchField('permisso');   // campo que o usuário digita no filtro
    }
}
