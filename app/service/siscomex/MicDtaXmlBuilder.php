<?php


use DOMDocument;
use DOMElement;
use Exception;

/**
 * MicDtaXmlBuilder
 * Construtor de XML para transmissão de MIC/DTA ao Portal Único Siscomex
 *
 * Gera XML conforme layout técnico do CCT Exportação (Receita Federal)
 * Documentação: https://portalunico.siscomex.gov.br
 */
class MicDtaXmlBuilder
{
    private $dom;
    private $root;

    /**
     * Constrói XML MIC/DTA a partir de um Conhecimento (CRT)
     *
     * @param int $conhecimento_id ID do conhecimento/CRT
     * @param array $items Array com items NF-e: [['chave' => '...', 'valor_frete' => 0], ...]
     * @return string XML em string
     * @throws Exception Se dados obrigatórios estejam ausentes
     */
    public static function buildMicDta($conhecimento_id, $items)
    {
        $builder = new self();
        return $builder->generate($conhecimento_id, $items);
    }

    /**
     * Construtor
     */
    private function __construct()
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;
    }

    /**
     * Gera o XML completo
     *
     * @param int $conhecimento_id
     * @param array $items
     * @return string
     * @throws Exception
     */
    private function generate($conhecimento_id, $items)
    {
        try {
            // Carregar dados do conhecimento
            $conhecimento = new Conhecimento($conhecimento_id);
            if (!$conhecimento->id) {
                throw new Exception("Conhecimento não encontrado: {$conhecimento_id}");
            }

            // Validar dados obrigatórios
            $this->validateRequiredFields($conhecimento, $items);

            // Criar raiz do XML
            $this->root = $this->dom->createElement('MIC');
            $this->root->setAttribute('versao', '1.0');
            $this->dom->appendChild($this->root);

            // Montar estrutura do XML
            $this->addHeader($conhecimento);
            $this->addTransporter($conhecimento);
            $this->addExporter($conhecimento);
            $this->addImporter($conhecimento);
            $this->addCargo($conhecimento);
            $this->addVehicle($conhecimento);
            $this->addDriver($conhecimento);
            $this->addNFes($items);

            return $this->dom->saveXML();

        } catch (Exception $e) {
            throw new Exception("Erro ao gerar XML MIC/DTA: " . $e->getMessage());
        }
    }

    /**
     * Adiciona header do manifesto
     */
    private function addHeader(Conhecimento $conhecimento)
    {
        $header = $this->createElement('cabecalho');

        $this->addElement($header, 'numeroMic', $conhecimento->numero);
        $this->addElement($header, 'dataMic', $this->formatDate($conhecimento->data_transportador_assinatura));
        $this->addElement($header, 'statusMic', '001'); // 001 = Em elaboração

        $this->root->appendChild($header);
    }

    /**
     * Adiciona dados do transportador
     */
    private function addTransporter(Conhecimento $conhecimento)
    {
        try {
            $permissao = $conhecimento->get_permisso();
            if (!$permissao || !$permissao->id) {
                throw new Exception("Permissão/Transportadora não encontrada");
            }

            $transporter = $this->createElement('transportador');

            // CNPJ da transportadora
            $cnpj = preg_replace('/\D/', '', $permissao->cnpj ?? '');
            if (strlen($cnpj) !== 14) {
                throw new Exception("CNPJ da transportadora inválido");
            }
            $this->addElement($transporter, 'cnpj', $cnpj);
            $this->addElement($transporter, 'razaoSocial', $permissao->transportadora ?? '');
            $this->addElement($transporter, 'permissao', $permissao->permisso ?? '');

            $this->root->appendChild($transporter);

        } catch (Exception $e) {
            throw new Exception("Erro ao adicionar dados do transportador: " . $e->getMessage());
        }
    }

    /**
     * Adiciona dados do exportador (remetente)
     */
    private function addExporter(Conhecimento $conhecimento)
    {
        try {
            $exportador = $conhecimento->get_remetente();
            if (!$exportador || !$exportador->id) {
                throw new Exception("Exportador/Remetente não encontrado");
            }

            $exporter = $this->createElement('exportador');

            // CNPJ do exportador
            $cnpj = preg_replace('/\D/', '', $exportador->cnpj ?? '');
            if (strlen($cnpj) !== 14) {
                throw new Exception("CNPJ do exportador inválido");
            }
            $this->addElement($exporter, 'cnpj', $cnpj);
            $this->addElement($exporter, 'nome', $exportador->nome ?? '');
            $this->addElement($exporter, 'endereco', $exportador->endereco ?? '');
            $this->addElement($exporter, 'cidade', $exportador->cidade ?? '');
            $this->addElement($exporter, 'estado', $exportador->estado ?? '');
            $this->addElement($exporter, 'cep', preg_replace('/\D/', '', $exportador->cep ?? ''));

            $this->root->appendChild($exporter);

        } catch (Exception $e) {
            throw new Exception("Erro ao adicionar dados do exportador: " . $e->getMessage());
        }
    }

    /**
     * Adiciona dados do importador (destinatário)
     */
    private function addImporter(Conhecimento $conhecimento)
    {
        try {
            $importador = $conhecimento->get_destinatario();
            if (!$importador || !$importador->id) {
                throw new Exception("Importador/Destinatário não encontrado");
            }

            $importer = $this->createElement('importador');

            // CNPJ do importador (pode ser do exterior - opcional)
            $cnpj = preg_replace('/\D/', '', $importador->cnpj ?? '');
            if (!empty($cnpj)) {
                $this->addElement($importer, 'cnpj', $cnpj);
            }

            $this->addElement($importer, 'nome', $importador->nome ?? '');
            $this->addElement($importer, 'endereco', $importador->endereco ?? '');
            $this->addElement($importer, 'cidade', $importador->cidade ?? '');
            $this->addElement($importer, 'estado', $importador->estado ?? '');
            $this->addElement($importer, 'cep', preg_replace('/\D/', '', $importador->cep ?? ''));

            $this->root->appendChild($importer);

        } catch (Exception $e) {
            throw new Exception("Erro ao adicionar dados do importador: " . $e->getMessage());
        }
    }

    /**
     * Adiciona dados da carga/mercadoria
     */
    private function addCargo(Conhecimento $conhecimento)
    {
        $cargo = $this->createElement('carga');

        $this->addElement($cargo, 'descricao', substr($conhecimento->descricao_mercadoria ?? '', 0, 255));
        $this->addElement($cargo, 'pesoBruto', number_format($conhecimento->peso_bruto_kg ?? 0, 2, '.', ''));
        $this->addElement($cargo, 'pesoLiquido', number_format($conhecimento->peso_liq_kg ?? 0, 2, '.', ''));
        $this->addElement($cargo, 'volume', number_format($conhecimento->volume_m3 ?? 0, 4, '.', ''));
        $this->addElement($cargo, 'quantidadeVolumes', $conhecimento->quantidade_volumes ?? 0);
        $this->addElement($cargo, 'especie', substr($conhecimento->especie_vol ?? '', 0, 20));

        // Valores
        $valorMercadoria = $conhecimento->valor_mercadorias ?? 0;
        $this->addElement($cargo, 'valor', number_format($valorMercadoria, 2, '.', ''));

        $this->root->appendChild($cargo);
    }

    /**
     * Adiciona dados do veículo (trator)
     */
    private function addVehicle(Conhecimento $conhecimento)
    {
        try {
            // Buscar contrato para obter veículo
            $contratos = \Adianti\Database\TDatabase::get()
                ->query("SELECT * FROM contrato WHERE conhecimento_numero = ?", [$conhecimento->numero]);

            if ($contratos && $contratos->rowCount() > 0) {
                $contrato_data = $contratos->fetch(\PDO::FETCH_ASSOC);
                $veiculo_id = $contrato_data['veiculo_id'] ?? null;

                if ($veiculo_id) {
                    $veiculo = new \Adianti\Model\Veiculo($veiculo_id);
                    if ($veiculo->id) {
                        $vehicle = $this->createElement('veiculo');

                        // Placa do trator
                        $placa = $veiculo->placa_trator ?? '';
                        if (empty($placa)) {
                            throw new Exception("Placa do trator não informada");
                        }
                        $this->addElement($vehicle, 'placaTrator', $placa);

                        // Dados ANTT
                        $antt = $veiculo->get_antt_consulta_trator();
                        if ($antt && $antt->id) {
                            $this->addElement($vehicle, 'proprietarioCnpj',
                                preg_replace('/\D/', '', $antt->cnpj ?? ''));
                            $this->addElement($vehicle, 'proprietarioRazao', $antt->razao_social ?? '');
                        }

                        $this->root->appendChild($vehicle);
                        return;
                    }
                }
            }

            // Se chegou aqui, não encontrou veículo
            throw new Exception("Veículo não encontrado para este conhecimento");

        } catch (Exception $e) {
            throw new Exception("Erro ao adicionar dados do veículo: " . $e->getMessage());
        }
    }

    /**
     * Adiciona dados do motorista
     */
    private function addDriver(Conhecimento $conhecimento)
    {
        try {
            // Buscar veículo e motorista
            $contratos = \Adianti\Database\TDatabase::get()
                ->query("SELECT * FROM contrato WHERE conhecimento_numero = ?", [$conhecimento->numero]);

            if ($contratos && $contratos->rowCount() > 0) {
                $contrato_data = $contratos->fetch(\PDO::FETCH_ASSOC);
                $veiculo_id = $contrato_data['veiculo_id'] ?? null;

                if ($veiculo_id) {
                    $veiculo = new \Adianti\Model\Veiculo($veiculo_id);
                    if ($veiculo->id) {
                        $motorista = $veiculo->get_motorista();
                        if ($motorista && $motorista->id) {
                            $driver = $this->createElement('motorista');

                            $cpf = preg_replace('/\D/', '', $motorista->cpf ?? '');
                            if (strlen($cpf) !== 11) {
                                throw new Exception("CPF do motorista inválido");
                            }

                            $this->addElement($driver, 'cpf', $cpf);
                            $this->addElement($driver, 'nome', $motorista->nome ?? '');
                            $this->addElement($driver, 'cnh', preg_replace('/\D/', '', $motorista->cnh_numero ?? ''));
                            $this->addElement($driver, 'categoria', $motorista->categoria ?? '');

                            $this->root->appendChild($driver);
                            return;
                        }
                    }
                }
            }

            throw new Exception("Motorista não encontrado para este conhecimento");

        } catch (Exception $e) {
            throw new Exception("Erro ao adicionar dados do motorista: " . $e->getMessage());
        }
    }

    /**
     * Adiciona lista de NF-es (chaves de acesso)
     */
    private function addNFes($items)
    {
        if (empty($items)) {
            throw new Exception("Nenhuma NF-e informada para a transmissão");
        }

        $nfes = $this->createElement('nfes');

        foreach ($items as $index => $item) {
            if (empty($item['chave'])) {
                throw new Exception("Chave de acesso da NF-e não informada no item " . ($index + 1));
            }

            // Validar formato da chave (44 dígitos)
            $chave = preg_replace('/\D/', '', $item['chave']);
            if (strlen($chave) !== 44) {
                throw new Exception("Chave de NF-e inválida (deve ter 44 dígitos): " . $item['chave']);
            }

            $nfe = $this->createElement('nfe');
            $this->addElement($nfe, 'chave', $chave);

            // Valor do frete se informado
            if (!empty($item['valor_frete'])) {
                $valor = number_format((float) $item['valor_frete'], 2, '.', '');
                $this->addElement($nfe, 'valorFrete', $valor);
            }

            $nfes->appendChild($nfe);
        }

        $this->root->appendChild($nfes);
    }

    /**
     * Valida dados obrigatórios antes de gerar o XML
     *
     * @param Conhecimento $conhecimento
     * @param array $items
     * @throws Exception
     */
    private function validateRequiredFields(Conhecimento $conhecimento, $items)
    {
        // Conhecimento
        if (empty($conhecimento->numero)) {
            throw new Exception("Número do CRT não informado");
        }

        // Items (NF-es)
        if (empty($items) || !is_array($items)) {
            throw new Exception("Nenhuma NF-e informada");
        }

        // Validar que cada item tem chave
        foreach ($items as $item) {
            if (empty($item['chave'])) {
                throw new Exception("Item sem chave de acesso NF-e");
            }
        }

        // Dados de carga
        if (empty($conocimiento->descricao_mercadoria)) {
            throw new Exception("Descrição da mercadoria não informada");
        }

        // Exportador
        try {
            $exportador = $conhecimento->get_remetente();
            if (!$exportador || !$exportador->id) {
                throw new Exception("Exportador não informado");
            }
        } catch (Exception $e) {
            throw new Exception("Erro ao validar exportador: " . $e->getMessage());
        }

        // Importador
        try {
            $importador = $conhecimento->get_destinatario();
            if (!$importador || !$importador->id) {
                throw new Exception("Importador não informado");
            }
        } catch (Exception $e) {
            throw new Exception("Erro ao validar importador: " . $e->getMessage());
        }
    }

    /**
     * Helper: Cria elemento XML
     */
    private function createElement($tagName)
    {
        return $this->dom->createElement($tagName);
    }

    /**
     * Helper: Adiciona elemento com valor
     */
    private function addElement(DOMElement $parent, $tagName, $value)
    {
        $element = $this->createElement($tagName);
        $element->appendChild($this->dom->createTextNode((string) $value));
        $parent->appendChild($element);
    }

    /**
     * Helper: Formata data para ISO 8601
     */
    private function formatDate($date)
    {
        if ($date instanceof \DateTime) {
            return $date->format('Y-m-d');
        }

        if (is_string($date)) {
            // Tentar converter de dd/mm/yyyy para yyyy-mm-dd
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $date, $matches)) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            }
            return $date;
        }

        return date('Y-m-d');
    }
}
?>
