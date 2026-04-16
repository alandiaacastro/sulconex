<?php
// [class-head]

// [/class-head]

/**
 * FaturacobrancaForm
 * Formulário Faturacobranca
 */
class FaturacobrancaForm extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TForm $form;
    private static $form_name = 'form_FaturacobrancaForm';
    
    // import traits
    use AdiantiCreatorFormTraits;
    
    // [class-body]

    // [/class-body]
    
    /**
     * Constructor
     * @author Creator
     */
    public function __construct($param)
    {
        parent::__construct();
        parent::setTargetContainer('adianti_right_panel');
        
        try
        {
            $this->ui = new AdiantiXMLFormRender;
            $this->ui->setController(__CLASS__);
            $this->ui->setPageName('Formulário Faturacobranca');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/FaturacobrancaForm.xml');
            TTransaction::close();
            
            $this->form = $this->ui->getForm();
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
        
        $vbox = new TVBox;
        $vbox->{'style'} = 'display:block;width:100%';
        $vbox->add($this->ui);
        parent::add( $vbox );
        
        parent::callIfExists('onAfterConstruct', $param);
    }//end-of-__construct()
    
    /**
     * onLoad()
     * @author Creator
     */
    public function onLoad($param)
    {
    
    }//end-of-onLoad()
    
    
    /**
     * edit()
     * @author Creator
     */
    private function edit($param)
    {
        try
        {
            if (isset($param['key']))
            {
                $key = $param['key'];
                TTransaction::open('Principal');
                $object = new Faturacobranca($key);
                
                
                
                $this->form->setData($object);
                
                TTransaction::close();
                return $object;
            }
            else
            {
                $this->form->clear(true);
            }
        }
        catch (Exception $e) // in case of exception
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }//end-of-edit()
    
    /**
     * save()
     * @author Creator
     */
    private function save($param)
    {
        try
        {
            TTransaction::open('Principal');
            
            $this->form->validate(); // run form validations
            $data = $this->form->getData(); // get form data as array
            
            $object = new Faturacobranca;
            $object->fromArray( (array) $data); // load the object with data
            
            
            
            $object->store();
            
            TTransaction::close();
            
            TToast::show('success', _t('Record saved'), 'bottom center', 'far:check-circle' );
            
            $this->closePage();
            
            return $object;
        }
        catch (Exception $e) // in case of exception
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }//end-of-save()
    
    
    /**
     * onEdit()
     */
    public function onEdit($param)
    {
        return $this->edit($param);
    }//end-of-onEdit()
    
    /**
     * onSave()
     */
    public function onSave($param)
    {
        $object = $this->save($param);
        if ($object)
        {
            AdiantiCoreApplication::loadPage('FaturacobrancaList', 'onLoad');
        }
        return $object;
    }//end-of-onSave()
    
    /**
     * onexitcliente()
     */
    public static function onexitcliente($param)
    {
        {   
    try
    {
        TTransaction::open('Principal');

        if (!empty($param['cliente_id']))
        {
            $cliente = new Clientes((int) $param['cliente_id']);

            if ($cliente)
            {
                $obj = new stdClass;
                $obj->cliente = $cliente->nome;
                $obj->cnpj_cliente = $cliente->cnpj;
                $obj->inscr_cliente = $cliente->inscricao_estadual;
                $obj->cliente_endereco = $cliente->endereco;
                $obj->cliente_cidade = $cliente->cidade;
                $obj->cliente_uf = $cliente->estado;

               TForm::sendData('form_FaturacobrancaForm', $obj);
            }
            else
            {
                new TMessage('warning', 'Cliente não encontrado');
            }
        }

        TTransaction::close();
    }
    catch (Exception $e)
    {
        new TMessage('error', $e->getMessage());
        TTransaction::rollback();
    }
}
    }//end-of-onexitcliente()
    
    /**
     * onsomafatura()
     */
    public static function onsomafatura($param)
    {
    try
{
    // 1. Obter os valores do formulário usando os nomes 'valor1', 'valor2', etc.
    $valor1 = $param['valor1'] ?? '0';
    $valor2 = $param['valor2'] ?? '0';
    $valor3 = $param['valor3'] ?? '0';

    // 2. Converter os valores do formato BR (1.234,56) para float (1234.56)
    $valor1 = str_replace('.', '', $valor1);
    $valor1 = str_replace(',', '.', $valor1);
    $valor1 = (float) $valor1;

    $valor2 = str_replace('.', '', $valor2);
    $valor2 = str_replace(',', '.', $valor2);
    $valor2 = (float) $valor2;

    $valor3 = str_replace('.', '', $valor3);
    $valor3 = str_replace(',', '.', $valor3);
    $valor3 = (float) $valor3;

    // 3. Somar os valores
    $soma_total = $valor1 + $valor2 + $valor3;

    // 4. Formatar o resultado de volta para o formato BR
    $total_formatado = number_format($soma_total, 2, ',', '.');

    // 5. Enviar o resultado de volta para o campo 'total' do formulário
    $obj = new stdClass;
    $obj->total = $total_formatado;

    // Lembre-se de usar o nome correto do seu formulário aqui!
    TForm::sendData('form_FaturacobrancaForm', $obj); 
}
catch (Exception $e)
{
    new TMessage('error', $e->getMessage());
}
    }//end-of-onsomafatura()
    
    /**
     * onextenso()
     */
   public static function onextenso($param)
{
    try
    {
        if (isset($param['total']) && $param['total'] !== '')
        {
            // Etapa 1: Pega o valor do formulário e converte para um float padrão.
            $valor_bruto = $param['total'];
            $valor = (float) str_replace(['.', ','], ['', '.'], $valor_bruto);

            if (!is_numeric($valor)) {
                throw new Exception('Valor inválido para conversão.');
            }

            // --- INÍCIO DA LÓGICA DE CONVERSÃO OTIMIZADA ---

            // Dicionários para a conversão
            $singular = ['centavo', 'real', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão'];
            $plural   = ['centavos', 'reais', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões'];

            $c = ["", "cem", "duzentos", "trezentos", "quatrocentos", "quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos"];
            $d = ["", "dez", "vinte", "trinta", "quarenta", "cinquenta", "sessenta", "setenta", "oitenta", "noventa"];
            $d10 = ["dez", "onze", "doze", "treze", "quatorze", "quinze", "dezesseis", "dezessete", "dezoito", "dezenove"];
            $u = ["", "um", "dois", "três", "quatro", "cinco", "seis", "sete", "oito", "nove"];

            $extenso = "";

            if ($valor == 0) {
                $extenso = "zero reais";
            } else {
                // Separa a parte inteira (reais) da parte fracionária (centavos)
                $inteiro = floor($valor);
                $fracao = round(($valor - $inteiro) * 100);

                // Parte 1: Converte a parte inteira (Reais)
                $rt = '';
                if ($inteiro > 0) {
                    $valor_str = (string)$inteiro;
                    if (strlen($valor_str) > 15) { // Limite para quatrilhões
                        throw new Exception("Valor muito grande para ser convertido.");
                    }

                    // Divide o número em grupos de 3 dígitos (ex: 1.234.567 -> [567, 234, 1])
                    $grupos = array_reverse(str_split(str_pad($valor_str, ceil(strlen($valor_str) / 3) * 3, '0', STR_PAD_LEFT), 3));
                    $rt_array = [];

                    for ($i = 0; $i < count($grupos); $i++) {
                        $grupo_valor = (int)$grupos[$i];
                        if ($grupo_valor === 0) continue;

                        $c_val = (int)($grupos[$i][0]);
                        $d_val = (int)($grupos[$i][1]);
                        $u_val = (int)($grupos[$i][2]);

                        $rc = ($c_val > 0) ? (($c_val === 1 && $d_val === 0 && $u_val === 0) ? 'cem' : $c[$c_val]) : '';
                        $rd = ($d_val > 0) ? (($d_val === 1) ? $d10[$u_val] : $d[$d_val]) : '';
                        $ru = (($d_val === 1) || ($u_val === 0)) ? '' : $u[$u_val];
                        
                        $r = $rc . (($rc && ($rd || $ru)) ? ' e ' : '') . $rd . (($rd && $ru && $d_val > 1) ? ' e ' : '') . $ru;
                        
                        $escala_idx = $i + 1; // Índice da escala (1=reais, 2=mil, 3=milhão...)
                        if ($i > 0) { // Adiciona a escala (mil, milhão...)
                            $r .= ' ' . ($grupo_valor > 1 ? $plural[$escala_idx] : $singular[$escala_idx]);
                        }
                        
                        // Exceção para "um mil" -> "mil"
                        if ($i === 1 && $grupo_valor === 1) {
                           $r = $singular[$escala_idx];
                        }
                        
                        $rt_array[] = $r;
                    }
                    $rt = implode(' e ', array_reverse($rt_array));
                    $rt = preg_replace('/ e mil$/', ' mil', $rt); // Corrige 'um milhão e mil'
                    
                    // Adiciona "real" ou "reais"
                    $rt .= ' ' . ($inteiro > 1 ? $plural[1] : $singular[1]);
                }

                // Parte 2: Converte a parte fracionária (Centavos)
                $rfc = '';
                if ($fracao > 0) {
                    $fracao_str = str_pad($fracao, 2, "0", STR_PAD_LEFT);
                    $d_val = (int)($fracao_str[0]);
                    $u_val = (int)($fracao_str[1]);
                    
                    $rd = ($d_val > 0) ? (($d_val === 1) ? $d10[$u_val] : $d[$d_val]) : '';
                    $ru = (($d_val === 1) || ($u_val === 0)) ? '' : $u[$u_val];
                    
                    $r = $rd . (($rd && $ru && $d_val > 1) ? ' e ' : '') . $ru;
                    
                    $rfc = $r . ' ' . (($fracao > 1) ? $plural[0] : $singular[0]);
                }
                
                // Parte 3: Combina as partes
                if ($rt && $rfc) {
                    $extenso = $rt . ' e ' . $rfc;
                } else {
                    $extenso = trim($rt . $rfc); // Se uma das partes for vazia, apenas concatena
                }
            }
            // --- FIM DA LÓGICA DE CONVERSÃO ---

            // Etapa 2: Prepara o objeto para enviar os dados de volta para o formulário
            $obj = new stdClass;
            $obj->extenso = mb_strtoupper($extenso, 'UTF-8');

            // Etapa 3: Envia os dados para o formulário (lembre-se de usar o nome correto do seu formulário)
            TForm::sendData('form_FaturacobrancaForm', $obj);
        }
    }
    catch (Exception $e)
    {
        new TMessage('error', '<b>Erro ao converter valor:</b> ' . $e->getMessage());
    }
    
    }//end-of-onextenso()
    
}//end-of-class
