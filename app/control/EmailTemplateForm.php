<?php

use Adianti\Control\TPage;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Validator\TRequiredValidator;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;
// Se você usar TXMLBreadCrumb em algum momento ou outra classe, adicione o 'use' correspondente.

class EmailTemplateForm extends TPage
{
    // Não estamos mais usando AdiantiStandardFormTrait para onSave, onEdit, onClear,
    // pois eles serão implementados manualmente abaixo.
    // No entanto, o trait pode fornecer outros métodos úteis. Se você precisar deles,
    // pode mantê-lo e sobrescrever apenas os métodos desejados.
    // Para este exemplo, vamos supor que queremos controle total sobre estes três.
    // use Adianti\Base\AdiantiStandardFormTrait; 

    protected $form;
    private $database = 'sample';       // Nome da conexão com o banco de dados (deve existir em app/config/)
    private $activeRecord = 'EmailTemplate'; // Nome da classe Model (ActiveRecord)

    public function __construct($param = null)
    {
        parent::__construct();

        // Se você decidir não usar o AdiantiStandardFormTrait de todo,
        // as chamadas $this->setDatabase() e $this->setActiveRecord() não estarão disponíveis
        // a menos que você as implemente ou o trait seja mantido para outros métodos.
        // Para os métodos manuais abaixo, essas propriedades de classe ($this->database, $this->activeRecord) serão usadas.
        // $this->setDatabase('sample'); 
        // $this->setActiveRecord('EmailTemplate');

        $this->form = new BootstrapFormBuilder('form_EmailTemplate');
        $this->form->setFormTitle('Cadastro de Modelo de E-mail');
        $this->form->setClientValidation(true);

        // Campos
        $id             = new TEntry('id');
        $title          = new TEntry('title');
        $subject        = new TEntry('subject');
        $body           = new TText('body'); 
        $variables_json = new TText('variables_json');

        // Configurações dos campos
        $id->setEditable(false);
        $id->setSize('100%');
        $title->setSize('100%');
        $subject->setSize('100%');
        $body->setSize('100%', 200); // THtmlEditor é mais recomendado para corpo de e-mail
        $variables_json->setSize('100%', 100);
        $variables_json->setProperty('placeholder', 'Ex: {"nome_cliente": "Nome do Cliente", "numero_pedido": "Número do Pedido"}');

        // Validações
        $title->addValidation('Título', new TRequiredValidator);
        $subject->addValidation('Assunto', new TRequiredValidator);
        // Você pode adicionar uma validação customizada para $variables_json para checar se é um JSON válido

        // Adiciona os campos ao formulário
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Título (*)', '#FF0000')], [$title]);
        $this->form->addFields([new TLabel('Assunto (*)', '#FF0000')], [$subject]);
        $this->form->addFields([new TLabel('Corpo do E-mail')], [$body]);
        $this->form->addFields([new TLabel('Variáveis (JSON)')], [$variables_json]);

        // Dica com variáveis de referência
        $available_vars = [
            'contact_id', 'status', 'company_name', 'phone',
            'responsible_name', 'position', 'email', 'notes', 'closing_date',
            'nome_cliente', 'numero_pedido', 'data_vencimento' // Adicione outras conforme sua necessidade
        ];

        $hint_html = '<div style="font-size: 0.9em; color: #777; margin-top: 8px; padding: 10px; background-color: #f9f9f9; border: 1px solid #eee; border-radius: 4px;">';
        $hint_html .= '<strong><i class="fa fa-info-circle"></i> Variáveis de Referência Disponíveis:</strong><br>';
        $hint_html .= 'Utilize as variáveis abaixo no corpo e assunto do e-mail, envolvendo-as com chaves triplas, ex: <code style=\'background:#e0e0e0;padding:2px 4px;margin:2px;display:inline-block;border-radius:3px;\'>{{{nome_variavel}}}</code><br><br>';
        foreach ($available_vars as $v) {
            $hint_html .= "<code style='background:#e0e0e0;padding:2px 4px;margin:2px;display:inline-block;border-radius:3px;'>{{{$v}}}</code> ";
        }
        $hint_html .= '</div>';
        
        $hint_element = new \Adianti\Widget\Base\TElement('div');
        $hint_element->style = 'margin-top: 10px;'; 
        $hint_element->add($hint_html);
        $this->form->addContent([$hint_element]);

        // Ações do formulário
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Voltar para Listagem', new TAction(['EmailTemplateList', 'onReload']), 'fa:table blue');

        // Layout final
        $vbox = new TVBox;
        $vbox->style = 'width: 100%';

        // Se você não quer o breadcrumb, esta seção pode ser removida.
        // if (is_file('menu.xml') && class_exists('Adianti\Widget\Util\TXMLBreadCrumb')) {
        //     $vbox->add(new \Adianti\Widget\Util\TXMLBreadCrumb('menu.xml', __CLASS__));
        // }

        $panel = TPanelGroup::pack($this->form->getFormTitle(), $this->form);
        $vbox->add($panel);
        
        parent::add($vbox);

        // Se o formulário é chamado para edição, o método onEdit será invocado pela rota/ação.
        // Não é necessário chamar onEdit explicitamente aqui no construtor,
        // a menos que haja um parÃ¢metro 'key' ou 'id' na URL inicial que o construtor precise processar.
        // O AdiantiStandardFormTrait normalmente lida com isso. Se você o removeu completamente,
        // e a ação de edição da datagrid aponta para EmailTemplateForm->onEdit,
        // o Adianti irá chamar onEdit($param) automaticamente quando essa ação for disparada.
        if (isset($param['key'])) { // Ou $param['id'], dependendo de como a ação de edição é definida na datagrid
            $this->onEdit($param);
        }
    }

    /**
     * Método para salvar os dados do formulário
     * Esta é uma implementação manual, similar ao que o AdiantiStandardFormTrait faria.
     */
    public function onSave($param = null)
    {
        try {
            TTransaction::open($this->database); // Abre transação

            $this->form->validate(); // Valida o formulário
            $data = $this->form->getData(); // Obtém os dados do formulário

            $object = new $this->activeRecord; // Cria uma instÃ¢ncia do ActiveRecord
            $object->fromArray( (array) $data); // Preenche o objeto com os dados do formulário
            
            // Validação customizada para o JSON (exemplo)
            if (!empty($data->variables_json)) {
                json_decode($data->variables_json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('O campo Variáveis (JSON) não contém um JSON válido.');
                }
            }

            $object->store(); // Salva o objeto no banco de dados

            $data->id = $object->id; // Atualiza o ID no objeto de dados
            $this->form->setData($data); // Repopula o formulário com os dados salvos (incluindo o ID)

            TTransaction::close(); // Fecha a transação

            new TMessage('info', 'Registro salvo com sucesso!');
            
            // Opcional: Redirecionar para a listagem ou outra ação
            // TApplication::loadPage('EmailTemplateList', 'onReload');

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage()); // Exibe a mensagem de erro
            $this->form->setData($this->form->getData()); // Mantém os dados no formulário
            TTransaction::rollback(); // Desfaz as operações da transação
        }
    }

    /**
     * Método para carregar os dados de um registro para edição
     * @param $param Array de parÃ¢metros, geralmente contendo 'key' ou 'id' do registro
     * Esta é uma implementação manual, similar ao que o AdiantiStandardFormTrait faria.
     */
    public function onEdit($param)
    {
        try {
            if (isset($param['key'])) { // 'key' � o par�metro padr�o do AdiantiStandardFormTrait
                $key = $param['key'];
                
                TTransaction::open($this->database); // Abre transação
                
                $object = new $this->activeRecord($key); // Instancia o ActiveRecord e carrega o registro
                if (!$object) {
                    throw new Exception('Registro não encontrado.');
                }
                $this->form->setData($object); // Preenche o formulário com os dados do objeto
                
                TTransaction::close(); // Fecha a transação
            } else {
                $this->form->clear(true);
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Método para limpar o formulário
     * @param $param Par�metros (geralmente n�o utilizado para limpar)
     * Esta é uma implementação manual, similar ao que o AdiantiStandardFormTrait faria.
     */
    public function onClear($param = null)
    {
        $this->form->clear(true); // O argumento 'true' mantém os valores padrão, se houver
    }
}