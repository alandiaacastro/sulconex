<?php
// [class-head]

// [/class-head]

/**
 * FaturacobrancaList
 * Listar Faturacobranca
 */
class FaturacobrancaList extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TDataGrid $datagrid;
    private TPageNavigation $pageNavigation;
    private TForm $search;
    private static $form_name = 'form_FaturacobrancaList';
    
    // import traits
    use AdiantiCreatorListTraits;
    
    // [class-body]

    // [/class-body]
    
    /**
     * Constructor
     * @author Creator
     */
    public function __construct($param)
    {
        parent::__construct();
        
        $this->setDatabase('Principal'); // defines the database
        $this->setActiveRecord('Faturacobranca'); // defines the active record
        $this->setDefaultOrder('id', 'asc');  // defines the default order
        $this->setLimit(10);
        
        
        try
        {
            $this->ui = new AdiantiXMLFormRender;
            $this->ui->setController(__CLASS__);
            $this->ui->setPageName('Listar Faturacobranca');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/FaturacobrancaList.xml');
            TTransaction::close();
            
            $this->datagrid = $this->ui->getDatagrid();
            $this->setExportedObject($this->datagrid);
            $this->setLoaderObject($this->datagrid);
            
            if ($this->datagrid->getPageNavigation())
            {
                $this->pageNavigation = $this->datagrid->getPageNavigation();
                $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
            }
            if ($this->datagrid->getSearchForm())
            {
                $this->search = $this->datagrid->getSearchForm();
                $this->search->getField('search_button')->setAction(new TAction([__CLASS__, 'onSearch']));
                $this->search->setData( TSession::getValue(__CLASS__.'_filter_data') );
            }
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
        
        parent::add( $this->packUI( true ) );
        
        parent::callIfExists('onAfterConstruct', $param);
    }//end-of-__construct()
    
    /**
     * onShowFilters()
     * @author Creator
     */
    public function onShowFilters($param)
    {
        self::showInRightPanel($this->search);
    }//end-of-onShowFilters()
    
    /**
     * onSelectColumns()
     * @author Creator
     */
    public function onSelectColumns($param)
    {
        $this->selectColumns($param);
    }//end-of-onSelectColumns()
    
    /**
     * onChangeLimit()
     * @author Creator
     */
    public static function onChangeLimit($param)
    {
        self::changeLimit($param);
    }//end-of-onChangeLimit()
    
    /**
     * onQuickSearch()
     * @author Creator
     */
    public static function onQuickSearch($param)
    {
        self::quickSearch($param);
    }//end-of-onQuickSearch()
    
    /**
     * onExportPDF()
     * @author Creator
     */
    public function onExportPDF($param)
    {
        $output = $this->exportToPDF($param);
        self::showInWindow(self::embedPDFObject($output), 'Listar Faturacobranca');
    }//end-of-onExportPDF()
    
    /**
     * onExportXLS()
     * @author Creator
     */
    public function onExportXLS($param)
    {
        $output = $this->exportToXLS($param);
        self::downloadFile($output);
    }//end-of-onExportXLS()
    
    /**
     * reload()
     * @author Creator
     */
    private function reload($param)
    {
        try
        {
            TTransaction::open('Principal');
            
            $objects = $this->loadObjectsFromFilters($param);
            $this->datagrid->clear();
            if ($objects)
            {
                foreach ($objects as $object)
                {
                    $row = $this->datagrid->addItem($object);
                    $row->{'data-key'} = $object->getPrimaryKeyValue();
                }
            }
            
            $this->configurePageNavigation($param);
            
            TTransaction::close();
            return $objects;
        }
        catch (Exception $e) // in case of exception
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }//end-of-reload()
    
    /**
     * onLoad()
     * @author Creator
     */
    public function onLoad($param)
    {
    
    }//end-of-onLoad()
    
    
    /**
     * onDelete()
     */
    public function onDelete($param)
    {
        $this->confirmDeletion($param);
    }//end-of-onDelete()
    
    /**
     * onSearch()
     */
    public function onSearch($param)
    {
        $this->buildSessionFilters($param);
        $this->onReload( ['offset'=>0, 'first_page'=>1] );
    }//end-of-onSearch()
    
    /**
     * onReload()
     */
    public function onReload($param)
    {
        $this->reload($param);
    }//end-of-onReload()
    
    /**
     * onprint()
     */
    public function onprint($param)
    {
try
{
    // 1. Abertura da transação
    TTransaction::open('Principal');

    // 2. Validação e carregamento do objeto Faturacobranca
    if (empty($param['key'])) {
        throw new Exception('A chave do registro não foi fornecida para a impressão.');
    }
    $key = $param['key'];
    $object = new Faturacobranca($key);
    if (!$object) {
        throw new Exception('Fatura não encontrada no banco de dados.');
    }

    // 3. Função para corrigir a codificação de caracteres para o FPDF
    $conv = function($text) {
        return mb_convert_encoding((string)$text, 'ISO-8859-1', 'UTF-8');
    };

    // 4. Inicialização do PDF
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    // ====================================================================
    // INÍCIO DO LAYOUT DO PDF
    // ====================================================================

    // --- CABEÇALHO ---
    $pdf->SetFont('Arial', '', 8);
    $textotransp = "COOPERATIVA DOS TRANSPORTADORES DE CARGAS E SERVIÇOS LOGISTICOS - SULCONEXLOG\nAVENIDA SANTOS DUMONT, 777\nRUI RAMOS URUGUAIANA-RS-BRASIL\nCNPJ 48.816.176/0001-42";
    $pdf->SetXY(56, 14);
    $pdf->MultiCell(142, 4, $conv($textotransp), 0, 'L');
    $pdf->SetLineWidth(0.2);
    $pdf->Line(28, 39, 100, 39);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Text(57,14, $conv('COOP DOS TRANPORTADORES  -  4011268'));

    /* --- QUADRO NÚMERO DA FATURA --- */
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Text(10, 40, $conv('FATURA'));
    $pdf->Rect(100, 34, 30, 10, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(101, 36, $conv('NÚMERO FATURA'));
    $pdf->SetFont('Arial', '', 10);
    $pdf->Text(108, 42, $conv($object->numero));

    /* --- QUADRO EMISSÃO --- */
    $pdf->Rect(132, 34, 30, 10, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(133, 36, $conv('EMISSÃO'));
    $pdf->SetFont('Arial', '', 10);
    $data_emissao = TDate::date2br($object->emissao);
    $pdf->Text(138, 42, $conv($data_emissao));

    /* --- QUADRO VENCIMENTO --- */
    $pdf->Rect(164, 34, 32, 10, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(165, 36, $conv('VENCIMENTO'));
    $pdf->SetFont('Arial', 'B', 10);
    $data_vencimento = TDate::date2br($object->vencimento);
    $pdf->Text(170, 42, $conv($data_vencimento));

    /* --- DADOS DO CLIENTE --- */
    $pdf->Rect(10, 46, 186, 30, 'D');
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Text(12, 50, $conv('Cliente'));
    $pdf->Text(12, 57, $conv('Endereço'));
    $pdf->Text(120, 50, $conv('Insc.CNPJ/MF'));
    $pdf->Text(160, 50, $conv('Insc.Estadual'));
    
    $pdf->SetFont('Arial', '', 8);
    // Utiliza os atributos do objeto $object conforme a definição da classe
    $pdf->Text(12, 53, $conv($object->clientekey->nome));
    $pdf->Text(12, 60, $conv($object->clientekey->endereco)); // Endereço já vem formatado
    $pdf->Text(12, 63, $conv($object->clientekey->cidade . ' - ' . $object->clientekey->estado));
    $pdf->Text(120, 53, $conv($object->clientekey->cnpj));
    $pdf->Text(160, 53, $conv($object->clientekey->inscricao_estadual));


    /* --- QUADROS DE VALORES E INFORMAÇÕES --- */
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Rect(10, 78, 46, 12, 'D');
    $pdf->Text(11, 81, $conv('VALOR DA FATURA'));
    $pdf->SetFont('Arial', '', 12);
    $pdf->Text(12, 88, 'R$ ' . number_format((float) $object->total, 2, ',', '.'));

    $pdf->Rect(57, 78, 46, 12, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(71, 81, $conv('DUPLICATA'));
    $pdf->Line(57, 82, 103, 82);
    $pdf->Line(78, 82, 78, 90); // linha vertical
    $pdf->Text(61, 85, $conv('VALOR'));
    $pdf->SetFont('Arial', '', 8);
    $pdf->Text(60, 88, 'R$ ' . number_format((float) $object->total, 2, ',', '.'));
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(81, 85, $conv('ORDEM')); // Adicionar lógica se necessário

    $pdf->Rect(104, 78, 34, 12, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(106, 81, $conv('CRT'));
    $pdf->SetFont('Arial', '', 11);
    $pdf->Text(108, 88, $conv($object->conhecimentokey->numero)); // Assumindo que 'conhecimento' armazena o número do CRT

    $pdf->Rect(139, 78, 57, 12, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(141, 81, $conv('Nº FATURA EXTERNA'));
    $pdf->SetFont('Arial', '', 10);
    // Este campo não existe na definição da classe, pode ser necessário adicioná-lo
    $pdf->Text(148, 88, $conv($object->conhecimentokey->fatura_crt)); 

    /* --- OBSERVAÇÕES E VALOR POR EXTENSO --- */
    $pdf->Rect(10, 92, 186, 11, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(11, 95, $conv('VALOR POR EXTENSO'));
    $pdf->SetFont('Arial', '', 8);
   // Este campo não existe na definição da classe, pode ser necessário adicioná-lo
    $pdf->Text(11, 100, $conv($object->extenso));
    $pdf->Text(11, 107, $conv('A DUPLICATA CORRESPONDENTE A ESTA FATURA DEVERÁ SER PAGA NO VENCIMENTO E PRAÇA ABAIXO CITADOS A'));
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Text(11, 110, $conv('COOPERATIVA DOS TRANSPORTADORES DE CARGAS E SERVIÇOS LOGISTICOS-SULCONEXLOG'));

    /* --- DADOS BANCÁRIOS E ASSINATURAS --- */
    $pdf->Rect(10, 112, 94, 25, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(11, 115, $conv('AGENTE FINANCEIRO - BANCO: '));
    $pdf->SetFont('Arial', '', 8);
    $pdf->Text(11, 119, $conv('BANCO ITAU S.A '));
    $pdf->Text(11, 122, $conv('AGENCIA 0324'));
    $pdf->Text(11, 125, $conv('CONTA CORRENTE 99432-6'));
    $pdf->Text(11, 128, $conv('CNPJ 48.816.176/0001-42 '));
    $pdf->Text(11, 131, $conv('BENEFICIARIO: COOP.TRANSP.CARGAS SULCONEXLOG'));

    $pdf->Rect(105, 112, 91, 25, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Line(126, 126, 168, 126);
    $pdf->Text(126, 130, $conv('ASSINATURA DO PRESIDENTE OU VICE'));
    $pdf->SetFont('Arial', '', 8);
    $pdf->Text(126, 133, $conv('COOPERATIVA SULCONEXLOG '));

    /* --- DETALHAMENTO DOS SERVIÇOS --- */
    $pdf->Rect(10, 139, 186, 120, 'D');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(11, 142, $conv('CONHECIMENTO '));
    $pdf->SetFont('Arial', '', 8);
    $pdf->Text(11, 148, $conv($object->conhecimentokey->numero)); // Novamente, o número do CRT

    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(34, 142, $conv('DESCRIÇÃO DAS MERCADORIAS E SERVIÇOS'));
    $pdf->SetFont('Arial', '', 8);
    $pdf->Text(34, 148, $conv($object->descricao1));
    $pdf->Text(34, 152, $conv($object->descricao2));
    $pdf->Text(34, 156, $conv($object->descricao3));
    
    // Estes campos não existem na classe, verificar necessidade
      $pdf->Text(34, 164, $conv('NOTAS FISCAIS.:'));
      $pdf->Text(60, 164, $conv($object->notafiscal));

      $pdf->Text(34, 174, $conv('ORIGEM.:'));
      $pdf->Text(60, 174, $conv($object->conhecimentokey->local_responsabilidade));

      $pdf->Text(34, 180, $conv('DESTINO.:'));
      $pdf->Text(60, 180, $conv($object->conhecimentokey->local_entrega));

      $pdf->Text(34, 186, $conv('REMETENTE.:'));
      $pdf->Text(60, 186, $conv($object->conhecimentokey->remetente->nome));

      $pdf->Text(34, 192, $conv('DESTINATARIO.:'));
      $pdf->Text(60, 192, $conv($object->conhecimentokey->destinatario->nome));

      $pdf->Text(34, 198, $conv('FATURA.:'));
      $pdf->Text(60, 198, $conv($object->conhecimentokey->fatura_crt));

   // Escreve o rótulo "PESOBRUTO.:" já com a conversão correta
$label_peso = 'PESOBRUTO.:';
$pdf->Text(34, 204, mb_convert_encoding($label_peso, 'ISO-8859-1', 'UTF-8'));

// 1. Inicialize o peso com um valor padrão (0).
$peso_valor = 0;

// 2. Verifique de forma segura se o peso existe no objeto.
if (isset($object->conhecimentokey) && !empty($object->conhecimentokey->peso_bruto_kg)) 
{
    // 3. Se existir, atribua o valor à variável.
    $peso_valor = $object->conhecimentokey->peso_bruto_kg;
}

// 4. Formate o número e adicione a unidade ' kg'.
$peso_formatado_com_unidade = number_format($peso_valor, 2, ',', '.') . ' kg';

// 5. Use a função moderna para converter a codificação da string final.
$valor_para_pdf = mb_convert_encoding($peso_formatado_com_unidade, 'ISO-8859-1', 'UTF-8');

// 6. Adicione o valor ao PDF na posição desejada.
$pdf->Text(60, 204, $valor_para_pdf);

      $pdf->Text(34, 210, $conv('PRODUTO.:'));
      $pdf->Text(60, 210, $conv($object->prod));

      $pdf->Text(34, 216, $conv('OBSERVAÇÃO.:'));
      $pdf->Text(60, 216, $conv($object->obs));


    // $pdf->Text(34, 200, $conv('FATURA .:'));
    // $pdf->Text(60, 200, $conv($object->fatura_cliente));
    // $pdf->Text(34, 218, $conv('OBSERVAÇÕES.:'));
    // $pdf->Text(34, 224, $conv($object->texto_observacao));
    
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(162, 142, $conv('VALOR'));
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY(160, 142);
    $pdf->Cell(30, 10, 'R$ ' . number_format((float) $object->valor1, 2, ',', '.'), 0, 0, 'R');
    $pdf->SetXY(160, 146);
    $pdf->Cell(30, 10, 'R$ ' . number_format((float) $object->valor2, 2, ',', '.'), 0, 0, 'R');
    $pdf->SetXY(160, 150);
    $pdf->Cell(30, 10, 'R$ ' . number_format((float) $object->valor3, 2, ',', '.'), 0, 0, 'R');
    
    $pdf->SetXY(160, 250);
    $pdf->Cell(30, 10, 'R$ ' . number_format((float) $object->total, 2, ',', '.'), 0, 0, 'R');
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(161, 252, $conv('TOTAL FATURA'));

    $pdf->Line(32, 139, 32, 259); // linha vertical
    $pdf->Line(160, 139, 160, 259); // linha vertical

    /* --- RODAPÉ --- */
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(10, 262, $conv('OBSERVAÇÕES '));
    $pdf->Rect(10, 264, 186, 20, 'D');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Text(11, 268, $conv('DECLARO QUE RECEBI OS CONHECIMENTOS CONSTANTES NESTA FATURA.'));
    $pdf->Line(20, 278, 60, 278);
    $pdf->Text(34, 282, $conv('DATA'));
    $pdf->Line(130, 278, 180, 278);
    $pdf->Text(145, 282, $conv('ASSINATURA'));

    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Text(11, 288, $conv('USUARIO E DATA DE INCLUSAO '));
    $pdf->Text(119, 288, $conv('USUARIO E DATA DE ALTERACAO '));


$img_file = 'files/images/2/COOPERATIVA.png';
if (file_exists($img_file)) {
    $pdf->Image(realpath($img_file), 12, 12, 40);
} else {
    // Se não achar a imagem, imprime um aviso discreto ou ignora para não travar o PDF
    $pdf->SetXY(12, 12);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->Cell(40, 10, $conv('Logo não encontrada'), 0);
}
    // ====================================================================
    // FIM DO LAYOUT DO PDF
    // ====================================================================

    // 5. Geração e abertura do arquivo
    $file_path = 'tmp/fatura_'.uniqid().'.pdf';
    $pdf->Output($file_path, 'F');
    parent::openFile($file_path);

    // 6. Fechamento da transação
    TTransaction::close();
}
catch (Exception $e)
{
    // Em caso de erro, exibe uma mensagem e desfaz a transação
    new TMessage('error', '<b>Erro ao gerar o PDF:</b> ' . $e->getMessage());
    TTransaction::rollback();
}



    }//end-of-onprint()
    
}//end-of-class
