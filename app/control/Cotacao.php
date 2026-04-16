<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<style>
  @page { margin: 0.5cm; size: A4; }
  @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap');

  * { box-sizing: border-box; }

  body {
    margin: 0;
    padding: 0;
    background: #eef3f8;
    color: #12263a;
    font-family: 'Manrope', Arial, sans-serif;
    font-size: 8pt;
    line-height: 1.45;
  }

  .page-shell {
    background: #ffffff;
    border: 1px solid #dbe4ee;
    border-radius: 12px;
    overflow: hidden;
  }

  .header {
    background: linear-gradient(120deg, #0f2744 0%, #1e4d7c 100%);
    padding: 14px 18px;
  }

  .header-table,
  .client-table,
  .grid-table,
  .cost-table,
  .allowance-table,
  .terms-table,
  .notes-table {
    width: 100%;
    border-collapse: collapse;
  }

  .logo {
    height: 44px;
    display: block;
  }

  .brand-sub {
    margin-top: 4px;
    color: rgba(255,255,255,0.65);
    font-size: 6.5pt;
    letter-spacing: 1.6px;
    text-transform: uppercase;
  }

  .quote-id {
    text-align: right;
    vertical-align: middle;
  }

  .quote-id-label {
    color: rgba(255,255,255,0.72);
    font-size: 6pt;
    letter-spacing: 1.9px;
    text-transform: uppercase;
  }

  .quote-id-value {
    margin-top: 2px;
    color: #fff;
    font-size: 13.5pt;
    font-weight: 800;
    letter-spacing: 0.4px;
  }

  .client-bar {
    padding: 9px 18px;
    background: #f2f6fb;
    border-top: 1px solid rgba(255,255,255,0.15);
    border-bottom: 1px solid #dbe4ee;
  }

  .client-tag {
    color: #37516d;
    font-size: 6pt;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    font-weight: 700;
  }

  .client-name {
    margin-top: 1px;
    color: #102a43;
    font-size: 9.2pt;
    font-weight: 800;
  }

  .client-msg {
    text-align: right;
    color: #5a6c80;
    font-size: 7.2pt;
    font-style: italic;
  }

  .content {
    padding: 10px 12px 12px;
  }

  .card {
    border: 1px solid #dbe4ee;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 9px;
  }

  .card-title {
    background: linear-gradient(90deg, #173a5e 0%, #25598a 100%);
    color: #fff;
    font-size: 6.8pt;
    font-weight: 800;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    padding: 5px 9px;
  }

  .field-td {
    border: 1px solid #e3eaf2;
    padding: 6px 9px;
    vertical-align: top;
    background: #fff;
  }

  .field-td.alt {
    background: #f8fbff;
  }

  .field-label {
    color: #647b95;
    font-size: 5.8pt;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 2px;
  }

  .field-value {
    color: #102a43;
    font-size: 8.5pt;
    font-weight: 700;
  }

  .cost-desc {
    border: 1px solid #e3eaf2;
    background: #f7fafe;
    padding: 9px 12px;
    color: #102a43;
    font-size: 8.4pt;
    font-weight: 700;
  }

  .cost-value {
    border: 1px solid #e3eaf2;
    width: 165px;
    text-align: right;
    padding: 9px 12px;
    background: #123252;
    color: #fff;
  }

  .cost-value.usd {
    width: 145px;
    background: #1f4a75;
  }

  .cost-caption {
    color: rgba(255,255,255,0.72);
    font-size: 5.8pt;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .cost-number {
    margin-top: 2px;
    font-size: 12.2pt;
    font-weight: 800;
  }

  .allowance-td {
    border: 1px solid #e3eaf2;
    width: 20%;
    text-align: center;
    padding: 7px 6px;
  }

  .allowance-td:nth-child(odd) { background: #f8fbff; }

  .allowance-label {
    color: #647b95;
    font-size: 5.7pt;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 2px;
  }

  .allowance-value {
    color: #173a5e;
    font-size: 9.8pt;
    font-weight: 800;
  }

  .allowance-value-usd {
    color: #9a3f00;
    font-size: 9pt;
    font-weight: 800;
  }

  .term-key {
    border: 1px solid #e3eaf2;
    width: 24px;
    text-align: center;
    vertical-align: top;
    padding: 5px 7px;
    color: #173a5e;
    font-size: 7.5pt;
    font-weight: 800;
    background: #eff4fb;
  }

  .term-text {
    border: 1px solid #e3eaf2;
    padding: 5px 8px;
    font-size: 7.2pt;
    color: #2a3f55;
    line-height: 1.55;
  }

  .terms-table tr:nth-child(even) .term-text { background: #fafcff; }

  .warn-note {
    border: 1px solid #f8c76f;
    border-left: 3px solid #f0a01b;
    background: #fffaf0;
    padding: 7px 10px;
    font-size: 7.3pt;
    color: #7a4a00;
    line-height: 1.5;
  }

  .std-note {
    border: 1px solid #e3eaf2;
    background: #f8fbff;
    padding: 7px 10px;
    font-size: 7pt;
    color: #334c66;
    line-height: 1.55;
  }

  .footer {
    margin-top: 2px;
    background: linear-gradient(120deg, #0f2744 0%, #1e4d7c 100%);
    padding: 9px 12px;
    text-align: center;
  }

  .footer-main {
    color: #fff;
    font-size: 7.8pt;
    font-weight: 800;
    letter-spacing: 0.4px;
  }

  .footer-sub {
    margin-top: 3px;
    color: rgba(255,255,255,0.72);
    font-size: 6.2pt;
  }
</style>
</head>
<body>
  <div class="page-shell">
    <div class="header">
      <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <img src="/app/images/logobranco.png" class="logo" alt="SULCONEX"/>
            <div class="brand-sub">Logistica Internacional</div>
          </td>
          <td class="quote-id">
            <div class="quote-id-label">N. Cotacao</div>
            <div class="quote-id-value">{$Cotacao_ID}</div>
          </td>
        </tr>
      </table>
    </div>

    <div class="client-bar">
      <table class="client-table" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <div class="client-tag">Cliente</div>
            <div class="client-name">{$cliente_nome}</div>
          </td>
          <td class="client-msg">Agradecemos a oportunidade e apresentamos abaixo nossa oferta com base nos dados informados.</td>
        </tr>
      </table>
    </div>

    <div class="content">
      <div class="card">
        <div class="card-title">Logistica</div>
        <table class="grid-table" cellpadding="0" cellspacing="0">
          <tr>
            <td class="field-td" style="width:38%;">
              <div class="field-label">Local Coleta</div>
              <div class="field-value">{$local_coleta}</div>
            </td>
            <td class="field-td alt" style="width:38%;">
              <div class="field-label">Local Entrega</div>
              <div class="field-value">{$local_entrega}</div>
            </td>
            <td class="field-td" style="width:24%;">
              <div class="field-label">Aduana / Fronteira</div>
              <div class="field-value">{$Aduana_Fronteira}</div>
            </td>
          </tr>
          <tr>
            <td class="field-td alt" style="width:55%;">
              <div class="field-label">Mercadoria</div>
              <div class="field-value">{$mercadoria}</div>
            </td>
            <td class="field-td" style="width:25%;">
              <div class="field-label">Equipamento</div>
              <div class="field-value">{$equipamento}</div>
            </td>
            <td class="field-td alt" style="width:20%;">
              <div class="field-label">Taxa de Dolar Minima</div>
              <div class="field-value">{$taxa_dolar}</div>
            </td>
          </tr>
          <tr>
            <td class="field-td" colspan="3">
              <div class="field-label">Forma de Pagamento</div>
              <div class="field-value">{$observacoes}</div>
            </td>
          </tr>
        </table>
      </div>

      <div class="card">
        <div class="card-title">Composicao dos Custos</div>
        <table class="cost-table" cellpadding="0" cellspacing="0">
          <tr>
            <td class="cost-desc">FRETE - Rota Internacional FTL / Full Truckload</td>
            <td class="cost-value">
              <div class="cost-caption">Valor (R$)</div>
              <div class="cost-number">R$ {$valor_brl}</div>
            </td>
            <td class="cost-value usd">
              <div class="cost-caption">Valor (USD)</div>
              <div class="cost-number">USD {$valor_usd}</div>
            </td>
          </tr>
        </table>
      </div>

      <div class="card">
        <div class="card-title">Franquias Livres</div>
        <table class="allowance-table" cellpadding="0" cellspacing="0">
          <tr>
            <td class="allowance-td"><div class="allowance-label">Embarque</div><div class="allowance-value">24 H</div></td>
            <td class="allowance-td"><div class="allowance-label">Aduana Fronteira</div><div class="allowance-value">48 H</div></td>
            <td class="allowance-td"><div class="allowance-label">Aduana Destino</div><div class="allowance-value">48 H</div></td>
            <td class="allowance-td"><div class="allowance-label">Descarga</div><div class="allowance-value">24 H</div></td>
            <td class="allowance-td"><div class="allowance-label">Valor Estadia</div><div class="allowance-value-usd">USD 400,00</div></td>
          </tr>
        </table>
      </div>

      <div class="card">
        <div class="card-title">Condicoes Gerais para Embarques</div>
        <table class="terms-table" cellpadding="0" cellspacing="0">
          <tr><td class="term-key">a)</td><td class="term-text">Taxa do dolar da data da emissao do CRT, a qual nunca podera ser inferior a taxa minima especificada na cotacao.</td></tr>
          <tr><td class="term-key">b)</td><td class="term-text">Carga e Descarga: por conta do importador e/ou exportador.</td></tr>
          <tr><td class="term-key">c)</td><td class="term-text">Nao estao incluidas despesas com Aduanas, SENASA, MULTILOG.</td></tr>
          <tr><td class="term-key">d)</td><td class="term-text">Seguro RCTR-VI incluso, cobrindo perdas por acidentes durante o transporte e desaparecimento da carga com o veiculo transportador, comprovados por vistoria da companhia seguradora. A responsabilidade do transportador esta limitada ao valor constante da <b>FATURA COMERCIAL</b>. Demais coberturas estarao a cargo do embarcador/destinatario, conforme <b>INCOTERM</b> que norteia a operacao. <b>Avarias devem ser apontadas ainda sobre rodas na descarga.</b></td></tr>
          <tr><td class="term-key">e)</td><td class="term-text">Infracoes, mesmo que aplicadas a nos, por preenchimento incorreto ou imperfeicoes em nota fiscal de exportacao ou importacao, a cargo do cliente.</td></tr>
          <tr><td class="term-key">f)</td><td class="term-text">Havendo necessidade, o transbordo de carga e facultado a nossa companhia, por motivo estrategico ou para desonerar o contratante.</td></tr>
          <tr><td class="term-key">g)</td><td class="term-text">Programacao de embarque: por escrito, com <b>48h de antecedencia</b> para o e-mail.</td></tr>
        </table>
      </div>

      <div class="card">
        <div class="card-title">Observacoes Importantes</div>
        <table class="notes-table" cellpadding="0" cellspacing="0">
          <tr><td class="warn-note"><b>Atencao - Inverno Chileno:</b> Durante os meses de inverno, os fretes podem ser mais elevados e os prazos de transito podem ser impactados por eventuais fechamentos das passagens andinas (Cordilheira dos Andes) e outras adversidades climaticas da regiao.</td></tr>
          <tr><td class="std-note">O Cliente leu e compreendeu a proposta ora apresentada pela <b>Sulconexlog</b> e declara que sua aceitacao por escrito, por e-mail e/ou pelo inicio dos servicos, manifesta sua expressa aceitacao aos Termos e Condicoes contidos nesta Proposta. Esta proposta constitui o documento unico que regula os direitos e obrigacoes das partes com relacao a prestacao dos servicos, ficando expressamente invalido e revogado todo e qualquer entendimento ou ajuste porventura anteriormente ocorrido entre as partes.</td></tr>
        </table>
      </div>
    </div>

    <div class="footer">
      <div class="footer-main">SULCONEXLOG - Logistica Internacional</div>
      <div class="footer-sub">CNPJ 48.816.176/0001-42 | Av. Setembrino de Carvalho, 777 | Uruguaiana/RS</div>
      <div class="footer-sub">Cooperativa dos Transportadores de Cargas e Servicos Logisticos</div>
    </div>
  </div>
</body>
</html>
