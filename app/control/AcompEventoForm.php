<?php

class AcompEventoForm extends TPage
{
    private $form;

    public function __construct($param = null)
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_acomp_evento');
        $this->form->setFormTitle('Cadastro de Etapa do Processo');

        $id = new TEntry('id');
        $processo_id = new THidden('processo_id');
        $data_evento = new TEntry('data_evento');
        $demora = new TEntry('demora');
        $localizacao = new TEntry('localizacao');
        $status_texto = new TCombo('status_texto');
        $franquia = new TCombo('franquia');
        $imagem = new TFile('imagem');

        $id->setEditable(false);
        $id->setSize('100%');
        $data_evento->setSize('100%');
        $demora->setSize('100%');
        $localizacao->setSize('100%');
        $status_texto->setSize('100%');
        $status_texto->setDefaultOption('Selecione');
        $status_texto->setEditable(false);
        $franquia->setSize('100%');
        $franquia->setDefaultOption('Selecione');
        $imagem->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $imagem->setSize('100%');

        $franquia->addItems([
            'LIVRE' => 'LIVRE',
            '24 HS' => '24 HS',
            '48 HS' => '48 HS',
            'ESTADIA' => 'ESTADIA',
        ]);

        $status_texto->addItems(self::getEtapaItems());

        $data_evento->setValue(date('d/m/Y H:i'));

        $data_evento->addValidation('Data/Hora', new TRequiredValidator);
        $localizacao->addValidation('Localizacao (cidade)', new TRequiredValidator);

        $this->form->addFields([$processo_id]);
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Data e hora (dd/mm/aaaa hh:mm)')], [$data_evento]);
        $this->form->addFields([new TLabel('Etapa')], [$status_texto], [new TLabel('Franquia')], [$franquia]);
        $this->form->addFields([new TLabel('Localizacao (cidade)')], [$localizacao]);
        $this->form->addFields([new TLabel('Evento')], [$demora]);
        $this->form->addFields([new TLabel('Imagem (foto/comprovante)')], [$imagem]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink('Voltar as etapas', new TAction(['AcompEventoList', 'onReload'], ['processo_id' => $param['processo_id'] ?? '']), 'fa:arrow-left blue');

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', 'AcompProcessoKanban'));
        $box->add($this->form);

        parent::add($box);

        if (!empty($param['processo_id'])) {
            $obj = new stdClass;
            $obj->processo_id = $param['processo_id'];
            try {
                TTransaction::open('sample');
                AcompProcesso::ensureTables();
                $proc = new AcompProcesso((int) $param['processo_id']);
                $obj->status_texto = self::stageCodeToOption((string) ($proc->etapa ?? ''));
                TTransaction::close();
            } catch (Exception $e) {
                try { TTransaction::rollback(); } catch (Exception $ee) {}
            }
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

            // Etapa sempre vem do Kanban (processo), sem edicao manual neste formulario
            $proc = new AcompProcesso((int) $obj->processo_id);
            $etapaSelecionada = self::stageCodeToOption((string) ($proc->etapa ?? ''));
            if ($etapaSelecionada === '') {
                throw new Exception('Etapa do processo nao definida no Kanban.');
            }
            $obj->status_texto = $etapaSelecionada;

            $obj->data_evento = self::toDbDateTime((string) $obj->data_evento);

            $imgFile = $this->form->getField('imagem')->getValue();
            if ($imgFile) {
                $source = 'tmp/' . $imgFile;
                $targetDir = 'app/images/acomp_evento/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                if (file_exists($source)) {
                    rename($source, $targetDir . $imgFile);
                    $obj->imagem = $imgFile;
                }
            } elseif (!empty($obj->id)) {
                $existing = new AcompEvento($obj->id);
                $obj->imagem = $existing->imagem;
            }

            $obj->store();

            // Mantem Kanban sincronizado com a etapa selecionada no evento
            $stageCode = AcompProcesso::normalizeStageCode((string) $obj->status_texto);
            if ($stageCode !== '') {
                $proc = new AcompProcesso($obj->processo_id);
                $proc->etapa = $stageCode;
                $proc->updated_at = date('Y-m-d H:i:s');
                $proc->store();
            }

            $this->form->setData($obj);
            TTransaction::close();

            new TMessage('info', 'Etapa salva com sucesso.', new TAction(['AcompEventoList', 'onReload'], ['processo_id' => $obj->processo_id]));
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
            if (empty($obj->status_texto) && !empty($obj->processo_id)) {
                $proc = new AcompProcesso((int) $obj->processo_id);
                $obj->status_texto = self::stageCodeToOption((string) ($proc->etapa ?? ''));
            }
            $obj->data_evento = self::toViewDateTime((string) $obj->data_evento);
            $this->form->setData($obj);

            if (!empty($obj->imagem) && file_exists('app/images/acomp_evento/' . $obj->imagem)) {
                $preview = new TImage('app/images/acomp_evento/' . $obj->imagem);
                $preview->style = 'max-width:300px; max-height:200px; margin:8px 0; display:block; border-radius:4px;';
                $this->form->addContent([$preview]);
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

    private static function getEtapaItems(): array
    {
        return [
            'COLETA' => 'COLETA',
            'TRANSITO BRASIL' => 'TRANSITO BRASIL',
            'ARMAZENAGEM' => 'ARMAZENAGEM',
            'ADUANA BRASIL' => 'ADUANA BRASIL',
            'TRANSITO EXT' => 'TRANSITO EXT',
            'ADUANA DESTINO' => 'ADUANA DESTINO',
            'ENTREGA' => 'ENTREGA',
        ];
    }

    private static function stageCodeToOption(string $code): string
    {
        $stage = AcompProcesso::normalizeStageCode($code);
        $map = [
            AcompProcesso::STAGE_COLETA => 'COLETA',
            AcompProcesso::STAGE_TRANSITO_BRASIL => 'TRANSITO BRASIL',
            AcompProcesso::STAGE_ARMAZENAGEM => 'ARMAZENAGEM',
            AcompProcesso::STAGE_ADUANA_BRASIL => 'ADUANA BRASIL',
            AcompProcesso::STAGE_TRANSITO_EXT => 'TRANSITO EXT',
            AcompProcesso::STAGE_ADUANA_DESTINO => 'ADUANA DESTINO',
            AcompProcesso::STAGE_ENTREGA => 'ENTREGA',
        ];

        return $map[$stage] ?? '';
    }
}
