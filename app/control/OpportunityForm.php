<?php

class OpportunityForm extends TPage
{
    protected $form; // Formulário principal

    public function __construct()
    {
        parent::__construct();

        // Criação do formulário com layout Bootstrap
        $this->form = new BootstrapFormBuilder('form_Opportunity');
        $this->form->setFormTitle('Cadastro de Oportunidade');
        $this->form->setProperty('style', 'width: 100%'); // Ocupa toda a largura

        // Definição dos campos do formulário
        $id               = new TEntry('id');
        $company_name     = new TEntry('company_name');
        $status           = new TCombo('status');
        $responsible_name = new TEntry('responsible_name');
        $phone            = new TEntry('phone');
        $email            = new TEntry('email');
        $position         = new TEntry('position');
        $notes            = new TText('notes');
        $closing_date     = new TDate('closing_date');

        // Opções para o campo 'status'
        $status->addItems([
            'QUALIFICACAO' => 'Qualificação',
            'PROPOSTA'     => 'Proposta',
            'NEGOCIACAO'   => 'Negociação',
            'FECHAMENTO'   => 'Fechamento'
        ]);
        $status->setEditable(false); // Impede edição manual, status é atualizado por lógica ou Kanban
        $status->setSize('100%');
        $status->setDefaultOption('Selecione'); // Opção padrão para combo boxes

        // Configuração e validação dos campos
        $id->setEditable(false); // ID não pode ser editado
        $id->setSize('100%');

        // Define o tamanho para 100% e máscara para data
        $company_name->setSize('100%');
        $responsible_name->setSize('100%');
        $phone->setSize('100%');
        $email->setSize('100%');
        $position->setSize('100%');
        $notes->setSize('100%');
        $closing_date->setSize('100%');
        $closing_date->setMask('dd/mm/yyyy'); // Máscara para entrada de data

        // Adiciona validações (campos obrigatórios e formato de e-mail)
        $company_name->addValidation('Empresa', new TRequiredValidator);
        $status->addValidation('Status', new TRequiredValidator);
        $responsible_name->addValidation('Responsável', new TRequiredValidator);
        $email->addValidation('E-mail', new TEmailValidator); // Valida formato de e-mail

        // Organização dos campos no formulário (linhas e colunas)
        // Cada chamada a addFields cria uma nova linha no formulário
        $this->form->addFields([new TLabel('ID')],                 [$id],
                               [new TLabel('Empresa <font color="red">*</font>')], [$company_name]);

        $this->form->addFields([new TLabel('Status <font color="red">*</font>')], [$status],
                               [new TLabel('Responsável <font color="red">*</font>')], [$responsible_name]);

        $this->form->addFields([new TLabel('Telefone')],           [$phone],
                               [new TLabel('E-mail')],             [$email]);

        $this->form->addFields([new TLabel('Cargo')],              [$position],
                               [new TLabel('Data Fechamento')],    [$closing_date]);

        // O campo 'Notas' ocupa uma linha inteira
        $this->form->addFields([new TLabel('Notas')],              [$notes]);

        // Ações do formulário (botões)
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        // Ao voltar, recarrega o Kanban
        $this->form->addAction('Voltar', new TAction(['OpportunityKanban', 'onReload']), 'fa:arrow-left blue');

        // Cria o container para o formulário
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);

        // Adiciona o container à página
        parent::add($container);
    }

    /**
     * Salva o registro da oportunidade no banco de dados.
     */
    public function onSave($param = null)
    {
        try {
            TTransaction::open('sample'); // Inicia a transação com o banco de dados

            $data = $this->form->getData(); // Obtém os dados do formulário
            $this->form->validate();        // Executa as validações definidas nos campos

            $object = new Opportunity;      // Cria uma nova instÃ¢ncia do Model Opportunity
            $object->fromArray((array) $data); // Preenche o objeto com os dados do formulário

            // Regra de negócio: se a data de fechamento for preenchida, o status vai para "FECHAMENTO"
            if (!empty($object->closing_date)) {
                $object->status = 'FECHAMENTO';
            }

            $object->store(); // Salva o objeto (insere ou atualiza) no banco de dados

            $this->form->setData($object); // Atualiza o formulário com os dados do objeto salvo (ex: ID gerado)

            TTransaction::close(); // Fecha a transação

            // Exibe mensagem de sucesso e redireciona para o Kanban
            new TMessage('info', 'Registro salvo com sucesso!', new TAction(['OpportunityKanban', 'onReload']));

        } catch (Exception $e) {
            TTransaction::rollback(); // Em caso de erro, desfaz a transação
            new TMessage('error', 'Erro ao salvar: ' . $e->getMessage()); // Exibe a mensagem de erro
        }
    }

    /**
     * Carrega um registro para edição ou prepara o formulário para um novo registro.
     * @param $param Par�metros (pode conter 'id' para edi��o).
     */
    public function onEdit($param = null)
    {
        try {
            $id = $param['key'] ?? $param['id'] ?? null; // Tenta obter o ID do registro

            if ($id) { // Se um ID for passado, carrega o registro para edição
                TTransaction::open('sample');
                $object = new Opportunity($id); // Carrega a oportunidade pelo ID
                $this->form->setData($object);  // Preenche o formulário com os dados carregados
                TTransaction::close();
            } else { // Se nenhum ID for passado, prepara o formulário para um novo registro
                $this->form->clear(); // Limpa todos os campos do formulário

                // Define um valor padrão inicial para o status para novos registros
                $obj = new StdClass;
                $obj->status = 'QUALIFICACAO';
                $this->form->setData($obj);
            }
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao carregar registro: ' . $e->getMessage());
        }
    }
}
?>