<?php

/**
 * Gera XML de importação CRT (Carta de Porte Internacional por Carretera)
 * a partir de um registro Conhecimento.
 */
class CrtXmlExporter
{
    /**
     * Gera o conteúdo XML do CRT e retorna a string.
     */
    public static function buildXml(Conhecimento $crt): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('CartaPorteInternacional');
        $dom->appendChild($root);

        // ---------- Informações Gerais ----------
        $gerais = $dom->createElement('InformacoesGerais');
        $root->appendChild($gerais);
        self::add($dom, $gerais, 'Numero',          $crt->numero);
        self::add($dom, $gerais, 'DataEmissao',     self::fmtDate($crt->data_transportador_assinatura));
        self::add($dom, $gerais, 'Permisso',        $crt->permisso);
        self::add($dom, $gerais, 'FaturaCRT',       $crt->fatura_crt);
        self::add($dom, $gerais, 'CopiarCRT',       $crt->copiacrt == '1' ? 'S' : 'N');
        self::add($dom, $gerais, 'Assinatura',      $crt->assinatura_nome);

        // ---------- Remetente ----------
        $rem = $dom->createElement('Remetente');
        $root->appendChild($rem);
        self::add($dom, $rem, 'Nome',     $crt->nome_remetente);
        self::add($dom, $rem, 'Endereco', $crt->endereco_remetente);
        self::addClientDetails($dom, $rem, $crt->get_remetente(), 'EXPORTADOR');

        // ---------- Destinatário ----------
        $dest = $dom->createElement('Destinatario');
        $root->appendChild($dest);
        self::add($dom, $dest, 'Nome',     $crt->nome_destinatario);
        self::add($dom, $dest, 'Endereco', $crt->endereco_destinatario);
        self::addClientDetails($dom, $dest, $crt->get_destinatario(), 'IMPORTADOR');

        // ---------- Consignatário ----------
        $consig = $dom->createElement('Consignatario');
        $root->appendChild($consig);
        self::add($dom, $consig, 'Nome',     $crt->nome_consignatario);
        self::add($dom, $consig, 'Endereco', $crt->endereco_consignatario);
        self::addClientDetails($dom, $consig, $crt->get_consignatario(), 'CONSIGNATARIO');

        // ---------- Notificar ----------
        $notif = $dom->createElement('Notificar');
        $root->appendChild($notif);
        self::add($dom, $notif, 'Nome',     $crt->notificar_nome);
        self::add($dom, $notif, 'Endereco', $crt->notificar_endereco);
        self::addClientDetails($dom, $notif, $crt->get_notificar(), 'NOTIFICAR');

        // ---------- Locais ----------
        $locais = $dom->createElement('Locais');
        $root->appendChild($locais);
        self::add($dom, $locais, 'Emissao',          $crt->local_emissao);
        self::add($dom, $locais, 'Responsabilidade', $crt->local_responsabilidade);
        self::add($dom, $locais, 'Entrega',          $crt->local_entrega);

        // ---------- Carga ----------
        $carga = $dom->createElement('Carga');
        $root->appendChild($carga);
        self::add($dom, $carga, 'Descricao',         $crt->descricao_mercadoria);
        self::add($dom, $carga, 'PesoBrutoKg',       self::fmtNum($crt->peso_bruto_kg));
        self::add($dom, $carga, 'PesoLiquidoKg',     self::fmtNum($crt->peso_liq_kg));
        self::add($dom, $carga, 'VolumeM3',          $crt->volume_m3);
        self::add($dom, $carga, 'QuantidadeVolumes', $crt->quantidade_volumes);
        self::add($dom, $carga, 'EspecieVolume',     $crt->especie_vol);
        self::add($dom, $carga, 'Incoterm',          $crt->incoterm);
        self::add($dom, $carga, 'Incoterm16',        $crt->incoterm16);
        self::add($dom, $carga, 'MoedaMercadoria',   $crt->moeda_valor_mercadorias);
        self::add($dom, $carga, 'ValorMercadoria',   self::fmtNum($crt->valor_mercadorias));
        self::add($dom, $carga, 'ValorDeclarado',    self::fmtNum($crt->valor_declarado));
        self::add($dom, $carga, 'ValorReembolso',    self::fmtNum($crt->valor_reembolso));

        // ---------- Frete ----------
        $frete = $dom->createElement('Frete');
        $root->appendChild($frete);
        self::add($dom, $frete, 'Moeda',        $crt->moeda_frete_externo);
        self::add($dom, $frete, 'ValorExterno', self::fmtNum($crt->valor_frete_externo));

        // ---------- Custos ----------
        $custos = $dom->createElement('Custos');
        $root->appendChild($custos);
        self::add($dom, $custos, 'Moeda', $crt->gastosmoeda);

        for ($i = 1; $i <= 3; $i++) {
            $descKey  = "textogasto{$i}";
            $remKey   = "custoremetente{$i}";
            $destKey  = "custodestino{$i}";
            if (!empty($crt->$descKey) || !empty($crt->$remKey) || !empty($crt->$destKey)) {
                $item = $dom->createElement("Item{$i}");
                $custos->appendChild($item);
                self::add($dom, $item, 'Descricao',        $crt->$descKey);
                self::add($dom, $item, 'CustoRemetente',   self::fmtNum($crt->$remKey));
                self::add($dom, $item, 'CustoDestinatario', self::fmtNum($crt->$destKey));
            }
        }

        self::add($dom, $custos, 'TotalRemetente',    self::fmtNum($crt->total_custo_remetente));
        self::add($dom, $custos, 'TotalDestinatario', self::fmtNum($crt->total_custo_destinatario));

        // ---------- Observações & Documentos ----------
        $obs = $dom->createElement('Observacoes');
        $root->appendChild($obs);
        self::add($dom, $obs, 'Observacoes',         $crt->observacoes);
        self::add($dom, $obs, 'InstrucoesAlfandega', $crt->instrucoes_alfandega);
        self::add($dom, $obs, 'DocumentosAnexos',    $crt->documentos_anexos);

        return $dom->saveXML();
    }

    /**
     * Gera o XML, grava em tmp/ e retorna o caminho do arquivo.
     */
    public static function exportToFile(Conhecimento $crt): string
    {
        $xml      = self::buildXml($crt);
        $filename = 'CRT_' . ($crt->numero ?: $crt->id) . '_' . date('Ymd_His') . '.xml';
        $path     = 'tmp/' . $filename;
        file_put_contents($path, $xml);
        return $path;
    }

    // ---- helpers ----

    /**
     * Acrescenta dados completos do cliente (CNPJ, email, etc.) na seção XML.
     */
    private static function addClientDetails(DOMDocument $dom, DOMElement $parent, $cliente, string $tipoDefault): void
    {
        if ($cliente instanceof Clientes) {
            self::add($dom, $parent, 'Cnpj',               $cliente->cnpj);
            self::add($dom, $parent, 'Email',              $cliente->email);
            self::add($dom, $parent, 'Telefone',           $cliente->telefone);
            self::add($dom, $parent, 'Cidade',             $cliente->cidade);
            self::add($dom, $parent, 'Estado',             $cliente->estado);
            self::add($dom, $parent, 'Cep',                $cliente->cep);
            self::add($dom, $parent, 'InscricaoEstadual',  $cliente->inscricao_estadual);
            self::add($dom, $parent, 'Atividade',          $cliente->atividade);
            self::add($dom, $parent, 'EmissaoCrt',         $cliente->emissao_crt);
            self::add($dom, $parent, 'Tipo',               $cliente->tipo ?: $tipoDefault);
        } else {
            self::add($dom, $parent, 'Cnpj',               '');
            self::add($dom, $parent, 'Email',              '');
            self::add($dom, $parent, 'Telefone',           '');
            self::add($dom, $parent, 'Cidade',             '');
            self::add($dom, $parent, 'Estado',             '');
            self::add($dom, $parent, 'Cep',                '');
            self::add($dom, $parent, 'InscricaoEstadual',  '');
            self::add($dom, $parent, 'Atividade',          '');
            self::add($dom, $parent, 'EmissaoCrt',         '');
            self::add($dom, $parent, 'Tipo',               $tipoDefault);
        }
    }

    private static function add(DOMDocument $dom, DOMElement $parent, string $tag, $value): void
    {
        $node = $dom->createElement($tag);
        $node->appendChild($dom->createTextNode((string) ($value ?? '')));
        $parent->appendChild($node);
    }

    private static function fmtDate(string $value = null): string
    {
        if (empty($value)) {
            return '';
        }
        // banco: yyyy-mm-dd
        $ts = strtotime($value);
        return $ts ? date('d/m/Y', $ts) : $value;
    }

    private static function fmtNum($value): string
    {
        if (is_null($value) || $value === '') {
            return '';
        }
        // normaliza vírgula BR para ponto
        $clean = str_replace(['.', ','], ['', '.'], (string) $value);
        return is_numeric($clean) ? number_format((float) $clean, 4, '.', '') : (string) $value;
    }
}
