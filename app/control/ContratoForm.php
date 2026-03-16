<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TText;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBUniqueSearch;

class ContratoForm extends TPage
{
    protected $form;

    public function __construct($param)
    {
        parent::__construct($param);
        parent::setTargetContainer('adianti_right_panel');

        Contrato::addColumnsIfNotExists();
        AliquotaImposto::createTableIfNotExists();

        $this->form = new BootstrapFormBuilder('form_contrato');
        $this->form->setFormTitle('Cadastro de Contrato');

        $id = new TEntry('id');
        $veiculo_id = new TDBUniqueSearch('veiculo_id', 'sample', 'Veiculo', 'id', 'placa_trator');
        $veiculo_id->setSize('100%');
        $permisso_id = new TDBCombo('permisso_id', 'sample', 'Permisso', 'id', 'transportadora', 'transportadora');
        $conhecimento_numero = new TEntry('conhecimento_numero');
        $danfeoumic = new TEntry('danfeoumic');
        $emissao = new TDate('emissao');
        $origem1 = new TEntry('origem1');
        $destino1 = new TEntry('destino1');
        $frete1 = new TNumeric('frete1', 2, ',', '.', true);
        $adt1 = new TNumeric('adt1', 2, ',', '.', true);
        $inss1 = new TNumeric('inss1', 2, ',', '.', true);
        $irrf1 = new TNumeric('irrf1', 2, ',', '.', true);
        $sest1 = new TNumeric('sest1', 2, ',', '.', true);
        $pis1 = new TNumeric('pis1', 2, ',', '.', true);
        $cofins1 = new TNumeric('cofins1', 2, ',', '.', true);
        $descontos1 = new TNumeric('descontos1', 2, ',', '.', true);
        $saldo1 = new TNumeric('saldo1', 2, ',', '.');
        $pagamento = new TText('pagamento');
        $observacoes = new TText('observacoes');
        $extenso1 = new TEntry('extenso1');
        $vencimento = new TDate('vencimento');
        $dta_efet_pg = new TDate('dta_efet_pg');
        $pago = new TCombo('pago');
        $placa_trator = new TEntry('placa_trator');
        $placa_semi = new TEntry('placa_semi');
        $motorista_nome = new TEntry('motorista_nome');
        $proprietario_nome = new TEntry('proprietario_nome');
        $pis_motorista = new TEntry('pis_motorista');
        $pis_motorista->setSize('100%');

        $placa_trator->setEditable(FALSE);
        $placa_semi->setEditable(FALSE);
        $motorista_nome->setEditable(FALSE);
        $proprietario_nome->setEditable(FALSE);
        $id->setEditable(FALSE);
        $saldo1->setEditable(FALSE);
        $extenso1->setEditable(FALSE);

        $veiculo_id->setMask('Trator: {placa_trator} | Motorista: {motorista->nome}');
        $veiculo_id->setMinLength(1);
        
        $onVeiculoSelect = new TAction(['ContratoForm', 'onVeiculoSelect']);
        $veiculo_id->setChangeAction($onVeiculoSelect);
        
        $emissao->setMask('dd/mm/yyyy'); $emissao->setDatabaseMask('yyyy-mm-dd');
        $vencimento->setMask('dd/mm/yyyy'); $vencimento->setDatabaseMask('yyyy-mm-dd');
        $dta_efet_pg->setMask('dd/mm/yyyy'); $dta_efet_pg->setDatabaseMask('yyyy-mm-dd');
        
        $pago->addItems(['N' => 'Não', 'S' => 'Sim']);

        $update_action = new TAction(['ContratoForm', 'onUpdateValores']);
        $pis_motorista->setExitAction($update_action);
        $frete1->setExitAction($update_action);
        $adt1->setExitAction($update_action);
        $inss1->setExitAction($update_action);
        $irrf1->setExitAction($update_action);
        $sest1->setExitAction($update_action);
        $pis1->setExitAction($update_action);
        $cofins1->setExitAction($update_action);
        $descontos1->setExitAction($update_action);

        $this->form->appendPage('Dados Principais');
        $this->form->addFields( [new TLabel('ID')], [$id] );
        $this->form->addFields( [new TLabel('Contratante (Permissão)', '#FF0000')], [$permisso_id] );
        $this->form->addFields( [new TLabel('Selecione o Veículo', '#FF0000')], [$veiculo_id] );
        $this->form->addFields( [new TLabel('Placa Trator')], [$placa_trator], [new TLabel('Placa Carreta')], [$placa_semi] );
        $this->form->addFields( [new TLabel('Motorista')], [$motorista_nome], [new TLabel('PIS do Motorista')], [$pis_motorista] );
        $this->form->addFields( [new TLabel('Proprietário')], [$proprietario_nome] );
        $this->form->addFields( [new TLabel('Conhecimento')], [$conhecimento_numero], [new TLabel('Danfe/Mic')], [$danfeoumic] );
        $this->form->addFields( [new TLabel('Emissão')], [$emissao] );
        $this->form->addFields( [new TLabel('Origem')], [$origem1] );
        $this->form->addFields( [new TLabel('Destino')], [$destino1] );

        $this->form->appendPage('Valores e Pagamento');
        $this->form->addFields( [new TLabel('Frete')], [$frete1], [new TLabel('Adiantamento')], [$adt1] );
        $this->form->addFields( [new TLabel('INSS (*)')], [$inss1], [new TLabel('IRRF (auto 1,5%)')], [$irrf1] );
        $this->form->addFields( [new TLabel('SEST/SENAT (auto 1,5%)')], [$sest1], [new TLabel('PIS (auto 0,65%)')], [$pis1] );
        $this->form->addFields( [new TLabel('COFINS (auto 3%)')], [$cofins1], [new TLabel('Outros Descontos')], [$descontos1] );
        $this->form->addFields( [new TLabel('Saldo')], [$saldo1] );
        $this->form->addFields( [new TLabel('Valor por Extenso')], [$extenso1] );
        $this->form->addFields( [new TLabel('Forma de Pagamento')], [$pagamento] );
        $this->form->addFields( [new TLabel('Observações')], [$observacoes] );
        
        $this->form->appendPage('Financeiro');
        $this->form->addFields( [new TLabel('Vencimento')], [$vencimento], [new TLabel('Data Pagamento')], [$dta_efet_pg] );
        $this->form->addFields( [new TLabel('Pago')], [$pago] );

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Listagem', new TAction(['ContratoList', 'onReload']), 'fa:table blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }
    
    public static function onVeiculoSelect($param)
    {
        if (!empty($param['veiculo_id'])) {
            try {
                TTransaction::open('sample');
                $veiculo = new Veiculo($param['veiculo_id']);
                $data = new stdClass;
                $data->placa_trator = $veiculo->antt_consulta_trator->placa ?? '';
                $data->placa_semi = $veiculo->antt_consulta_semi_reboque->placa ?? '';
                $data->motorista_nome = $veiculo->motorista->nome ?? '';
                $data->proprietario_nome = $veiculo->proprietario->razao_social ?? '';
                TForm::sendData('form_contrato', $data, false, true);
                TTransaction::close();
            }
            catch (Exception $e) {
                new TMessage('error', $e->getMessage());
                TTransaction::rollback();
            }
        }
    }
    
    private static function parseBrNumber($val): float
    {
        $val = trim((string)($val ?? '0'));
        // formato pt-BR: 1.234,56
        if (strpos($val, ',') !== false) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        }
        return (float) $val;
    }

    public static function onUpdateValores($param)
    {
        $frete          = self::parseBrNumber($param['frete1']     ?? 0);
        $adt            = self::parseBrNumber($param['adt1']       ?? 0);
        $inss           = self::parseBrNumber($param['inss1']      ?? 0);
        $irrf           = self::parseBrNumber($param['irrf1']      ?? 0);
        $sest           = self::parseBrNumber($param['sest1']      ?? 0);
        $pis            = self::parseBrNumber($param['pis1']       ?? 0);
        $cofins         = self::parseBrNumber($param['cofins1']    ?? 0);
        $descontos      = self::parseBrNumber($param['descontos1'] ?? 0);
        $pis_motorista  = trim($param['pis_motorista'] ?? '');

        $obj = new stdClass;

        // Calcula impostos automaticamente apenas se PIS do motorista estiver preenchido
        if ($pis_motorista !== '') {
            // Lê alíquotas configuráveis do banco (fallback para valores padrão)
            try {
                $rates = AliquotaImposto::getAll();
            } catch (Exception $e) {
                $rates = [];
            }
            $r_irrf   = (float) ($rates['IRRF']       ?? 0.015);
            $r_sest   = (float) ($rates['SEST_SENAT']  ?? 0.015);
            $r_pis    = (float) ($rates['PIS']         ?? 0.0065);
            $r_cofins = (float) ($rates['COFINS']      ?? 0.03);

            $obj->irrf1   = round($frete * $r_irrf,   2);
            $obj->sest1   = round($frete * $r_sest,   2);
            $obj->pis1    = round($frete * $r_pis,    2);
            $obj->cofins1 = round($frete * $r_cofins, 2);
            $irrf   = $obj->irrf1;
            $sest   = $obj->sest1;
            $pis    = $obj->pis1;
            $cofins = $obj->cofins1;
        } else {
            // PIS não informado → zera impostos automáticos
            $obj->irrf1   = 0;
            $obj->sest1   = 0;
            $obj->pis1    = 0;
            $obj->cofins1 = 0;
            $irrf   = 0;
            $sest   = 0;
            $pis    = 0;
            $cofins = 0;
        }

        $obj->saldo1   = $frete - $adt - $inss - $irrf - $sest - $pis - $cofins - $descontos;
        $obj->extenso1 = ExtensoReal::numeroPorExtenso($frete);

        TForm::sendData('form_contrato', $obj, false, false);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            $this->form->validate();
            $object = $this->form->getData('Contrato');
            $object->saldo1 = (float)$object->frete1 - (float)$object->adt1 - (float)$object->inss1 - (float)$object->irrf1 - (float)$object->sest1 - (float)$object->pis1 - (float)$object->cofins1 - (float)$object->descontos1;
            $object->extenso1 = ExtensoReal::numeroPorExtenso((float)$object->frete1);
            $object->store();
            $this->form->setData($object);
            TTransaction::close();
            new TMessage('info', 'Contrato salvo com sucesso!');
        }
        catch(Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
    
    public function onEdit($param)
    {
        try {
            if (isset($param['key'])) {
                TTransaction::open('sample');
                $object = new Contrato($param['key']);
                $this->form->setData($object);
                $proxy_param['veiculo_id'] = $object->veiculo_id;
                self::onVeiculoSelect($proxy_param);
                $proxy_param_valores = (array) $object;
                self::onUpdateValores($proxy_param_valores);
                TTransaction::close();
            } else {
                $this->onClear($param);
            }
        }
        catch(Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
    
    public function onClear($param)
    {
        $this->form->clear(true);
    }
}