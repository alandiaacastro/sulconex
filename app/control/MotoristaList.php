<?php

class MotoristaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_search_Motorista');
        $this->form->setFormTitle('Motorista');

        $nome = new TEntry('nome');
        $cpf = new TEntry('cpf');

        $nome->setSize('70%');
        $cpf->setMask('999.999.999-99');

        $this->form->addFields([new TLabel('Nome')], [$nome], [new TLabel('CPF')], [$cpf]);

        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search green');
        $this->form->addAction('Novo', new TAction(['MotoristaForm', 'onEdit']), 'fa:plus blue');
        $this->form->addAction('Importar XML', new TAction([$this, 'onImportXML']), 'fa:file-code orange');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';
        $this->datagrid->datatable = 'true';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center'));
        $this->datagrid->addColumn(new TDataGridColumn('nome', 'Nome', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('cpf', 'CPF', 'center'));
        $this->datagrid->addColumn(new TDataGridColumn('cnh_numero', 'CNH', 'center'));
        $this->datagrid->addColumn(new TDataGridColumn('categoria', 'Categoria', 'center'));

        $colTelefone = new TDataGridColumn('telefone', 'Telefone', 'center');
        $colTelefone->setTransformer(function ($value) {
            if (empty($value)) {
                return '<span style="color:#999">-</span>';
            }

            $fone = preg_replace('/\D/', '', (string) $value);
            $foneBR = (strlen($fone) <= 11) ? '55' . $fone : $fone;
            $display = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

            return "<a href='tel:+{$foneBR}' title='Ligar' style='text-decoration:none'>{$display}</a> "
                . "<a href='https://wa.me/{$foneBR}' target='_blank' title='WhatsApp' style='color:#25D366;font-size:1.1rem;margin-left:4px'><i class='fab fa-whatsapp'></i></a>";
        });
        $this->datagrid->addColumn($colTelefone);

        $this->datagrid->addColumn(new TDataGridColumn('data_emissao_cnh', 'Emissao CNH', 'center'))->setTransformer([$this, 'formatDate']);
        $this->datagrid->addColumn(new TDataGridColumn('data_validade_cnh', 'Validade CNH', 'center'))->setTransformer([$this, 'formatDate']);
        $this->datagrid->addColumn(new TDataGridColumn('data_nascimento', 'Nascimento', 'center'))->setTransformer([$this, 'formatDate']);

        $actionEdit = new TDataGridAction(['MotoristaForm', 'onEdit'], ['id' => '{id}']);
        $actionShareAccess = new TDataGridAction([$this, 'onShareAccessConfirm'], ['id' => '{id}']);
        $actionDelete = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);

        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');
        $this->datagrid->addAction($actionShareAccess, 'Enviar Acesso', 'fab:whatsapp green');
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        $panel = new TPanelGroup('Motorista');
        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        parent::add($panel);
    }

    public function formatDate($value)
    {
        if (!empty($value) && $value !== '0000-00-00') {
            try {
                $date = new DateTime((string) $value);
                return $date->format('d/m/Y');
            } catch (Exception $e) {
                return $value;
            }
        }

        return '';
    }

    public static function convertDateToDB($date)
    {
        $date = trim((string) $date);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            if (checkdate((int) $matches[2], (int) $matches[1], (int) $matches[3])) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            }

            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            if (checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                return $date;
            }

            return null;
        }

        return null;
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        TSession::setValue('MotoristaList_filter_nome', !empty($data->nome) ? new TFilter('nome', 'like', "%{$data->nome}%") : null);
        TSession::setValue('MotoristaList_filter_cpf', !empty($data->cpf) ? new TFilter('cpf', 'like', "%{$data->cpf}%") : null);

        $this->form->setData($data);
        $this->onReload();
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            Motorista::ensureTables();

            $repository = new TRepository('Motorista');
            $criteria = new TCriteria;

            if ($filter = TSession::getValue('MotoristaList_filter_nome')) {
                $criteria->add($filter);
            }

            if ($filter = TSession::getValue('MotoristaList_filter_cpf')) {
                $criteria->add($filter);
            }

            $criteria->setProperty('order', 'id desc');
            $criteria->setProperty('limit', 10);

            $objects = $repository->load($criteria, false);

            $this->datagrid->clear();

            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $count = $repository->count($criteria);

            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit(10);

            TTransaction::close();

            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onShareAccessConfirm($param = null)
    {
        $action = new TAction([$this, 'onShareAccess']);
        $action->setParameters($param);

        new TQuestion('Gerar uma nova senha temporaria e preparar a mensagem de acesso para este motorista?', $action);
    }

    public function onShareAccess($param = null)
    {
        try {
            $motoristaId = (int) ($param['id'] ?? 0);
            $shareData = PortalMotoristaAuthService::generateTemporaryAccess($motoristaId);

            new TMessage('info', self::buildShareAccessHtml($shareData));
            TScript::create(self::buildCopyScript());
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function onDelete($param = null)
    {
        $action = new TAction([$this, 'Delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir?', $action);
    }

    public function Delete($param = null)
    {
        try {
            TTransaction::open('sample');

            $object = new Motorista($param['id']);
            $object->delete();

            TTransaction::close();

            $this->onReload();
            new TMessage('info', 'Registro excluido com sucesso');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onImportXML($param = null)
    {
        try {
            $modeloXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<motoristas>
    <motorista>
        <cnh_numero>9876543210</cnh_numero>
        <data_emissao_cnh>2020-01-01</data_emissao_cnh>
        <data_validade_cnh>2030-01-01</data_validade_cnh>
        <categoria>B</categoria>
        <registro_num>123456</registro_num>
        <nome>Joao Silva</nome>
        <data_nascimento>1985-05-20</data_nascimento>
        <local_nascimento>Sao Paulo</local_nascimento>
        <cpf>123.456.789-00</cpf>
        <rg_numero>12345678</rg_numero>
        <rg_emissor>SSP</rg_emissor>
        <rg_uf>SP</rg_uf>
        <filiacao_pai>Jose Silva</filiacao_pai>
        <filiacao_mae>Maria Silva</filiacao_mae>
    </motorista>
</motoristas>
XML;

            $form = new BootstrapFormBuilder('form_import_motorista');
            $form->setFormTitle('Importar Motoristas via XML');

            $xmlText = new TText('xml_text');
            $xmlText->setSize('100%', 200);
            $xmlText->setValue($modeloXml);

            $form->addFields([new TLabel('Cole ou edite o XML abaixo')], [$xmlText]);

            $form->addAction('Importar', new TAction([$this, 'processImportXML']), 'fa:upload green');
            $form->addAction('Fechar', new TAction([$this, 'onReload']), 'fa:times red');

            $window = TWindow::create('Importacao de Motoristas', 600, 400);
            $window->add($form);
            $window->show();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function processImportXML($param = null)
    {
        try {
            TTransaction::open('sample');

            if (!empty($param['xml_text'])) {
                $xmlString = $param['xml_text'];

                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($xmlString);

                if ($xml === false) {
                    $errors = libxml_get_errors();
                    $errorMessage = 'Erro no XML:<br>';
                    foreach ($errors as $error) {
                        $errorMessage .= htmlspecialchars((string) $error->message, ENT_QUOTES, 'UTF-8') . '<br>';
                    }
                    throw new Exception($errorMessage);
                }

                $count = 0;
                foreach ($xml->motorista as $item) {
                    $motorista = new Motorista;
                    $motorista->cnh_numero = (string) $item->cnh_numero;
                    $motorista->data_emissao_cnh = self::convertDateToDB((string) $item->data_emissao_cnh);
                    $motorista->data_validade_cnh = self::convertDateToDB((string) $item->data_validade_cnh);
                    $motorista->categoria = (string) $item->categoria;
                    $motorista->registro_num = (string) $item->registro_num;
                    $motorista->nome = (string) $item->nome;
                    $motorista->data_nascimento = self::convertDateToDB((string) $item->data_nascimento);
                    $motorista->local_nascimento = (string) $item->local_nascimento;
                    $motorista->cpf = (string) $item->cpf;
                    $motorista->rg_numero = (string) $item->rg_numero;
                    $motorista->rg_emissor = (string) $item->rg_emissor;
                    $motorista->rg_uf = (string) $item->rg_uf;
                    $motorista->filiacao_pai = (string) $item->filiacao_pai;
                    $motorista->filiacao_mae = (string) $item->filiacao_mae;

                    if (!$motorista->data_emissao_cnh || !$motorista->data_validade_cnh || !$motorista->data_nascimento) {
                        throw new Exception('Data invalida no registro de ' . $motorista->nome);
                    }

                    $motorista->store();
                    $count++;
                }

                new TMessage('info', "Importacao concluida com sucesso! {$count} registros importados.");
            } else {
                throw new Exception('O campo XML esta vazio!');
            }

            TTransaction::close();
            $this->onReload();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }

        parent::show();
    }

    private static function buildShareAccessHtml(array $shareData): string
    {
        $driverName = htmlspecialchars((string) ($shareData['driver']['nome'] ?? 'Motorista'), ENT_QUOTES, 'UTF-8');
        $portalUrl = htmlspecialchars((string) ($shareData['portal_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars((string) ($shareData['driver']['telefone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $temporaryPassword = htmlspecialchars((string) ($shareData['temporary_password'] ?? ''), ENT_QUOTES, 'UTF-8');
        $messageId = 'motorista_access_message_' . md5($driverName . $temporaryPassword . microtime(true));
        $message = htmlspecialchars((string) ($shareData['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        $whatsAppUrl = (string) ($shareData['whatsapp_url'] ?? '');

        $whatsAppButton = $whatsAppUrl !== ''
            ? "<a href='" . htmlspecialchars($whatsAppUrl, ENT_QUOTES, 'UTF-8') . "' target='_blank' style='display:inline-block;background:#25D366;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:bold'><i class='fab fa-whatsapp'></i> Abrir WhatsApp</a>"
            : "<span style='display:inline-block;color:#92400E;background:#FEF3C7;padding:10px 14px;border-radius:6px'>Motorista sem telefone para abrir o WhatsApp automaticamente.</span>";

        return "
            <div style='text-align:left;max-width:680px'>
                <p style='margin:0 0 12px'>Senha temporaria gerada para <strong>{$driverName}</strong>.</p>
                <div style='background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;margin-bottom:12px'>
                    <div><strong>Portal:</strong> <a href='{$portalUrl}' target='_blank'>{$portalUrl}</a></div>
                    <div style='margin-top:6px'><strong>Telefone de login:</strong> {$phone}</div>
                    <div style='margin-top:6px'><strong>Senha temporaria:</strong> <code style='font-size:15px'>{$temporaryPassword}</code></div>
                </div>
                <div style='font-weight:bold;margin-bottom:6px'>Mensagem pronta para enviar</div>
                <textarea id='{$messageId}' readonly style='width:100%;min-height:180px;border:1px solid #CBD5E1;border-radius:8px;padding:12px;box-sizing:border-box;background:#fff'>{$message}</textarea>
                <div style='margin-top:12px;display:flex;gap:8px;flex-wrap:wrap'>
                    <button type='button' onclick='pmCopyTextarea(&quot;{$messageId}&quot;); return false;' style='background:#0F172A;color:#fff;border:0;padding:10px 16px;border-radius:6px;cursor:pointer;font-weight:bold'>Copiar mensagem</button>
                    {$whatsAppButton}
                </div>
                <p style='margin:12px 0 0;color:#64748B;font-size:12px'>Cada novo envio por este atalho redefine a senha temporaria anterior. No primeiro acesso, o motorista sera levado para cadastrar a senha definitiva.</p>
            </div>
        ";
    }

    private static function buildCopyScript(): string
    {
        return <<<'JS'
(function () {
    if (window.pmCopyTextarea) {
        return;
    }

    window.pmCopyTextarea = function (textareaId) {
        var textarea = document.getElementById(textareaId);
        if (!textarea) {
            return false;
        }

        var text = textarea.value || textarea.textContent || '';
        var notify = function () {
            if (window.Swal && Swal.fire) {
                Swal.fire({
                    icon: 'success',
                    title: 'Copiado!',
                    text: 'Mensagem copiada para a area de transferencia.',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        };

        var fallbackCopy = function () {
            textarea.focus();
            textarea.select();
            document.execCommand('copy');
            notify();
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(notify).catch(fallbackCopy);
            return false;
        }

        fallbackCopy();
        return false;
    };
})();
JS;
    }
}

?>



