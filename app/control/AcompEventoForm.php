<?php

class AcompEventoForm extends TPage
{
    private $form;

    public function __construct($param = null)
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_acomp_evento');
        $this->form->setFormTitle('Cadastro de Status do Processo');

        $id = new TEntry('id');
        $processo_id = new THidden('processo_id');
        $data_evento = new TEntry('data_evento');
        $demora = new TEntry('demora');
        $status_texto = new TCombo('status_texto');
        $franquia = new TCombo('franquia');

        $id->setEditable(false);
        $id->setSize('100%');
        $data_evento->setSize('100%');
        $demora->setSize('100%');
        $status_texto->setSize('100%');
        $status_texto->setEditable(false);
        $status_texto->setDefaultOption('Selecione');
        $franquia->setSize('100%');
        $franquia->setEditable(false);
        $franquia->setDefaultOption('Selecione');

        $franquia->addItems([
            'LIVRE' => 'LIVRE',
            '24 HS' => '24 HS',
            '48 HS' => '48 HS',
            'ESTADIA' => 'ESTADIA',
        ]);
        $status_texto->addItems([
            'COLETA' => 'COLETA',
            'TRANSITO BRASIL' => 'TRANSITO BRASIL',
            'ADUANA BRASIL' => 'ADUANA BRASIL',
            'ARMAZENAGEM' => 'ARMAZENAGEM',
            'TRANSITO ARGENTINA' => 'TRANSITO ARGENTINA',
            'ADUANA ARGENTINA' => 'ADUANA ARGENTINA',
            'TRANSITO CHILE' => 'TRANSITO CHILE',
            'ADUANA CHILE' => 'ADUANA CHILE',
            'ENTREGA' => 'ENTREGA',
        ]);

        $data_evento->setValue(date('d/m/Y H:i'));

        $data_evento->addValidation('Data/Hora', new TRequiredValidator);
        $status_texto->addValidation('Status', new TRequiredValidator);

        $this->form->addFields([$processo_id]);
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Data e hora (dd/mm/aaaa hh:mm)')], [$data_evento]);
        $this->form->addFields([new TLabel('Status')], [$status_texto], [new TLabel('Franquia')], [$franquia]);
        $this->form->addFields([new TLabel('Evento')], [$demora]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink('Voltar aos status', new TAction(['AcompEventoList', 'onReload'], ['processo_id' => $param['processo_id'] ?? '']), 'fa:arrow-left blue');

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', 'AcompProcessoKanban'));
        $box->add($this->form);

        parent::add($box);

        if (!empty($param['processo_id'])) {
            $obj = new stdClass;
            $obj->processo_id = $param['processo_id'];
            $this->form->setData($obj);
        }
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $this->form->validate();
            $obj = $this->form->getData('AcompEvento');

            if (empty($obj->processo_id)) {
                throw new Exception('Processo nao informado.');
            }

            $obj->data_evento = self::toDbDateTime((string) $obj->data_evento);
            $obj->store();

            $this->form->setData($obj);
            TTransaction::close();

            new TMessage('info', 'Status salvo com sucesso.', new TAction(['AcompEventoList', 'onReload'], ['processo_id' => $obj->processo_id]));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onEdit($param)
    {
        try {
            if (empty($param['key'])) {
                return;
            }

            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $obj = new AcompEvento($param['key']);
            $obj->data_evento = self::toViewDateTime((string) $obj->data_evento);
            $this->form->setData($obj);
            if (!empty($obj->status_texto)) {
                $field = $this->form->getField('status_texto');
                if ($field instanceof TCombo) {
                    $field->addItems([$obj->status_texto => $obj->status_texto]);
                }
            }

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private static function toDbDateTime(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $m[3], $m[2], $m[1], $m[4], $m[5]);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $value)) {
            return strlen($value) === 16 ? $value . ':00' : $value;
        }

        throw new Exception('Formato de data/hora invalido. Use dd/mm/aaaa hh:mm');
    }

    private static function toViewDateTime(string $value): string
    {
        $ts = strtotime($value);
        return $ts ? date('d/m/Y H:i', $ts) : $value;
    }
}
