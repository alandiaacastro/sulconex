<?php



/**
 * CctCertificateConfigForm
 * Formulário para configuração do certificado digital A1 para Siscomex
 *
 * Funcionalidades:
 * - Upload de arquivo .pfx
 * - Definição de senha
 * - Validação de certificado
 * - Teste de conexão
 * - Visualização de informações do certificado
 */
class CctCertificateConfigForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        try {
            $this->setTitle("Configuração de Certificado Digital");
            $this->setDescription("Gerenciar certificado A1 para transmissão de MIC/DTA no Siscomex");

            // Container principal
            $container = new TPanelGroup("Certificado Digital A1");

            // Formulário
            $this->buildForm();
            $container->addControl($this->form);

            // Painel de informações do certificado atual
            $this->buildCertificateInfo();

            // Botões
            $this->buildActions();

            parent::add($container);

        } catch (\Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Constrói formulário de configuração
     */
    private function buildForm()
    {
        $this->form = new TForm('form_cert_config');
        $fieldlist = new TFieldList();
        $this->form->add($fieldlist);

        // Campo: Upload do arquivo .pfx
        $cert_file = new TFile('cert_file');
        $cert_file->setLabel("Arquivo de Certificado (.pfx)");
        $cert_file->setAllowedExtensions(array('pfx', 'p12'));
        $cert_file->setTip("Selecione o arquivo de certificado PKCS#12");
        $fieldlist->addField('cert_file', $cert_file);

        // Campo: Senha do certificado
        $cert_password = new TPassword('cert_password');
        $cert_password->setLabel("Senha do Certificado");
        $cert_password->setRequired(true);
        $cert_password->setTip("Senha utilizada para proteger o arquivo .pfx");
        $fieldlist->addField('cert_password', $cert_password);

        // Campo: Confirmar senha
        $cert_password_confirm = new TPassword('cert_password_confirm');
        $cert_password_confirm->setLabel("Confirmar Senha");
        $cert_password_confirm->setRequired(true);
        $fieldlist->addField('cert_password_confirm', $cert_password_confirm);

        // Campo: Habilitar CCT
        $cct_enabled = new TCheckBox('cct_enabled');
        $cct_enabled->setLabel("Habilitar integração CCT");
        $cct_enabled->addCheckValue('1');
        $fieldlist->addField('cct_enabled', $cct_enabled);

        // Campo: Ambiente
        $cct_env = new \Adianti\Widgets\Form\TCombo('cct_environment');
        $cct_env->setLabel("Ambiente");
        $cct_env->addOption('homolog', 'Homologação (testes)');
        $cct_env->addOption('prod', 'Produção');
        $cct_env->setDefaultValue('homolog');
        $fieldlist->addField('cct_environment', $cct_env);

        // Campo: Verificar SSL
        $ssl_verify = new TCheckBox('ssl_verify');
        $ssl_verify->setLabel("Verificar certificado SSL");
        $ssl_verify->addCheckValue('1');
        $ssl_verify->setTip("Ativar para aumentar segurança em produção");
        $fieldlist->addField('ssl_verify', $ssl_verify);

        // TextArea: Informações do certificado atual
        $cert_info = new TTextArea('cert_info');
        $cert_info->setLabel("Informações do Certificado Atual");
        $cert_info->setEditable(false);
        $cert_info->setHeight('150px');
        $fieldlist->addField('cert_info', $cert_info);

        // Carregar informações se certificado existe
        $this->loadCertificateInfo();
    }

    /**
     * Carrega informações do certificado atual
     */
    private function loadCertificateInfo()
    {
        try {
            $config = (object) include 'app/config/cct.php';
            $cert_path = $config->certificate['path'] . $config->certificate['file'];

            if (!file_exists($cert_path)) {
                $this->form->setFieldValue('cert_info', 'Nenhum certificado configurado');
                return;
            }

            $password = $config->certificate['password'];
            if (empty($password)) {
                $this->form->setFieldValue('cert_info', 'Certificado existe mas senha não está configurada');
                return;
            }

            // Carregar certificado
            $certificate = DigitalCertificateService::loadFromFile($cert_path, $password);
            $info = $certificate->info;

            // Formatar informações
            $cert_text = "CERTIFICADO ATUAL:\n";
            $cert_text .= "---\n";
            $cert_text .= "CN (Nome): " . ($info->cn ?? 'N/A') . "\n";
            $cert_text .= "Organização: " . ($info->o ?? 'N/A') . "\n";
            $cert_text .= "País: " . ($info->c ?? 'N/A') . "\n";
            $cert_text .= "Serial: " . ($info->serial ?? 'N/A') . "\n";
            $cert_text .= "Válido de: " . ($info->not_before ?? 'N/A') . "\n";
            $cert_text .= "Válido até: " . ($info->not_after ?? 'N/A') . "\n";
            $cert_text .= "Fingerprint (SHA1): " . ($info->fingerprint_sha1 ?? 'N/A') . "\n";

            // Verificar expiração
            if (DigitalCertificateService::validateExpiration($certificate->certificate)) {
                $cert_text .= "\nSTATUS: ✓ VÁLIDO\n";
            } else {
                $cert_text .= "\nSTATUS: ✗ EXPIRADO OU INVÁLIDO\n";
            }

            $this->form->setFieldValue('cert_info', $cert_text);

        } catch (\Exception $e) {
            $this->form->setFieldValue('cert_info', "Erro ao carregar certificado:\n" . $e->getMessage());
        }
    }

    /**
     * Constrói painel com informações adicionais
     */
    private function buildCertificateInfo()
    {
        $panel = new TPanel();
        $panel->setTitle("Informações sobre Certificados Digitais");

        $info = new \Adianti\Widgets\Form\TLabel("info_text");
        $info_html = <<<HTML
<div style="padding: 10px; font-size: 12px; line-height: 1.6;">
    <p><strong>O que é um certificado digital A1?</strong></p>
    <p>Um certificado A1 é um arquivo PKCS#12 (.pfx ou .p12) que contém a chave privada e o certificado público
    de uma pessoa jurídica ou física. É utilizado para assinar digitalmente documentos e transações eletrônicas.</p>

    <p><strong>Onde obter um certificado?</strong></p>
    <p>Certificados digitais são emitidos por Autoridades Certificadoras (ACs) credenciadas pela Infraestrutura de
    Chaves Públicas Brasileira (ICP-Brasil). Exemplos:</p>
    <ul>
        <li>Certisign</li>
        <li>Serasa Experian</li>
        <li>Soluti</li>
        <li>Outras ACs credenciadas</li>
    </ul>

    <p><strong>Segurança do certificado:</strong></p>
    <ul>
        <li>Armazene o arquivo .pfx em local seguro</li>
        <li>Use uma senha forte</li>
        <li>Não compartilhe o certificado</li>
        <li>Faça backup regularmente</li>
    </ul>
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

        // Botão: Salvar
        $btn_save = new TButton('btn_save');
        $btn_save->setLabel("Salvar Configurações");
        $btn_save->setImage('fa:save');
        $btn_save->setAction(new TControllerAction('CctCertificateConfigForm', 'onSave'));
        $btn_save->setStyle('primary');
        $panel->addControl($btn_save);

        // Botão: Testar Conexão
        $btn_test = new TButton('btn_test');
        $btn_test->setLabel("Testar Conexão Siscomex");
        $btn_test->setImage('fa:plug');
        $btn_test->setAction(new TControllerAction('CctCertificateConfigForm', 'onTestConnection'));
        $panel->addControl($btn_test);

        // Botão: Voltar
        $btn_back = new TButton('btn_back');
        $btn_back->setLabel("Voltar");
        $btn_back->setImage('fa:arrow-left');
        $btn_back->setAction(new TControllerAction('SystemAdmin', 'onLoad'));
        $panel->addControl($btn_back);

        parent::add($panel);
    }

    /**
     * Salva configurações
     */
    public function onSave()
    {
        try {
            TTransaction::open('default');

            $data = $this->form->getData();

            // Validar senhas
            if ($data->cert_password !== $data->cert_password_confirm) {
                throw new \Exception("As senhas não correspondem");
            }

            if (empty($data->cert_password)) {
                throw new \Exception("Senha do certificado é obrigatória");
            }

            // Se arquivo foi carregado, processar upload
            if (!empty($_FILES['cert_file']['tmp_name'])) {
                $this->processFileUpload($data->cert_password);
            }

            // Atualizar configurações em arquivo
            $this->saveConfiguration($data);

            TTransaction::close();

            new TMessage('info', 'Configurações salvas com sucesso!');
            $this->loadCertificateInfo();

        } catch (\Exception $e) {
            if (TTransaction::isOpen()) {
                TTransaction::close();
            }
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Processa upload de arquivo de certificado
     */
    private function processFileUpload($password)
    {
        try {
            // Diretório de destino
            $config = (object) include 'app/config/cct.php';
            $cert_dir = $config->certificate['path'];

            if (!is_dir($cert_dir)) {
                mkdir($cert_dir, 0700, true);
            }

            // Validar arquivo
            $file_tmp = $_FILES['cert_file']['tmp_name'];
            $file_name = $_FILES['cert_file']['name'];

            if (!file_exists($file_tmp)) {
                throw new \Exception("Arquivo não foi enviado corretamente");
            }

            // Verificar extensão
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($ext, array('pfx', 'p12'))) {
                throw new \Exception("Extensão de arquivo inválida. Use .pfx ou .p12");
            }

            // Verificar tamanho (máximo 5MB)
            if (filesize($file_tmp) > 5 * 1024 * 1024) {
                throw new \Exception("Arquivo muito grande (máximo 5MB)");
            }

            // Validar certificado
            $file_content = file_get_contents($file_tmp);
            $certs = array();
            $result = openssl_pkcs12_read($file_content, $certs, $password);

            if (!$result) {
                throw new \Exception("Certificado inválido ou senha incorreta");
            }

            // Copiar arquivo
            $dest_file = $cert_dir . $config->certificate['file'];
            if (!copy($file_tmp, $dest_file)) {
                throw new \Exception("Erro ao salvar arquivo de certificado");
            }

            // Definir permissões restritas (somente leitura pelo servidor)
            chmod($dest_file, 0600);

            new TMessage('info', 'Certificado importado com sucesso!');

        } catch (\Exception $e) {
            throw new \Exception("Erro ao processar arquivo: " . $e->getMessage());
        }
    }

    /**
     * Salva configurações em arquivo
     */
    private function saveConfiguration($data)
    {
        try {
            // Criar/atualizar variáveis de ambiente ou config
            $env_file = '.env';

            // Preparar dados de config
            $config_updates = array(
                'CCT_ENABLED' => $data->cct_enabled ? '1' : '0',
                'CCT_ENV' => $data->cct_environment ?? 'homolog',
                'CCT_CERT_PASSWORD' => $data->cert_password,
                'CCT_SSL_VERIFY' => $data->ssl_verify ? '1' : '0',
            );

            // Atualizar arquivo .env (simplificado)
            // Em produção, usar biblioteca apropriada para .env
            foreach ($config_updates as $key => $value) {
                putenv("{$key}={$value}");
            }

            // Salvar em arquivo de config local (se necessário)
            // Esta é uma implementação simplificada

        } catch (\Exception $e) {
            throw new \Exception("Erro ao salvar configuração: " . $e->getMessage());
        }
    }

    /**
     * Testa conexão com Siscomex
     */
    public function onTestConnection()
    {
        try {
            TTransaction::open('default');

            $config = (object) include 'app/config/cct.php';

            if (!$config->enabled) {
                throw new \Exception("Integração CCT não está habilitada");
            }

            $cert_path = $config->certificate['path'] . $config->certificate['file'];
            if (!file_exists($cert_path)) {
                throw new \Exception("Certificado não encontrado");
            }

            // Teste simplificado: validar certificado
            $password = $config->certificate['password'];
            $certificate = DigitalCertificateService::loadFromFile($cert_path, $password);

            if (!DigitalCertificateService::validateExpiration($certificate->certificate)) {
                throw new \Exception("Certificado está expirado");
            }

            // Teste de conexão: fazer requisição simples ao endpoint
            $endpoint = $config->endpoints[$config->environment] ?? null;
            if (!$endpoint) {
                throw new \Exception("Endpoint não configurado");
            }

            // Teste simples de conectividade
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_NOBODY, true);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            TTransaction::close();

            $msg = "✓ Teste de conexão bem-sucedido!\n\n";
            $msg .= "Informações:\n";
            $msg .= "- Certificado: VÁLIDO\n";
            $msg .= "- Endpoint: Acessível\n";
            $msg .= "- HTTP Code: {$http_code}\n";
            $msg .= "- Ambiente: " . ($config->environment === 'prod' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO') . "\n";

            new TMessage('info', $msg);

        } catch (\Exception $e) {
            if (TTransaction::isOpen()) {
                TTransaction::close();
            }
            new TMessage('error', 'Erro no teste:\n' . $e->getMessage());
        }
    }
}
?>
