<?php
/**
 * AliquotasImpostosForm - Configuração de alíquotas de impostos
 * Permite ajustar as taxas conforme mudanças na legislação.
 */
class AliquotasImpostosForm extends TPage
{
    protected $form;

    public function __construct($param = [])
    {
        parent::__construct($param);

        AliquotaImposto::createTableIfNotExists();

        $this->form = new BootstrapFormBuilder('form_aliquotas');
        $this->form->setFormTitle('Configuração de Alíquotas de Impostos (Transportador Autônomo)');

        // Campos para cada alíquota
        $irrf       = new TNumeric('aliq_IRRF',      4, ',', '.', true);
        $sest_senat = new TNumeric('aliq_SEST_SENAT', 4, ',', '.', true);
        $pis        = new TNumeric('aliq_PIS',        4, ',', '.', true);
        $cofins     = new TNumeric('aliq_COFINS',     4, ',', '.', true);

        foreach ([$irrf, $sest_senat, $pis, $cofins] as $f) {
            $f->setSize('100%');
        }

        $this->form->addFields(
            [new TLabel('IRRF (ex: 0,0150 = 1,5%)', '#FF0000')],       [$irrf],
            [new TLabel('SEST/SENAT (ex: 0,0150 = 1,5%)', '#FF0000')], [$sest_senat]
        );
        $this->form->addFields(
            [new TLabel('PIS (ex: 0,0065 = 0,65%)', '#FF0000')],       [$pis],
            [new TLabel('COFINS (ex: 0,0300 = 3%)', '#FF0000')],        [$cofins]
        );

        // Nota explicativa
        $this->form->addContent([TElement::tag('div',
            '<div class="alert alert-info mt-2" style="font-size:.87rem;">'
            . '<i class="fa fa-info-circle"></i> '
            . 'Estas alíquotas são aplicadas <strong>automaticamente</strong> sobre o valor do frete '
            . 'quando o INSS for informado na Carta de Frete. '
            . 'Informe o valor decimal (ex: 0,0150 para 1,5%).'
            . '</div>'
        )]);

        $this->form->addAction('Salvar Alíquotas', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink('Voltar',        new TAction(['ContratoList', 'onReload']), 'fa:arrow-left blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        parent::add($container);

        $this->onLoad();
    }

    private function onLoad()
    {
        try {
            $rates = AliquotaImposto::getAll();
            $data  = new stdClass;
            foreach ($rates as $codigo => $aliquota) {
                $field = 'aliq_' . $codigo;
                $data->$field = number_format((float)$aliquota, 4, ',', '.');
            }
            $this->form->setData($data);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            $conn = TTransaction::get();
            $stmt = $conn->prepare(
                "UPDATE aliquotas_impostos SET aliquota = ?, updated_at = ? WHERE codigo = ?"
            );

            $codigos = ['IRRF', 'SEST_SENAT', 'PIS', 'COFINS'];
            foreach ($codigos as $cod) {
                $field = 'aliq_' . $cod;
                $val   = isset($param[$field])
                    ? (float) str_replace(',', '.', str_replace('.', '', $param[$field]))
                    : null;
                if ($val !== null) {
                    $stmt->execute([$val, date('Y-m-d H:i:s'), $cod]);
                }
            }

            TTransaction::close();
            new TMessage('info', 'Alíquotas salvas com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
