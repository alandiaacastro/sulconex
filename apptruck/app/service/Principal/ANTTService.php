<?php
// [class-head]

// [/class-head]

/**
 * ANTTService
 */
class ANTTService
{
    // [class-body]

    // [/class-body]
    
    /**
     * onconsulta()
     */
    public static function onconsulta($param)
    {
      try {
            $placa = strtoupper(trim($param['placa'] ?? ''));
            if (!$placa) {
                throw new Exception('Placa não informada.');
            }

            // --- CORREÇÃO FINAL: URL ATUALIZADA PARA O NOVO ENDEREÇO DO SERVIÇO ---
            $url = 'https://scff.antt.gov.br/conLocalizaVeiculo.asp';
            
            $cookie_file = tempnam(sys_get_temp_dir(), 'cookie_antt_');

            // --- ETAPA 1: VISITAR A PÁGINA (GET) ---
            $ch_get = curl_init($url);
            curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_get, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch_get, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch_get, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch_get, CURLOPT_COOKIEJAR, $cookie_file);

            $initial_html = curl_exec($ch_get);
            if (curl_errno($ch_get)) {
                throw new Exception('Erro na Etapa 1 (GET): ' . curl_error($ch_get));
            }
            curl_close($ch_get);

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($initial_html);
            $xpath = new DOMXPath($dom);
            $hidden_inputs = $xpath->query('//form[@name="Formulario"]//input[@type="hidden"]');
            
            $post_fields = [];
            foreach ($hidden_inputs as $input) {
                $post_fields[$input->getAttribute('name')] = $input->getAttribute('value');
            }

            $post_fields['txtPlaca'] = $placa;
            $post_fields['cmdConsultaPlaca'] = 'Consultar';

            // --- ETAPA 2: ENVIAR A CONSULTA (POST) ---
            $ch_post = curl_init($url);
            curl_setopt($ch_post, CURLOPT_POST, true);
            curl_setopt($ch_post, CURLOPT_POSTFIELDS, http_build_query($post_fields));
            curl_setopt($ch_post, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_post, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch_post, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch_post, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch_post, CURLOPT_COOKIEFILE, $cookie_file);
            curl_setopt($ch_post, CURLOPT_REFERER, $url);

            $final_html = curl_exec($ch_post);
            if (curl_errno($ch_post)) {
                throw new Exception('Erro na Etapa 2 (POST): ' . curl_error($ch_post));
            }
            curl_close($ch_post);
            
            unlink($cookie_file);

            $dados = self::extrairDados($final_html);
            if (!$dados) {
                throw new Exception('Não foi possível extrair os dados da consulta. O layout do site da ANTT pode ter mudado ou a placa não foi encontrada.');
            }
            
            TTransaction::open('sample');
            $log = new ANTTConsulta;
            $log->fromArray($dados);
            $log->store();
            TTransaction::close();

            return ['success' => true, 'dados' => $dados];

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            return ['success' => false, 'mensagem' => $e->getMessage()];
        }
    }


    private static function extrairDados($html) {
        if (empty($html) || strpos($html, 'Nenhum veículo encontrado') !== false) { return null; }
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $dados = [];
        $container_veiculo = "//th[contains(text(), 'Dados do Veículo')]/ancestor::table";
        $container_empresa = "//th[contains(text(), 'Dados da Empresa')]/ancestor::table";
        $dados['placa']        = trim($xpath->evaluate("string({$container_veiculo}//td[normalize-space()='Placa:']/following-sibling::td[1])"));
        $dados['tipo']         = trim($xpath->evaluate("string({$container_veiculo}//td[normalize-space()='Tipo:']/following-sibling::td[1])"));
        $dados['marca']        = trim($xpath->evaluate("string({$container_veiculo}//td[normalize-space()='Marca:']/following-sibling::td[1])"));
        $dados['carroceria']   = trim($xpath->evaluate("string({$container_veiculo}//td[normalize-space()='Carroceria:']/following-sibling::td[1])"));
        $dados['eixos']        = trim($xpath->evaluate("string({$container_veiculo}//td[normalize-space()='Eixos:']/following-sibling::td[1])"));
        $dados['chassi_motor'] = trim($xpath->evaluate("string({$container_veiculo}//td[normalize-space()='Chassi/Motor:']/following-sibling::td[1])"));
        $dados['ano']          = trim($xpath->evaluate("string({$container_veiculo}//td[normalize-space()='Ano:']/following-sibling::td[1])"));
        $dados['ccu']          = trim($xpath->evaluate("string({$container_veiculo}//td[normalize-space()='CCU:']/following-sibling::td[1])"));
        $dados['cnpj']         = trim($xpath->evaluate("string({$container_empresa}//td[normalize-space()='CPNJ:']/following-sibling::td[1])"));
        $dados['razao_social'] = trim($xpath->evaluate("string({$container_empresa}//td[normalize-space()='Razão Social:']/following-sibling::td[1])"));
        $dados['nome_fantasia']= trim($xpath->evaluate("string({$container_empresa}//td[normalize-space()='Nome Fantasia:']/following-sibling::td[1])"));
        $dados['endereco']     = trim($xpath->evaluate("string({$container_empresa}//td[normalize-space()='Endereço:']/following-sibling::td[1])"));
        $dados['bairro']       = trim($xpath->evaluate("string({$container_empresa}//td[normalize-space()='Bairro:']/following-sibling::td[1])"));
        $dados['cidade']       = trim($xpath->evaluate("string({$container_empresa}//td[normalize-space()='Cidade:']/following-sibling::td[1])"));
        $dados['pais_origem']  = trim($xpath->evaluate("string({$container_empresa}//td[normalize-space()='País de Origem:']/following-sibling::td[1])"));
        $linhas = $xpath->query("//th[contains(text(),'Situação das Licenç')]/ancestor::table//tr[td]");
        $licencas = [];
        foreach ($linhas as $i => $linha) {
            if ($i == 0) continue;
            $tds = $linha->getElementsByTagName('td');
            if ($tds->length >= 2) {
                $rota   = trim($tds->item(0)->nodeValue);
                $status = trim($tds->item(1)->nodeValue);
                if (!empty($rota)) { $licencas[] = "$rota: $status"; }
            }
        }
        $dados['situacao_licencas'] = implode(' | ', $licencas);
        return !empty($dados['placa']) ? $dados : null;
      
    }//end-of-onconsulta()
    /**
     * extrairDados()
     */
    public static function extrairDados($html)
    {

    }//end-of-extrairDados()
    
}//end-of-class
