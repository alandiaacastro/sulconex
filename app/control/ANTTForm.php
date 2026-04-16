<?php

class ANTTForm extends TPage
{
    private $form;
    private static $panel;
    private $vbox;

    public function __construct()
    {
        parent::__construct();

        // Criação do formulário
        $this->form = new BootstrapFormBuilder('form_antt');
        $this->form->setFormTitle('Consulta de Placa - ANTT');
        $this->form->setFieldSizes('100%');

        // Campo de entrada da placa
        $placa = new TEntry('placa');
        $placa->setMaxLength(8);
        $placa->forceUpperCase();
        $placa->setProperty('placeholder', 'Digite a placa');
        $this->form->addFields([new TLabel('Placa')], [$placa]);

        // Botão Consultar
        $btnConsultar = new TButton('btnConsultar');
        $btnConsultar->setAction(new TAction([$this, 'onConsultar']), 'Consultar');
        $btnConsultar->setImage('fa:search blue');

        // Botão Salvar (inicialmente escondido)
        $btnSalvar = new TButton('btnSalvar');
        $btnSalvar->setAction(new TAction([$this, 'onSalvar']), 'Salvar');
        $btnSalvar->setImage('fa:save green');
        $btnSalvar->setProperty('style', 'display:none');

        // Caixa horizontal para botões
        $buttonBox = new THBox;
        $buttonBox->add($btnConsultar);
        $buttonBox->add($btnSalvar);

        $this->form->addFields([$buttonBox]);

        // Registrar botões e campos no formulário
        $this->form->addField($btnConsultar);
        $this->form->addField($btnSalvar);
        $this->form->setFields([$placa, $btnConsultar, $btnSalvar]);

        // Painel de exibição dos dadosa
        self::$panel = new TPanelGroup('Resultado da Consulta');
        self::$panel->setProperty('id', 'panel_conteudo');
        self::$panel->add(new TLabel('Informe uma placa e clique em Consultar.'));

        // Layout principal
        $this->vbox = new TVBox;
        $this->vbox->style = 'width:100%';
        $this->vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $this->vbox->add($this->form);
        $this->vbox->add(self::$panel);

        parent::add($this->vbox);
    }

    public static function onConsultar($param)
    {
        try {
            $placa = strtoupper(trim($param['placa'] ?? ''));
            if (empty($placa) || strlen($placa) < 7) {
                self::showPanelMessage('Placa inválida ou incompleta', 'danger');
                return;
            }

            require_once __DIR__ . '/ANTTService.php';
            $resposta = ANTTService::onConsulta(['placa' => $placa]);

            if (empty($resposta['success'])) {
                self::showPanelMessage($resposta['mensagem'] ?? 'Erro na consulta', 'danger');
                return;
            }

            $d = $resposta['dados'];

            if (empty($d['placa']) || $d['placa'] !== $placa) {
                self::showPanelMessage("Placa <b>{$placa}</b> não encontrada na base ANTT", 'danger');
                return;
            }

            $html  = '<div class="alert alert-success">Dados obtidos com sucesso.</div>';
            $html .= '<table class="table table-bordered table-striped">';
            foreach ($d as $c => $v) {
                $lbl = ucwords(str_replace('_', ' ', $c));
                $html .= "<tr><th width='30%'>{$lbl}</th><td>{$v}</td></tr>";
            }
            $html .= '</table>';

            TSession::setValue('antt_resultado', $d);
            $json = json_encode($html);
            TScript::create("document.getElementById('panel_conteudo').querySelector('.panel-body').innerHTML = {$json};");

            // Exibe o botão Salvar
            TScript::create("document.querySelector('[name=btnSalvar]').style.display = 'inline-block';");

        } catch (Exception $e) {
            self::showPanelMessage($e->getMessage(), 'danger');
        }
    }

    public static function onSalvar($param)
    {
        try {
            $d = TSession::getValue('antt_resultado');
            if (!$d) {
                self::showPanelMessage('Nenhum dado de consulta disponível para salvar', 'danger');
                return;
            }

            TTransaction::open('sample');

            $placa = strtoupper(trim($d['placa']));
            $criteria = new TCriteria;
            $criteria->add(new TFilter('placa', '=', $placa));
            $repo = new TRepository('AnttConsulta');

            if ($repo->count($criteria) > 0) {
                TTransaction::close();
                self::showPanelMessage("A placa <b>{$placa}</b> já existe no histórico. Nenhum registro foi inserido.caso queira incluir alguma alteracão dele o registro anterior", 'warning');
                return;
            }

            $r = new AnttConsulta();
            $r->placa              = $placa;
            $r->tipo               = $d['tipo'] ?? null;
            $r->marca              = $d['marca'] ?? null;
            $r->carroceria         = $d['carroceria'] ?? null;
            $r->eixos              = $d['eixos'] ?? null;
            $r->chassi_motor       = $d['chassi_motor'] ?? null;
            $r->ano                = $d['ano'] ?? null;
            $r->ccu                = $d['ccu'] ?? null;
            $r->cnpj               = $d['cnpj'] ?? null;
            $r->razao_social       = $d['razao_social'] ?? null;
            $r->nome_fantasia      = $d['nome_fantasia'] ?? null;
            $r->endereco           = $d['endereco'] ?? null;
            $r->bairro             = $d['bairro'] ?? null;
            $r->cidade             = $d['cidade'] ?? null;
            $r->pais_origem        = $d['pais_origem'] ?? null;
            $r->situacao_licencas  = $d['situacao_licencas'] ?? null;
            $r->data_consulta      = date('Y-m-d H:i:s');

            $r->store();
            TTransaction::close();

            self::showPanelMessage("Registro salvo com sucesso! ID: <b>{$r->id}</b>", 'success');

        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $e2) {
            }
            self::showPanelMessage($e->getMessage(), 'danger');
        }
    }

    public static function showPanelMessage($message, $type = 'info')
    {
        $color = in_array($type, ['info', 'success', 'warning', 'danger']) ? $type : 'info';
        $html = "<div class='alert alert-{$color}'>{$message}</div>";
        $json = json_encode($html);
        TScript::create("document.getElementById('panel_conteudo').querySelector('.panel-body').innerHTML = {$json};");
    }
}

