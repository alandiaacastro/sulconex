<?php
/**
 * Fatura Active Record
 * @author  <seu-nome>
 */
class Fatura extends TRecord
{
    const TABLENAME = 'fatura';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'serial'; // serial ou max

    private $clientekey;
    private $conhecimentokey;

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        //
        // Adiciona todos os atributos da tabela
        //
        parent::addAttribute('pessoa_id');
        parent::addAttribute('conhecimento_id');
        parent::addAttribute('numero_fatura');
        parent::addAttribute('numero_crt');
        parent::addAttribute('fatura_cliente');
        parent::addAttribute('emissao');
        parent::addAttribute('vencimento');
        parent::addAttribute('prazo');
        parent::addAttribute('taxa');
        parent::addAttribute('nota_fiscal');
        parent::addAttribute('chave_acesso_nfe');
        parent::addAttribute('texto_observacao');
        parent::addAttribute('descricao1');
        parent::addAttribute('valor1');
        parent::addAttribute('descricao2');
        parent::addAttribute('valor2');
        parent::addAttribute('descricao3');
        parent::addAttribute('valor3');
        parent::addAttribute('valor_fatura');
        parent::addAttribute('valor_extenso');
        parent::addAttribute('pagamento');
        parent::addAttribute('tipo_baixa');
        parent::addAttribute('desconto_banco');
        parent::addAttribute('ORIGEM');
        parent::addAttribute('DESTINO');
        parent::addAttribute('REMETENTE');
        parent::addAttribute('DESTINATARIO');
        parent::addAttribute('PESO_BRUTO');
        parent::addAttribute('PRODUTO');
    }

    /**
     * Retorna o cliente (pessoa) associado.
     * Executado sempre que a propriedade ->clientekey é acessada.
     * @return Clientes
     */
    public function get_clientekey()
    {
        if (empty($this->clientekey))
        {
            $this->clientekey = new Clientes($this->pessoa_id);
        }
        return $this->clientekey;
    }

    /**
     * Retorna o conhecimento associado.
     * Executado sempre que a propriedade ->conhecimentokey é acessada.
     * @return Conhecimento
     */
    public function get_conhecimentokey()
    {
        if (empty($this->conhecimentokey))
        {
            $this->conhecimentokey = new Conhecimento($this->conhecimento_id);
        }
        return $this->conhecimentokey;
    }
}
