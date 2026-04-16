<?php

class ANTTService
{
    /**
     * Proxy backend: consulta ANTT e retorna dados extraídos como JSON
     * Exemplo: index.php?class=ANTTService&method=onConsulta&placa=ABC1234
     */
    public static function onConsulta($param)
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $placa = strtoupper(trim($param['placa'] ?? ''));
            if (!$placa) {
                throw new Exception('Placa não informada');
            }

            $html = self::consultarSiteANTT($placa);
            $dados = self::extrairDados($html);

            if (!$dados) {
                throw new Exception('Nenhum dado encontrado para esta placa');
            }

            echo json_encode(['success' => true, 'dados' => $dados], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'mensagem' => $e->getMessage()]);
        }
    }

    /**
     * Realiza a chamada HTTP ao site da ANTT
     */
    private static function consultarSiteANTT($placa)
    {
        $url = 'https://appweb1.antt.gov.br/scff/conLocalizaVeiculo.asp';

        $postData = http_build_query([
            'txtPlaca' => $placa,
            'cmdConsultaPlaca' => 'Consultar'
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $html = curl_exec($ch);
        curl_close($ch);

        return $html;
    }

    /**
     * Extrai os dados relevantes da página HTML da ANTT
     */
    private static function extrairDados($html)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $dados = [];

        // Dados do veículo
        $dados['placa']        = $xpath->evaluate("string(//td[contains(text(),'Placa:')]/following-sibling::td[1])");
        $dados['tipo']         = $xpath->evaluate("string(//td[contains(text(),'Tipo:')]/following-sibling::td[1])");
        $dados['marca']        = $xpath->evaluate("string(//td[contains(text(),'Marca:')]/following-sibling::td[1])");
        $dados['eixos']        = $xpath->evaluate("string(//td[contains(text(),'Eixos:')]/following-sibling::td[1])");
        $dados['carroceria']   = $xpath->evaluate("string(//td[contains(text(),'Carroceria:')]/following-sibling::td[1])");
        $dados['chassi_motor'] = $xpath->evaluate("string(//td[contains(text(),'Chassi/Motor:')]/following-sibling::td[1])");
        $dados['ano']          = $xpath->evaluate("string(//td[contains(text(),'Ano:')]/following-sibling::td[1])");
        $dados['ccu']          = $xpath->evaluate("string(//td[contains(text(),'CCU:')]/following-sibling::td[1])");

        // Dados da empresa
        $dados['cnpj']         = $xpath->evaluate("string(//td[contains(text(),'CNPJ:')]/following-sibling::td[1])");
        $dados['razao_social'] = $xpath->evaluate("string(//td[contains(text(),'Razão Social:')]/following-sibling::td[1])");
        $dados['nome_fantasia']= $xpath->evaluate("string(//td[contains(text(),'Nome Fantasia:')]/following-sibling::td[1])");
        $dados['endereco']     = $xpath->evaluate("string(//td[contains(text(),'Endereço:')]/following-sibling::td[1])");
        $dados['bairro']       = $xpath->evaluate("string(//td[contains(text(),'Bairro:')]/following-sibling::td[1])");
        $dados['cidade']       = $xpath->evaluate("string(//td[contains(text(),'Cidade:')]/following-sibling::td[1])");
        $dados['pais_origem']  = $xpath->evaluate("string(//td[contains(text(),'País de Origem:')]/following-sibling::td[1])");

        // Situação das licenças
        $linhas = $xpath->query("//table//tr[td[contains(text(),'BRASIL')]]");
        $licencas = [];
        foreach ($linhas as $linha) {
            $tds = $linha->getElementsByTagName('td');
            if ($tds->length >= 3) {
                $rota = trim($tds->item(0)->nodeValue);
                $status = trim($tds->item(2)->nodeValue);
                $licencas[] = "$rota: $status";
            }
        }
        $dados['situacao_licencas'] = implode(' | ', $licencas);

        return $dados['placa'] ? $dados : null;
    }
}