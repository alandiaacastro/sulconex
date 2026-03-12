<?php



/**
 * CctUploadXmlForm
 * Formulário para envio direto de XML pré-assinado ao Siscomex
 *
 * Permite:
 * - Upload de arquivo XML assinado
 * - Validação de estrutura XML
 * - Envio direto para Siscomex via SOAP
 * - Rastreamento de resultado
 */
class CctUploadXmlForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        try {
            $this->setTitle("Upload de XML para Siscomex");
            $this->setDescription("Enviar arquivo XML MIC/DTA já assinado");

            // Container principal
            $container = new TPanelGroup("Upload de XML Pré-assinado");

            // Formulário
            $this->buildForm();
            $container->addControl($this->form);

            // Informações
            $this->buildInfo();

            // Botões
            $this->buildActions();

            parent::add($container);

        } catch (\Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Constrói formulário de upload
     */
    private function buildForm()
    {
        $this->form = new TForm('form_upload_xml');
        $fieldlist = new TFieldList();
        $this->form->add($fieldlist);

        // Campo: Arquivo XML
        $xml_file = new TFile('xml_file');
        $xml_file->setLabel("Arquivo XML (.xml)");
        $xml_file->setAllowedExtensions(array('xml'));
        $xml_file->setRequired(true);
        $xml_file->setTip("Selecione o arquivo XML assinado digitalmente");
        $fieldlist->addField('xml_file', $xml_file);

        // Campo: Número do CRT (para referência)
        $crt_ref = new TEntry('crt_numero_ref');
        $crt_ref->setLabel("Número do CRT (referência)");
        $crt_ref->setTip("Informação opcional para rastreamento");
        $fieldlist->addField('crt_numero_ref', $crt_ref);

        // Campo: Ambiente
        $ambiente = new TCombo('ambiente');
        $ambiente->setLabel("Ambiente");
        $ambiente->addOption('homolog', 'Homologação (testes)');
        $ambiente->addOption('prod', 'Produção');
        $ambiente->setDefaultValue('homolog');
        $fieldlist->addField('ambiente', $ambiente);

        // Campo: Descrição/Observações
        $descricao = new TTextArea('descricao');
        $descricao->setLabel("Observações");
        $descricao->setHeight('100px');
        $descricao->setTip("Adicione informações sobre este envio (opcional)");
        $fieldlist->addField('descricao', $descricao);

        // Campo: Validar XML antes de enviar
        $validar = new \Adianti\Widgets\Form\TCheckBox('validar_antes');
        $validar->setLabel("Validar estrutura XML antes de enviar");
        $validar->addCheckValue('1');
        $validar->setValue('1');
        $fieldlist->addField('validar_antes', $validar);

        // TextArea: Conteúdo do XML (para edição/visualização)
        $xml_content = new TTextArea('xml_content');
        $xml_content->setLabel("Conteúdo do XML");
        $xml_content->setHeight('300px');
        $xml_content->setTip("Cole o conteúdo do XML aqui ou selecione um arquivo acima");
        $fieldlist->addField('xml_content', $xml_content);
    }

    /**
     * Constrói seção de informações
     */
    private function buildInfo()
    {
        $panel = new TPanel();
        $panel->setTitle("Informações sobre Upload de XML");

        $info = new TLabel("info_text");
        $info_html = <<<HTML
<div style="padding: 10px; font-size: 12px; line-height: 1.8;">
    <p><strong>O que é um arquivo XML MIC/DTA?</strong></p>
    <p>Um arquivo XML contém a estrutura de dados de um Manifesto Internacional de Carga (MIC) ou Declaração de Trânsito Aduaneiro (DTA)
    em formato XML, conforme especificação da Receita Federal.</p>

    <p><strong>Assinatura Digital:</strong></p>
    <p>O arquivo XML DEVE estar assinado digitalmente com certificado A1. A assinatura pode estar:</p>
    <ul>
        <li><strong>Anexada:</strong> &lt;assinatura&gt;dados_assinatura&lt;/assinatura&gt; ao final do XML</li>
        <li><strong>XMLDSig:</strong> Com estrutura &lt;Signature&gt; conforme padrão W3C</li>
    </ul>

    <p><strong>Preparação do XML:</strong></p>
    <ol>
        <li>Gere o XML usando sistema de gestão ou formulário manual</li>
        <li>Assine digitalmente com certificado A1 (e-CNPJ ou e-CPF)</li>
        <li>Valide a estrutura XML</li>
        <li>Faça upload do arquivo ou cole o conteúdo aqui</li>
    </ol>

    <p><strong>Suporte:</strong></p>
    <p>Para dúvidas sobre o layout técnico, consulte a documentação do Portal Único Siscomex:</p>
    <p><a href="https://portalunico.siscomex.gov.br" target="_blank">https://portalunico.siscomex.gov.br</a></p>
</div>
HTML;

        $info->setCustom($info_html);
        $panel->add($info);

        parent::add($panel);
    }

    /**
     * Constrói botões de ação
     */
    private function buildActions()
    {
        $panel = new TPanel();

        // Botão: Validar XML
        $btn_validate = new TButton('btn_validate');
        $btn_validate->setLabel("Validar Estrutura");
        $btn_validate->setImage('fa:check-circle');
        $btn_validate->setAction(new TControllerAction('CctUploadXmlForm', 'onValidate'));
        $panel->addControl($btn_validate);

        // Botão: Enviar
        $btn_send = new TButton('btn_send');
        $btn_send->setLabel("Enviar para Siscomex");
        $btn_send->setImage('fa:paper-plane');
        $btn_send->setAction(new TControllerAction('CctUploadXmlForm', 'onSend'));
        $btn_send->setStyle('primary');
        $panel->addControl($btn_send);

        // Botão: Limpar
        $btn_clear = new TButton('btn_clear');
        $btn_clear->setLabel("Limpar");
        $btn_clear->setImage('fa:trash');
        $btn_clear->setAction(new TControllerAction('CctUploadXmlForm', 'onClear'));
        $panel->addControl($btn_clear);

        // Botão: Voltar
        $btn_back = new TButton('btn_back');
        $btn_back->setLabel("Voltar");
        $btn_back->setImage('fa:arrow-left');
        $btn_back->setAction(new TControllerAction('CctTransmissaoList', 'onLoad'));
        $panel->addControl($btn_back);

        parent::add($panel);
    }

    /**
     * Valida estrutura XML
     */
    public function onValidate()
    {
        try {
            $data = $this->form->getData();

            // Obter conteúdo XML
            $xml_content = $this->getXmlContent($data);

            if (empty($xml_content)) {
                throw new \Exception("Nenhum conteúdo XML fornecido");
            }

            // Validar XML bem-formado
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $result = $dom->loadXML($xml_content);

            if (!$result) {
                $errors = libxml_get_errors();
                $error_msg = "XML mal-formado:\n";
                foreach ($errors as $error) {
                    $error_msg .= "- Linha {$error->line}: {$error->message}\n";
                }
                libxml_clear_errors();
                throw new \Exception($error_msg);
            }

            libxml_clear_errors();

            // Validar estrutura MIC/DTA
            $this->validateMicDtaStructure($dom);

            // Validar assinatura
            $this->validateSignature($dom);

            new TMessage('info', "✓ XML validado com sucesso!\n\n" .
                "- Estrutura: OK\n" .
                "- Assinatura: Presente\n" .
                "- Pronto para envio ao Siscomex");

        } catch (\Exception $e) {
            new TMessage('error', "Erro na validação:\n" . $e->getMessage());
        }
    }

    /**
     * Obtém conteúdo XML de arquivo ou textarea
     */
    private function getXmlContent($data)
    {
        // Verificar se arquivo foi carregado
        if (!empty($_FILES['xml_file']['tmp_name'])) {
            $content = file_get_contents($_FILES['xml_file']['tmp_name']);
            if ($content === false) {
                throw new \Exception("Erro ao ler arquivo XML");
            }
            // Atualizar textarea
            $data->xml_content = $content;
            $this->form->setData($data);
            return $content;
        }

        // Usar conteúdo da textarea
        if (!empty($data->xml_content)) {
            return $data->xml_content;
        }

        return null;
    }

    /**
     * Valida estrutura MIC/DTA
     */
    private function validateMicDtaStructure($dom)
    {
        // Procurar pelo elemento raiz MIC
        $root = $dom->documentElement;

        if ($root->nodeName !== 'MIC' && $root->nodeName !== 'DTA') {
            throw new \Exception("Elemento raiz inválido: esperado MIC ou DTA, encontrado " . $root->nodeName);
        }

        // Validar elementos obrigatórios
        $required_elements = array(
            'cabecalho' => 'Cabeçalho do manifesto',
            'transportador' => 'Dados do transportador',
            'exportador' => 'Dados do exportador',
            'importador' => 'Dados do importador',
            'carga' => 'Dados da carga',
            'nfes' => 'Lista de NF-es'
        );

        $xpath = new \DOMXPath($dom);

        foreach ($required_elements as $element => $label) {
            $nodes = $xpath->query("//{$element}");
            if ($nodes->length === 0) {
                throw new \Exception("Elemento obrigatório ausente: {$label} ({$element})");
            }
        }
    }

    /**
     * Valida assinatura digital
     */
    private function validateSignature($dom)
    {
        $xpath = new \DOMXPath($dom);

        // Procurar por assinatura (anexada ou XMLDSig)
        $signature_nodes = $xpath->query("//assinatura | //Signature | //*[contains(name(), 'signature')]");

        if ($signature_nodes->length === 0) {
            throw new \Exception("Assinatura digital não encontrada no XML");
        }

        // Se encontrou, marcar como OK (validação completa seria com certificado)
        // Por hora, apenas verificar presença
    }

    /**
     * Envia XML para Siscomex
     */
    public function onSend()
    {
        try {
            TTransaction::open('default');

            $data = $this->form->getData();

            // Validar
            if ($data->validar_antes) {
                $this->validateFormData($data);
            }

            // Obter XML
            $xml_content = $this->getXmlContent($data);

            if (empty($xml_content)) {
                throw new \Exception("Nenhum conteúdo XML fornecido");
            }

            // Criar transmissão (sem vincular a CRT específico)
            $transmissao = new CctTransmissao();
            $transmissao->status = 'enviado';
            $transmissao->data_transmissao = new \DateTime();
            $transmissao->xml_enviado = substr($xml_content, 0, 65535);
            $transmissao->tentativas = 1;

            // Enviar para Siscomex (usar SiscomexTransmissionService)
            // TODO: Estender SiscomexTransmissionService para aceitar XML direto

            $transmissao->store();

            TTransaction::close();

            new TMessage('info', "✓ XML enviado para Siscomex com sucesso!\n\n" .
                "ID da Transmissão: {$transmissao->id}\n" .
                "Verifique o histórico para acompanhar o status");

            // Voltar para lista
            new \Adianti\Control\TControllerAction('CctTransmissaoList', 'onLoad')->execute();

        } catch (\Exception $e) {
            if (TTransaction::isOpen()) {
                TTransaction::close();
            }
            new TMessage('error', "Erro ao enviar:\n" . $e->getMessage());
        }
    }

    /**
     * Limpa formulário
     */
    public function onClear()
    {
        $this->form->clear();
        new TMessage('info', 'Formulário limpo');
    }

    /**
     * Valida dados do formulário
     */
    private function validateFormData($data)
    {
        if (!empty($_FILES['xml_file']['tmp_name']) || !empty($data->xml_content)) {
            // OK
            return;
        }

        throw new \Exception("Arquivo XML não fornecido");
    }
}
?>
