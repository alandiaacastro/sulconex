    <?php
    /**
     * Classe de Modelo para a tabela 'opportunity'.
     * Representa uma oportunidade no CRM.
     */
    class Opportunity extends TRecord
    {
        const TABLENAME  = 'opportunity';
        const PRIMARYKEY = 'id';
        const IDPOLICY   = 'max'; // Use 'max' para SQLite com AUTOINCREMENT

        /**
         * Construtor da classe.
         * Define os atributos que mapeiam para as colunas da tabela.
         */
        public function __construct($id = NULL, $callObjectLoad = TRUE)
        {
            parent::__construct($id, $callObjectLoad);
            parent::addAttribute('contact_id');
            parent::addAttribute('status');
            parent::addAttribute('company_name');
            parent::addAttribute('phone');
            parent::addAttribute('responsible_name');
            parent::addAttribute('position');
            parent::addAttribute('email');
            parent::addAttribute('notes');
            parent::addAttribute('closing_date'); // <-- NOVA LINHA: Adicione este atributo
        }
    }
    ?>