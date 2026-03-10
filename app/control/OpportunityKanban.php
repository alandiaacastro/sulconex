<?php

/**
 * Classe OpportunityKanban
 * Exibe um painel Kanban para Oportunidades com botões de Nova Oportunidade e Atualizar.
 * Utiliza o componente TKanban nativo do Adianti Framework 8.1.
 */
class OpportunityKanban extends TPage
{
    private $kanbanContainer; // Onde o componente TKanban será exibido na página
    private $loaded = false;    // Flag para controlar o carregamento inicial dos dados no onReload
    private $formButtons;     // Formulário dedicado para agrupar e gerenciar os botões de ação

    /**
     * Construtor da classe.
     * Configura o layout da página e os botões de ação.
     */
    public function __construct()
    {
        parent::__construct();

        // Inclui o arquivo CSS personalizado para o Kanban.
        TPage::include_css('app/resources/css/kanban_custom.css');

        // Cria o container principal HTML que abrigará o componente TKanban.
        $this->kanbanContainer = new TElement('div');
        $this->kanbanContainer->class = 'kanban-wrapper p-4';

        // Cria um container vertical (TVBox) para organizar todos os elementos da página.
        $container = new TVBox;
        $container->style = 'width: 100%';

        // Adiciona o breadcrumb de navegação, útil se a página estiver integrada a um menu.
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));

        // --- Seção dos Botões de Ação Global (`$this->formButtons`) ---
        // Criamos um TForm exclusivo para conter e gerenciar os TButton.
        $this->formButtons = new TForm('form_global_actions'); // ID único para o formulário de botões
        $this->formButtons->setProperty('style', 'padding-bottom: 10px;'); // Estilo para o espaço abaixo.

        // Cria os botões de ação da página.
        $btnNew = new TButton('btn_new');
        $btnNew->setLabel('Nova Oportunidade');
        $btnNew->setImage('fa:plus green'); // Ícone "mais" (verde)
        $btnNew->setAction(new TAction(['OpportunityForm', 'onEdit'])); // Ação para abrir o formulário.

        $btnReload = new TButton('btn_reload');
        $btnReload->setLabel('Atualizar');
        $btnReload->setImage('fa:sync-alt blue'); // Ícone de sincronização (azul)
        $btnReload->setAction(new TAction([$this, 'onReload'])); // Recarrega o Kanban.

        // Cria um THBox (container horizontal) para organizar visualmente os botões em linha.
        $buttonLayoutContainer = new THBox();
        // Adiciona apenas os botões "Nova Oportunidade" e "Atualizar" ao THBox.
        $buttonLayoutContainer->add($btnNew);
        $buttonLayoutContainer->add($btnReload);
        // Estilo para espaçamento entre os botões.
        $buttonLayoutContainer->style = 'display: flex; gap: 10px;'; 
        
        // Adiciona o THBox (que contém os botões) como o único elemento visível ao $this->formButtons.
        $this->formButtons->add($buttonLayoutContainer);

        // Lista todos os botões que este TForm irá "gerenciar" através do setFields().
        $this->formButtons->setFields([$btnNew, $btnReload]);

        // --- Adiciona os componentes principais ao container da página ---
        // Removido $this->formFilter
        $container->add($this->formButtons);      // O formulário que contém os botões de ação
        $container->add($this->kanbanContainer); // O container que abrigará o painel Kanban

        // Adiciona o container principal (TVBox) à página.
        parent::add($container);
    }

    /**
     * O método onClear não é mais necessário, pois não há filtro para limpar.
     * Pode ser removido ou deixado vazio se não for mais referenciado.
     */
    // public function onClear($param = null) {}

    /**
     * Carrega as oportunidades do banco de dados e as renderiza no painel Kanban.
     * @param $param Par�metros de filtro e ordena��o (agora n�o usados para filtro).
     */
    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample'); // Inicia uma transação com o banco de dados 'sample'.

            // Instancia o componente TKanban do Adianti Framework.
            $kanban = new TKanban;
            $kanban->setStageHeight('80vh'); // Define a altura visual das colunas do Kanban.

            // --- Configuração das Ações de Item (aparecem ao passar o mouse sobre cada cartão) ---
            $editAction = new TAction(['OpportunityForm', 'onEdit']); // Ação para editar (chama OpportunityForm).
            $editAction->setParameter('id', '{id}'); // Passa o ID do item clicado para o formulário.
            $kanban->addItemAction('Editar', $editAction, 'far:edit blue'); // Adiciona a ação "Editar" com ícone azul.

            $deleteAction = new TAction([$this, 'onDelete']); // Ação para excluir (chama método estático desta classe).
            $deleteAction->setParameter('id', '{id}');
            $kanban->addItemAction('Excluir', $deleteAction, 'far:trash-alt red'); // Adiciona a ação "Excluir" com ícone vermelho.

            // Define a ação a ser executada quando um item é arrastado e solto em uma nova fase (coluna).
            $kanban->setItemDropAction(new TAction([$this, 'onUpdateItemDrop']));

            // --- Definição das Fases (Colunas) do Kanban ---
            // Mapeia os IDs internos das fases para seus títulos exibíveis.
            $stages = [
                'QUALIFICACAO' => 'Qualificação',
                'PROPOSTA'     => 'Proposta',
                'NEGOCIACAO'   => 'Negociação',
                'FECHAMENTO'   => 'Fechamento',
            ];

            foreach ($stages as $id => $title) {
                $kanban->addStage($id, $title); // Adiciona cada fase ao componente TKanban.
            }

            // --- Cores para os cabeçalhos dos cartões, mapeadas por status ---
            $stageColors = [
                'QUALIFICACAO' => '#dc3545', // Vermelho (perigo)
                'PROPOSTA'     => '#28a745', // Verde (sucesso)
                'NEGOCIACAO'   => '#ffc107', // Laranja (alerta)
                'FECHAMENTO'   => '#007bff', // Azul (primário)
            ];

            // --- Carregamento das Oportunidades (sem filtro) ---
            // O código de filtro de $data e $this->formFilter->setData($data) foi removido.
            $criteria = new TCriteria(); // Objeto para construir a consulta SQL.
            // Não há mais filtros de empresa aqui.
            
            // Carrega todos os objetos Opportunity do banco de dados.
            $repo = new TRepository('Opportunity');
            $items = $repo->load($criteria);

            // --- Adiciona cada oportunidade como um cartão ao TKanban ---
            if ($items) { // Se houver oportunidades carregadas
                foreach ($items as $item) {
                    $stage = $item->status ?? 'QUALIFICACAO'; // Define a fase do cartão (com fallback).
                    $color = $stageColors[$stage] ?? '#6c757d'; // Define a cor do cartão (com fallback).

                    // Constrói o conteúdo HTML personalizado de cada cartão.
                    $content = "
                    <div style='
                        background-color: {$color};
                        padding: 4px 8px;
                        border-top-left-radius: 6px;
                        border-top-right-radius: 6px;
                        font-weight: bold;
                        font-size: 14px;
                        color: white;
                    '>
                        {$item->company_name}
                    </div>
                    <div class='kanban-card-content' style='padding: 8px;'>
                        <div style='display:flex; align-items:center; gap:6px; margin-bottom:4px;'>
                            <i class='fas fa-user-circle' style='color:{$color};'></i>
                            <b style='font-size:13px; color:#333;'>{$item->responsible_name}</b>
                        </div>
                        <div style='display:flex; align-items:center; gap:6px; margin-bottom:4px; color:#555; font-size:13px;'>
                            <i class='fas fa-envelope' style='color:{$color};'></i>
                            {$item->email}
                        </div>";

                    if (!empty($item->notes)) {
                        $short_notes = mb_substr($item->notes, 0, 80);
                        if (mb_strlen($item->notes) > 80) {
                            $short_notes .= '...';
                        }
                        $content .= "
                        <div style='background:#f8f9fa; padding:6px; border-radius:6px; margin-bottom:6px; font-size:13px; color:#333;'>
                            <i class='far fa-comment-dots' style='color:{$color};'></i> {$short_notes}
                        </div>";
                    }

                    if (!empty($item->closing_date)) {
                        $date = TDate::date2br($item->closing_date); // Formata a data para o padrão brasileiro.
                        $content .= "
                        <div style='color:#888; font-size:12px;'>
                            <i class='far fa-calendar-alt'></i> Fechamento: <b>{$date}</b>
                        </div>";
                    }

                    $content .= "</div>"; // Fecha o div 'kanban-card-content'

                    // Adiciona o item ao componente TKanban.
                    $kanban->addItem(
                        $item->id,         // ID único do item.
                        $stage,            // ID da fase (coluna) a que pertence.
                        '',                // Título do card (pode ser vazio se o conteúdo já o tiver).
                        $content,          // Conteúdo HTML customizado do card.
                        $color             // Cor principal do card.
                    );
                }
            }

            // Limpa o container da página e adiciona o componente TKanban gerado.
            $this->kanbanContainer->clearChildren();
            $this->kanbanContainer->add($kanban);

            TTransaction::close(); // Fecha a transação com o banco de dados.
            $this->loaded = true;   // Define a flag para indicar que a página foi carregada.
        } catch (Exception $e) {
            TTransaction::rollback(); // Em caso de erro, desfaz a transação.
            new TMessage('error', 'Erro ao carregar Kanban: ' . $e->getMessage()); // Exibe mensagem de erro.
        }
    }

    /**
     * Método estático chamado pelo TKanban quando um item é solto em uma nova fase.
     * Atualiza o status da oportunidade correspondente no banco de dados via AJAX.
     * @param $param Array contendo 'id' (ID da oportunidade) e 'stage_id' (o novo status/fase).
     */
    public static function onUpdateItemDrop($param)
    {
        try {
            TTransaction::open('sample'); // Abre a transação.

            $opp = new Opportunity($param['id']); // Carrega a oportunidade pelo ID.
            $opp->status = $param['stage_id'];   // Atualiza o status com o ID da nova fase.
            $opp->store();                       // Salva as alterações no banco.

            TTransaction::close(); // Fecha a transação.

            TToast::show('success', 'Status atualizado com sucesso!', 'bottom right'); // Exibe uma notificação de sucesso.
        } catch (Exception $e) {
            TTransaction::rollback(); // Desfaz a transação em caso de erro.
            TToast::show('error', 'Erro ao atualizar status: ' . $e->getMessage(), 'bottom right'); // Exibe uma notificação de erro.
        }
    }

    /**
     * Exibe uma pergunta de confirmação antes de excluir uma oportunidade.
     * Este método é estático porque é chamado por TAction de uma ação de item no Kanban.
     * @param $param Par�metros (cont�m o 'id' do item a ser exclu�do).
     */
    public static function onDelete($param)
    {
        // Cria uma ação que chama o método estático 'Delete' desta própria classe após a confirmação.
        $actionYes = new TAction([__CLASS__, 'Delete']);
        $actionYes->setParameters($param); // Passa os par�metros (ID) para a a��o 'Delete'.

        // Exibe a caixa de pergunta ao usuário.
        new TQuestion('Deseja realmente excluir esta oportunidade?', $actionYes);
    }

    /**
     * Executa a exclusão de uma oportunidade após a confirmação do usuário.
     * Este método é estático porque é chamado por TAction da caixa de pergunta.
     * @param $param Par�metros (cont�m o 'id' do item a ser exclu�do).
     */
    public static function Delete($param)
    {
        try {
            TTransaction::open('sample'); // Abre a transação.

            $opp = new Opportunity($param['id']); // Carrega a oportunidade pelo ID.
            $opp->delete();                       // Exclui o registro do banco de dados.

            TTransaction::close(); // Fecha a transação.

            TToast::show('success', 'Oportunidade excluída com sucesso!', 'bottom right'); // Exibe uma notificação de sucesso.

            // Recarrega a página do Kanban para refletir a exclusão do item.
            TApplication::loadPage(__CLASS__, 'onReload');
        } catch (Exception $e) {
            TTransaction::rollback(); // Desfaz a transação em caso de erro.
            TToast::show('error', 'Erro ao excluir oportunidade: ' . $e->getMessage(), 'bottom right'); // Exibe uma notificação de erro.
        }
    }

    /**
     * Método show() padrão do TPage.
     * Garante que o método onReload seja chamado na primeira vez que a página é exibida.
     */
    public function show()
    {
        // Se a página ainda não foi carregada (onReload não foi chamado), chama onReload.
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show(); // Chama o método show da classe pai.
    }
}
?>