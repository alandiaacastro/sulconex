<?php

class AcompProcessoForm extends TPage
{
    private $form;

    public function __construct($param = null)
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_acomp_processo');
        $this->form->setFormTitle('Cadastro - Acompanhamento de Processo');

        $id = new TEntry('id');
        $numero_processo = new TEntry('numero_processo');
        $local_coleta = new TText('local_coleta');
        $local_entrega = new TText('local_entrega');
        $data_coleta = new TDate('data_coleta');
        $previsao_entrega = new TDate('previsao_entrega');
        $transit_time_dias = new TEntry('transit_time_dias');
        $aduana_origem = new TEntry('aduana_origem');
        $aduana_destino = new TEntry('aduana_destino');

        $exportador = new TEntry('exportador');
        $importador = new TEntry('importador');
        $produto = new TText('produto');

        $crt = new TEntry('crt');
        $fatura = new TEntry('fatura');
        $peso_bruto = new TNumeric('peso_bruto', 2, ',', '.');

        $id->setEditable(false);
        $id->setSize('100%');
        $numero_processo->setSize('100%');
        $local_coleta->setSize('100%', 60);
        $local_entrega->setSize('100%', 60);
        $data_coleta->setMask('dd/mm/yyyy');
        $data_coleta->setDatabaseMask('yyyy-mm-dd');
        $previsao_entrega->setMask('dd/mm/yyyy');
        $previsao_entrega->setDatabaseMask('yyyy-mm-dd');
        $transit_time_dias->setSize('100%');
        $aduana_origem->setSize('100%');
        $aduana_destino->setSize('100%');

        $exportador->setSize('100%');
        $importador->setSize('100%');
        $produto->setSize('100%', 60);

        $crt->setSize('100%');
        $fatura->setSize('100%');
        $peso_bruto->setSize('100%');

        $numero_processo->addValidation('Numero do processo', new TRequiredValidator);
        $exportador->addValidation('Exportador', new TRequiredValidator);
        $importador->addValidation('Importador', new TRequiredValidator);
        $produto->addValidation('Produto', new TRequiredValidator);

        $this->form->addFields([new TLabel('ID')], [$id], [new TLabel('No BR/AR')], [$numero_processo]);
        $this->form->addFields([new TLabel('Local de coleta')], [$local_coleta]);
        $this->form->addFields([new TLabel('Local de entrega')], [$local_entrega]);
        $this->form->addFields([new TLabel('Data coleta')], [$data_coleta], [new TLabel('Previsao entrega')], [$previsao_entrega]);
        $this->form->addFields([new TLabel('Transit time (dias)')], [$transit_time_dias], [new TLabel('Aduana de origem')], [$aduana_origem]);

        $this->form->addFields([new TLabel('Exportador')], [$exportador], [new TLabel('Importador')], [$importador]);
        $this->form->addFields([new TLabel('Produto')], [$produto]);

        $this->form->addFields([new TLabel('CRT')], [$crt], [new TLabel('Fatura')], [$fatura]);
        $this->form->addFields([new TLabel('Peso bruto (kg)')], [$peso_bruto], [new TLabel('Aduana de destino')], [$aduana_destino]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink('Listagem', new TAction(['AcompProcessoKanban', 'onReload']), 'fa:list blue');

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', 'AcompProcessoKanban'));
        $box->add($this->form);

        parent::add($box);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $this->form->validate();
            $obj = $this->form->getData('AcompProcesso');
            $obj->store();

            $this->form->setData($obj);
            TTransaction::close();

            new TMessage('info', 'Processo salvo com sucesso.');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onEdit($param)
    {
        try {
            if (empty($param['key'])) {
                $this->form->clear(true);
                return;
            }

            TTransaction::open('sample');
            AcompProcesso::ensureTables();
            $obj = new AcompProcesso($param['key']);
            $this->form->setData($obj);
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }
}
