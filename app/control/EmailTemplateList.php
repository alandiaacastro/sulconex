<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\TPanelGroup; // Usar TPanelGroup para consistência e modernidade
use Adianti\Widget\Container\TTable;      // Adicionado para o formulário de busca
use Adianti\Registry\TSession;
// use Adianti\Core\AdiantiCoreTranslator; // Não está sendo usado diretamente

class EmailTemplateList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;
    
    private $database = 'sample'; // Certifique-se que este é o nome correto da conexão em app/config/
    private $activeRecord = 'EmailTemplate'; // Certifique-se que esta classe Model existe em app/model/
    private $defaultOrder = 'id';
    private $defaultDirection = 'asc';
    private $limit = 10;

    public function __construct()
    {
        parent::__construct();

        // Formulário de busca
        $this->form = new TForm('form_search_EmailTemplate');
        $table_search = new TTable; // Usando TTable para o formulário de busca
        $this->form->add($table_search);
        
        $row_search = $table_search->addRow();
        $row_search->addCell(new TLabel('Título:'));
        $title_filter = new TEntry('title_filter');
        $row_search->addCell($title_filter);

        // Botão de busca
        $button_search = new TButton('search_button'); // Nome diferente para o botão
        $button_search->setLabel('Buscar');
        $button_search->setAction(new TAction([$this, 'onSearch']), 'Buscar');
        $this->form->addField($title_filter);
        $this->form->addField($button_search);
        
        $row_button_search = $table_search->addRow();
        $cell_button_search = $row_button_search->addCell($button_search);
        $cell_button_search->colspan = 2;

        // Datagrid
        $this->datagrid = new TDataGrid;
        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '10%'));
        $this->datagrid->addColumn(new TDataGridColumn('title', 'Título', 'left', '30%'));
        $this->datagrid->addColumn(new TDataGridColumn('subject', 'Assunto', 'left', '40%'));

        // Ação de Editar
        $action_edit = new TDataGridAction(['EmailTemplateForm', 'onEdit'], ['key' => '{id}']);
        $action_edit->setLabel('Editar');
        $action_edit->setImage('fa:edit blue'); // Sugestão: adicionar ícone
        $this->datagrid->addAction($action_edit); // CORRIGIDO

        // Ação de Excluir
        $action_delete = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);
        $action_delete->setLabel('Excluir');
        $action_delete->setImage('fa:trash red'); // Sugestão: adicionar ícone
        $this->datagrid->addAction($action_delete); // CORRIGIDO

        // Ação de Testar E-mail
        $action_send = new TDataGridAction([$this, 'onSendEmail'], ['id' => '{id}']);
        $action_send->setLabel('Testar E-mail');
        $action_send->setImage('fa:envelope green'); // Sugestão: adicionar ícone
        $this->datagrid->addAction($action_send); // CORRIGIDO

        $this->datagrid->createModel();

        // Navegação
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setLimit($this->limit); // Corrigido

        // Layout
        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($this->form);
        
        $panel_datagrid = new TPanelGroup('Modelos de E-mail');
        $panel_datagrid->add($this->datagrid);
        $panel_datagrid->addFooter($this->pageNavigation);
        $vbox->add($panel_datagrid);

        parent::add($vbox);
        $this->loaded = false;
    }

    public function onSearch($param)
    {
        $data = $this->form->getData();
        TSession::setValue('EmailTemplateList_filter_title', $data->title_filter ?? '');
        $this->form->setData($data); // Mantém o filtro visível no formulário
        $this->onReload(['offset' => 0, 'page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);
            $repository = new TRepository($this->activeRecord);
            $criteria = new TCriteria;

            $filter_title = TSession::getValue('EmailTemplateList_filter_title');
            if ($filter_title) {
                $criteria->add(new TFilter('title', 'like', "%{$filter_title}%"));
            }

            $page = isset($param['page']) && is_numeric($param['page']) ? (int) $param['page'] : 1;
            $offset = ($page - 1) * $this->limit;
            
            // Define a ordem padr�o se n�o houver uma nos par�metros
            $order = $param['order'] ?? $this->defaultOrder;
            $direction = $param['direction'] ?? $this->defaultDirection;

            $criteria->setProperties([
                'limit'     => $this->limit,
                'offset'    => $offset,
                'order'     => $order,
                'direction' => $direction
            ]);

            $objects = $repository->load($criteria);
            $this->datagrid->clear();

            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $count = $repository->count($criteria);

            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setPage($page);
            $this->pageNavigation->setProperties($param); // Para manter a ordenação na paginação

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao recarregar: ' . $e->getMessage());
        }
    }

    public function onDelete($param)
    {
        $action = new TAction([$this, 'deleteConfirmed']);
        $action->setParameters($param); // Passa o ID e outros par�metros para a confirma��o
        new TQuestion('Deseja excluir este modelo?', $action);
    }

    public function deleteConfirmed($param)
    {
        try {
            TTransaction::open($this->database);
            $object = new $this->activeRecord($param['id']); // Acessa 'id' do parÃ¢metro
            $object->delete();
            TTransaction::close();
            $this->onReload();
            new TMessage('info', 'Modelo excluído com sucesso.');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', "Erro ao excluir: " . $e->getMessage());
        }
    }

    public function onSendEmail($param)
    {
        try {
            TTransaction::open($this->database);
            $template = new $this->activeRecord($param['id']);

            if (!$template) {
                throw new Exception('Modelo de e-mail não encontrado.');
            }

            $subject = $template->subject ?? '';
            $body = $template->body ?? '';

            $variables = [];
            if (!empty($template->variables_json)) {
                $decoded_variables = json_decode((string) $template->variables_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_variables)) {
                    $variables = $decoded_variables;
                }
            }
            
            foreach ($variables as $key => $label) {
                $value_placeholder = '[Ex: ' . ($label ?: $key) . ']'; // Usa a chave se o label estiver vazio
                $subject = str_replace("{{{$key}}}", $value_placeholder, $subject);
                $body    = str_replace("{{{$key}}}", $value_placeholder, $body);
            }
            
            // Exemplo de variáveis padrão que podem não estar no JSON
            $defaults = ['contact_id' => '123', 'status' => 'Aberta', 'closing_date' => date('d/m/Y')];
            foreach ($defaults as $key => $val) {
                 if (strpos($subject, "{{{$key}}}") !== false) {
                    $subject = str_replace("{{{$key}}}", $val, $subject);
                 }
                 if (strpos($body, "{{{$key}}}") !== false) {
                    $body    = str_replace("{{{$key}}}", $val, $body);
                 }
            }
            
            // Remover quaisquer placeholders não substituídos
            $subject = preg_replace('/\{\{\{.*?\}\}\}/', '', $subject);
            $body = preg_replace('/\{\{\{.*?\}\}\}/', '', $body);

            $mailto = 'mailto:?subject=' . rawurlencode($subject) . '&body=' . rawurlencode(strip_tags($body));
            TScript::create("window.open('{$mailto}', '_blank');");

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao preparar e-mail: ' . $e->getMessage());
        }
    }

    public function show()
    {
        if (!$this->loaded) {
             // Verifica se h� par�metros espec�ficos de reload (como ordena��o ou p�gina)
            $isReloadSpecific = false;
            if (!empty($_GET)) {
                foreach ($_GET as $key => $value) {
                    if ($key !== 'class' && $key !== 'method') { // Ignora par�metros padr�o de rota
                        $isReloadSpecific = true;
                        break;
                    }
                }
            }

            if ($isReloadSpecific) {
                $this->onReload($_GET);
            } else {
                $this->onReload(); // Primeira carga ou carga sem par�metros espec�ficos
            }
        }
        parent::show();
    }
}