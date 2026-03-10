<?php

class ConhecimentoSeekList extends TWindow
{
    protected $form;
    protected $datagrid;

    public function __construct($param)
    {
        // Chama o construtor da classe pai (TWindow)
        parent::__construct();
        // Define o tamanho da janela (largura e altura em porcentagem da tela)
        parent::setSize(0.8, 0.8);
        // Define o título da janela
        parent::setTitle('Buscar Conhecimento');

        // Cria o formulário de busca
        $this->form = new BootstrapFormBuilder('form_search_conhecimento');
        // Cria um campo de entrada de texto para o número do CRT
        $numero = new TEntry('numero');
        // Adiciona um rótulo e o campo ao formulário
        $this->form->addFields([new TLabel('Nº CRT:')], [$numero]);
        // Adiciona uma ação (botão) para buscar os conhecimentos
        // 'Buscar' é o texto do botão
        // new TAction([$this, 'onSearch']) define o método que será chamado ao clicar
        // 'fa:search' é o ícone Font Awesome para o botão
        // class = 'btn btn-sm btn-primary' define o estilo CSS do botão
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search')->class = 'btn btn-sm btn-primary';

        // Cria a datagrid (grade de dados) para exibir os resultados da busca
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        // Define o estilo da datagrid para ocupar 100% da largura disponível
        $this->datagrid->style = 'width: 100%';

        // Adiciona as colunas à datagrid
        // TDataGridColumn('nome_do_campo', 'Título da Coluna', 'alinhamento', 'largura')
        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '10%'));
        $this->datagrid->addColumn(new TDataGridColumn('numero', 'Nº CRT', 'left', '30%'));
        $this->datagrid->addColumn(new TDataGridColumn('nome_remetente', 'Remetente', 'left', '60%'));

        // Cria a ação para o botão "Selecionar" na datagrid
        // Quando este botão for clicado, o método estático 'onSelect' desta classe será chamado
        $action_select = new TDataGridAction([__CLASS__, 'onSelect']);
        // Define qual campo do objeto será usado como chave para a ação (neste caso, o ID do conhecimento)
        $action_select->setField('id');

        // Repassa todos os par�metros recebidos pelo construtor da tela de origem para a a��o 'onSelect'.
        // Isso é fundamental para que o método 'onSelect' saiba para qual formulário e campos os dados devem ser enviados.
        // Os par�metros comuns incluem: form_name (nome do formul�rio de destino), receive_key (campo de ID de destino),
        // receive_display (campo de exibição de destino), entre outros.
        foreach ($param as $key => $value) {
            $action_select->setParameter($key, $value);
        }

        // Adiciona a ação "Selecionar" à datagrid, com seu ícone e rótulo
        // 'fa:check-circle green' é o ícone Font Awesome com a cor verde
        // 'Selecionar' é o texto que aparecerá para a ação
        $this->datagrid->addAction($action_select, 'fa:check-circle green', 'Selecionar');

        // Cria o modelo da datagrid (renderiza as colunas e ações definidas)
        $this->datagrid->createModel();

        // Cria um painel para envolver a datagrid
        $panel = new TPanelGroup;
        // Adiciona a datagrid ao corpo do painel
        $panel->add($this->datagrid);
        // Define um estilo para o corpo do painel, permitindo rolagem horizontal se o conteúdo for muito largo
        $panel->getBody()->style = "overflow-x:auto;";

        // Cria um contêiner vertical (TVBox) para organizar o formulário e o painel
        $container = new TVBox;
        // Define o estilo do contêiner para ocupar 100% da largura
        $container->style = 'width: 100%';
        // Adiciona o formulário de busca ao contêiner
        $container->add($this->form);
        // Adiciona o painel (que contém a datagrid) ao contêiner
        $container->add($panel);

        // Adiciona o contêiner (com o formulário e a datagrid) à janela principal (TWindow)
        parent::add($container);
    }

    /**
     * Método responsável por realizar a busca de conhecimentos e popular a datagrid.
     * Este método é chamado ao clicar no botão "Buscar" do formulário.
     * @param $param Par�metros da requisi��o, que podem incluir o crit�rio de busca.
     */
    public function onSearch($param = null)
    {
        try {
            // Abre uma transação com o banco de dados 'sample'
            TTransaction::open('sample');

            // Obtém os dados do formulário de busca
            $data = $this->form->getData();
            // Cria um objeto de critérios para a busca no banco de dados
            $criteria = new TCriteria;

            // Verifica se o campo 'numero' do formulário não está vazio
            if (!empty($data->numero)) {
                // Adiciona um filtro aos critérios: buscar conhecimentos onde o campo 'numero'
                // seja semelhante (LIKE) ao valor digitado, com curingas (%)
                $criteria->add(new TFilter('numero', 'like', "%{$data->numero}%"));
            }

            // Cria um repositório para a entidade 'Conhecimento'
            $repository = new TRepository('Conhecimento');
            // Carrega os objetos (registros) do banco de dados de acordo com os critérios definidos
            $objects = $repository->load($criteria);

            // Limpa todos os itens atualmente exibidos na datagrid
            $this->datagrid->clear();

            // Verifica se foram encontrados objetos (registros)
            if ($objects) {
                // Itera sobre cada objeto encontrado
                foreach ($objects as $object) {
                    // Adiciona o objeto como um item à datagrid para exibição
                    $this->datagrid->addItem($object);
                }
            }

            // Define os dados do formulário novamente, para que os campos mantenham os valores digitados após a busca
            $this->form->setData($data);

            // Fecha a transação com o banco de dados
            TTransaction::close();
        } catch (Exception $e) {
            // Em caso de erro, exibe uma mensagem de erro
            new TMessage('error', $e->getMessage());
            // Desfaz todas as operações da transação no banco de dados
            TTransaction::rollback();
        }
    }

    /**
     * Método estático responsável por lidar com a seleção de um item na datagrid.
     * Este método é chamado quando o botão "Selecionar" de um registro é clicado.
     * @param $param Par�metros da requisi��o, incluindo a chave (ID) do registro selecionado
     * e informações sobre a tela de origem.
     */
    public static function onSelect($param)
    {
        try {
            // Abre uma transação com o banco de dados 'sample'
            TTransaction::open('sample');

            // Obtém a chave (ID) do registro clicado, que foi configurada com setField('id') na TDataGridAction
            $key = $param['key'];
            // Obtém o nome do formulário de origem (por exemplo, 'form_FaturaForm')
            $form_name = $param['form_name'];
            // Obtém o nome do campo na tela de origem que receberá o ID do conhecimento (ex: 'conhecimento_id')
            $receive_key = $param['receive_key'];
            // Obtém o nome do campo na tela de origem que receberá o valor de exibição do conhecimento (ex: 'conhecimento_display')
            $receive_display = $param['receive_display'];

            // Carrega o objeto Conhecimento do banco de dados usando a chave (ID)
            $conhecimento = new Conhecimento($key);

            // Cria um objeto genérico (stdClass) para armazenar os dados a serem enviados de volta para a tela de origem
            $data = new stdClass;
            // Define o valor do campo de ID na tela de origem com o ID do conhecimento selecionado
            $data->{$receive_key} = $conhecimento->id;
            // Define o valor do campo de exibição na tela de origem com o número do conhecimento selecionado
            $data->{$receive_display} = $conhecimento->numero;
            // Se houver outros campos na tela de origem que precisam ser preenchidos com base no conhecimento,
            // adicione-os aqui, por exemplo:
            // $data->cliente_nome = $conhecimento->cliente->nome;
            // $data->emissao = $conhecimento->data_emissao;
            // $data->prazo = $conhecimento->prazo_pagamento;
            // $data->vencimento = $conhecimento->data_vencimento;
            // $data->valor_fatura = $conhecimento->valor_total;
            // $data->valor_extenso = $conhecimento->valor_extenso;

            // Envia os dados para o formulário de origem
            // O terceiro parÃ¢metro (true) indica que os campos devem ser atualizados
            // O quarto par�metro (true) indica que os dados devem ser limpos ap�s o envio (opcional, pode ser false)
            TForm::sendData($form_name, $data, true, true);

            // Dispara um método remoto via AJAX na tela FaturaForm.
            // Isso é útil se a tela FaturaForm precisar carregar outros dados relacionados (como dados do cliente)
            // ou executar alguma lógica específica (cálculos, validações) com base no 'conhecimento_id' recém-preenchido.
            TScript::create("__adianti_load_page('engine.php?class=FaturaForm&method=onReceiveConhecimento&static=1&key={$conhecimento->id}');");

            // Fecha a janela de busca (TWindow) após a seleção e o envio dos dados
            TWindow::closeWindow();

            // Fecha a transação com o banco de dados
            TTransaction::close();
        } catch (Exception $e) {
            // Em caso de erro, exibe uma mensagem de erro
            new TMessage('error', $e->getMessage());
            // Desfaz todas as operações da transação no banco de dados
            TTransaction::rollback();
        }
    }

    /**
     * Método responsável por recarregar o conteúdo da janela de busca.
     * Este método é chamado automaticamente pelo Adianti Framework quando a janela é exibida ou precisa ser atualizada.
     * Ã‰ CRUCIAL que este mÃ©todo NÃƒO crie uma nova instÃ¢ncia da prÃ³pria classe TWindow,
     * pois isso causaria um loop infinito e esgotaria a pilha de chamadas (stack depth).
     * Em vez de criar uma nova inst�ncia, este m�todo deve atuar sobre a inst�ncia atual da janela,
     * geralmente chamando um método de busca (como 'onSearch') para popular a datagrid.
     * @param $param Par�metros da requisi��o, que podem ser repassados para o m�todo de busca.
     */
    public function onReload($param = null)
    {
        // Chama o método 'onSearch' para carregar e exibir os dados na datagrid.
        // Isso garante que, ao abrir ou recarregar a janela, a lista de conhecimentos seja populada corretamente.
        $this->onSearch($param);
    }
}
?>