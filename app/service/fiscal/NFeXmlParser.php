<?php

/**
 * NFeXmlParser
 * Faz o parsing do XML da NF-e (Nota Fiscal Eletrônica) brasileira
 * Extrai dados do cabeçalho, emitente, destinatário e itens
 */
class NFeXmlParser
{
    const NS_NFE = 'http://www.portalfiscal.inf.br/nfe';

    private DOMDocument $dom;
    private DOMXPath $xpath;

    /**
     * Carrega XML a partir de string
     */
    public function loadFromString(string $xmlContent): self
    {
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
        $result    = $this->dom->loadXML($xmlContent);

        if (!$result) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = array_map(fn($e) => "Linha {$e->line}: {$e->message}", $errors);
            throw new Exception("XML mal-formado:\n" . implode("\n", $msg));
        }

        libxml_clear_errors();
        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('nfe', self::NS_NFE);
        return $this;
    }

    /**
     * Carrega XML a partir de arquivo
     */
    public function loadFromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo não encontrado: {$filePath}");
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Erro ao ler o arquivo XML");
        }
        return $this->loadFromString($content);
    }

    /**
     * Retorna todos os dados parsed da NF-e como stdClass
     */
    public function parse(): stdClass
    {
        $result            = new stdClass();
        $result->cabecalho = $this->parseCabecalho();
        $result->emitente  = $this->parseEmitente();
        $result->dest      = $this->parseDestinatario();
        $result->itens     = $this->parseItens();
        $result->total     = $this->parseTotal();
        $result->transp    = $this->parseTransporte();
        return $result;
    }

    /**
     * Dados do cabeçalho (ide)
     */
    private function parseCabecalho(): stdClass
    {
        $cab = new stdClass();

        $cab->numero_nf   = $this->getValue('//nfe:ide/nfe:nNF') ?: $this->getValue('//ide/nNF');
        $cab->serie       = $this->getValue('//nfe:ide/nfe:serie') ?: $this->getValue('//ide/serie');
        $cab->natureza_op = $this->getValue('//nfe:ide/nfe:natOp') ?: $this->getValue('//ide/natOp');
        $cab->chave_nfe   = $this->getValue('//nfe:infNFe/@Id') ?: $this->getValue('//infNFe/@Id');

        // Tira "NFe" do início da chave se houver
        if ($cab->chave_nfe) {
            $cab->chave_nfe = ltrim($cab->chave_nfe, 'NFe');
        }

        // Data de emissão
        $dhEmi = $this->getValue('//nfe:ide/nfe:dhEmi') ?: $this->getValue('//ide/dhEmi');
        if ($dhEmi) {
            $cab->data_emissao = substr($dhEmi, 0, 10); // yyyy-mm-dd
        } else {
            $cab->data_emissao = null;
        }

        // DANFE = número da NF-e (série + número)
        $cab->danfe = trim(($cab->serie ?? '') . ' / ' . ($cab->numero_nf ?? ''), ' /');

        return $cab;
    }

    /**
     * Dados do emitente (fornecedor)
     */
    private function parseEmitente(): stdClass
    {
        $emit = new stdClass();

        $emit->cnpj      = $this->getValue('//nfe:emit/nfe:CNPJ') ?: $this->getValue('//emit/CNPJ');
        $emit->cpf       = $this->getValue('//nfe:emit/nfe:CPF')  ?: $this->getValue('//emit/CPF');
        $emit->nome      = $this->getValue('//nfe:emit/nfe:xNome') ?: $this->getValue('//emit/xNome');
        $emit->fantasia  = $this->getValue('//nfe:emit/nfe:xFant') ?: $this->getValue('//emit/xFant');
        $emit->ie        = $this->getValue('//nfe:emit/nfe:IE')    ?: $this->getValue('//emit/IE');
        $emit->logradouro = $this->getValue('//nfe:emit/nfe:enderEmit/nfe:xLgr') ?: $this->getValue('//emit/enderEmit/xLgr');
        $emit->numero    = $this->getValue('//nfe:emit/nfe:enderEmit/nfe:nro')   ?: $this->getValue('//emit/enderEmit/nro');
        $emit->municipio = $this->getValue('//nfe:emit/nfe:enderEmit/nfe:xMun') ?: $this->getValue('//emit/enderEmit/xMun');
        $emit->uf        = $this->getValue('//nfe:emit/nfe:enderEmit/nfe:UF')   ?: $this->getValue('//emit/enderEmit/UF');
        $emit->cep       = $this->getValue('//nfe:emit/nfe:enderEmit/nfe:CEP')  ?: $this->getValue('//emit/enderEmit/CEP');

        // Documento principal
        $emit->documento = $emit->cnpj ?: $emit->cpf;
        // Formata CNPJ
        if ($emit->cnpj && strlen($emit->cnpj) === 14) {
            $emit->cnpj_formatado = vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($emit->cnpj));
        }

        return $emit;
    }

    /**
     * Dados do destinatário (importador)
     */
    private function parseDestinatario(): stdClass
    {
        $dest = new stdClass();

        $dest->cnpj      = $this->getValue('//nfe:dest/nfe:CNPJ')  ?: $this->getValue('//dest/CNPJ');
        $dest->cpf       = $this->getValue('//nfe:dest/nfe:CPF')   ?: $this->getValue('//dest/CPF');
        $dest->nome      = $this->getValue('//nfe:dest/nfe:xNome') ?: $this->getValue('//dest/xNome');
        $dest->ie        = $this->getValue('//nfe:dest/nfe:IE')    ?: $this->getValue('//dest/IE');
        $dest->municipio = $this->getValue('//nfe:dest/nfe:enderDest/nfe:xMun') ?: $this->getValue('//dest/enderDest/xMun');
        $dest->uf        = $this->getValue('//nfe:dest/nfe:enderDest/nfe:UF')   ?: $this->getValue('//dest/enderDest/UF');

        $dest->documento = $dest->cnpj ?: $dest->cpf;

        return $dest;
    }

    /**
     * Itens da NF-e (det)
     */
    private function parseItens(): array
    {
        $itens    = [];
        // Tenta com namespace primeiro, depois sem
        $nodeList = $this->xpath->query('//nfe:det');
        if (!$nodeList || $nodeList->length === 0) {
            $nodeList = $this->xpath->query('//det');
        }
        if (!$nodeList) return [];

        foreach ($nodeList as $det) {
            $item = new stdClass();

            $item->numero_item   = $det->getAttribute('nItem') ?: count($itens) + 1;
            $item->codigo        = $this->getChildValue($det, ['nfe:prod/nfe:cProd', 'prod/cProd']);
            $item->descricao     = $this->getChildValue($det, ['nfe:prod/nfe:xProd', 'prod/xProd']);
            $item->ncm           = $this->getChildValue($det, ['nfe:prod/nfe:NCM',   'prod/NCM']);
            $item->cfop          = $this->getChildValue($det, ['nfe:prod/nfe:CFOP',  'prod/CFOP']);
            $item->unidade       = $this->getChildValue($det, ['nfe:prod/nfe:uCom',  'prod/uCom']);
            $item->quantidade    = (float)($this->getChildValue($det, ['nfe:prod/nfe:qCom',   'prod/qCom']) ?: 0);
            $item->valor_unit    = (float)($this->getChildValue($det, ['nfe:prod/nfe:vUnCom',  'prod/vUnCom']) ?: 0);
            $item->valor_total   = (float)($this->getChildValue($det, ['nfe:prod/nfe:vProd',   'prod/vProd']) ?: 0);

            $itens[] = $item;
        }

        return $itens;
    }

    /**
     * Totais da NF-e
     */
    private function parseTotal(): stdClass
    {
        $total = new stdClass();

        $total->valor_produtos = (float)($this->getValue('//nfe:total/nfe:ICMSTot/nfe:vProd') ?: $this->getValue('//total/ICMSTot/vProd') ?: 0);
        $total->valor_frete    = (float)($this->getValue('//nfe:total/nfe:ICMSTot/nfe:vFrete') ?: $this->getValue('//total/ICMSTot/vFrete') ?: 0);
        $total->valor_nf       = (float)($this->getValue('//nfe:total/nfe:ICMSTot/nfe:vNF')   ?: $this->getValue('//total/ICMSTot/vNF')   ?: 0);
        $total->valor_icms     = (float)($this->getValue('//nfe:total/nfe:ICMSTot/nfe:vICMS')  ?: $this->getValue('//total/ICMSTot/vICMS')  ?: 0);

        return $total;
    }

    /**
     * Dados de transporte (modal, volumes, peso)
     */
    private function parseTransporte(): stdClass
    {
        $transp = new stdClass();

        $transp->modal_frete   = $this->getValue('//nfe:transp/nfe:modFrete') ?: $this->getValue('//transp/modFrete');
        $transp->transportador = $this->getValue('//nfe:transp/nfe:transporta/nfe:xNome') ?: $this->getValue('//transp/transporta/xNome');
        $transp->cnpj_transp   = $this->getValue('//nfe:transp/nfe:transporta/nfe:CNPJ')  ?: $this->getValue('//transp/transporta/CNPJ');
        $transp->placa         = $this->getValue('//nfe:transp/nfe:veicTransp/nfe:placa')  ?: $this->getValue('//transp/veicTransp/placa');

        // Volumes
        $transp->quantidade_vol = (float)($this->getValue('//nfe:transp/nfe:vol/nfe:qVol')    ?: $this->getValue('//transp/vol/qVol') ?: 0);
        $transp->especie        = $this->getValue('//nfe:transp/nfe:vol/nfe:esp')             ?: $this->getValue('//transp/vol/esp');
        $transp->peso_liquido   = (float)($this->getValue('//nfe:transp/nfe:vol/nfe:pesoL')   ?: $this->getValue('//transp/vol/pesoL') ?: 0);
        $transp->peso_bruto     = (float)($this->getValue('//nfe:transp/nfe:vol/nfe:pesoB')   ?: $this->getValue('//transp/vol/pesoB') ?: 0);

        return $transp;
    }

    /**
     * Helper: busca valor de nó XPath
     */
    private function getValue(string $query): ?string
    {
        $node = $this->xpath->query($query);
        if ($node && $node->length > 0) {
            return trim($node->item(0)->nodeValue);
        }
        return null;
    }

    /**
     * Helper: busca valor filho de um nó com múltiplos caminhos tentados
     */
    private function getChildValue(DOMNode $parent, array $paths): ?string
    {
        foreach ($paths as $path) {
            $node = $this->xpath->query($path, $parent);
            if ($node && $node->length > 0) {
                return trim($node->item(0)->nodeValue);
            }
        }
        return null;
    }

    /**
     * Formata CNPJ 14 dígitos → XX.XXX.XXX/XXXX-XX
     */
    public static function formatCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) return $cnpj;
        return sprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', ...str_split($cnpj));
    }
}
