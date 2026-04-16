CREATE TABLE clientes ( 
    id int PRIMARY KEY NOT NULL AUTO_INCREMENT, 
    nome varchar(100) NOT NULL, 
    inscricao_estadual varchar(40), 
    cnpj varchar(40), 
    cidade varchar(100), 
    estado varchar(2), 
    email varchar(40), 
    telefone varchar(20), 
    endereco varchar(200), 
    cep varchar(20), 
    atividade varchar(200), 
    dados_crt text
 );

CREATE TABLE conhecimento ( 
    id int PRIMARY KEY NOT NULL AUTO_INCREMENT, 
    data_emissao date, 
    permisso varchar(6), 
    pais_destino text, 
    numero varchar(11), 
    fatura_crt text, 
    status_crt_id int, 
    remetente_id int, 
    nome_remetente varchar(100), 
    endereco_remetente text, 
    destinatario_id int, 
    endereco_destinatario text, 
    nome_destinatario varchar(100), 
    consignatario_id int, 
    nome_consignatario text, 
    endereco_consignatario text, 
    notificar_id int, 
    notificar_nome text, 
    notificar_endereco text, 
    pagador_id int, 
    endereco_transportador text, 
    local_emissao text, 
    local_responsabilidade text, 
    local_entrega text, 
    transportadores_sucessivos text, 
    quantidade_volumes int, 
    descricao_mercadoria text, 
    peso_bruto_kg float, 
    peso_liq_kg float, 
    volume_m3 text, 
    incoterm varchar(4), 
    incoterm16 varchar(4), 
    valor_mercadorias float, 
    moeda_valor_mercadorias text, 
    valor_declarado float, 
    documentos_anexos text, 
    nome_transporte text, 
    nome_transportador text, 
    observacoes text, 
    instrucoes_alfandega text, 
    valor_reembolso float, 
    valor_frete_externo float, 
    moeda_frete_externo text, 
    textogasto1 text, 
    textogasto2 text, 
    textogasto3 text, 
    custoremetente1 float, 
    custoremetente2 float, 
    custoremetente3 float, 
    custodestino1 float, 
    custodestino2 float, 
    custodestino3 float, 
    gastosmoeda text, 
    total_custo_destinatario float, 
    total_custo_remetente float, 
    valorfaturausd float, 
    assinatura_nome text, 
    porteador text, 
    especie_vol text, 
    fatura_brl float, 
    fatura_usd float, 
    nome_pagador text, 
    copiar int
 );

CREATE TABLE enlastre ( 
    id int PRIMARY KEY NOT NULL AUTO_INCREMENT, 
    dta_emissao date, 
    numeroenlastre int, 
    nometransporte varchar(100), 
    trator varchar(6), 
    semi varchar(6), 
    motoristaedoc varchar(50), 
    transportadora varchar(300), 
    cnpj varchar(14), 
    enlastre_id int
 );

CREATE TABLE faturacobranca ( 
    id int PRIMARY KEY NOT NULL AUTO_INCREMENT, 
    numero text, 
    emissao date, 
    cliente_id int, 
    vencimento date, 
    conhecimento int, 
    notafiscal text, 
    descricao1 text, 
    descricao2 text, 
    descricao3 text, 
    valor1 numeric, 
    valor2 numeric, 
    valor3 numeric, 
    total numeric, 
    extenso text, 
    prod text, 
    obs text
 );

CREATE TABLE motoristas ( 
    id int PRIMARY KEY NOT NULL AUTO_INCREMENT, 
    cnh_numero varchar(20), 
    data_emissao_cnh date, 
    data_validade_cnh date, 
    categoria text, 
    registro_num varchar(20), 
    nome varchar(100), 
    data_nascimento date, 
    local_nascimento varchar(50), 
    cpf varchar(14), 
    rg_numero varchar(20), 
    rg_emissor varchar(30), 
    rg_uf varchar(2), 
    filiacao_pai varchar(100), 
    filiacao_mae varchar(100)
 );

CREATE TABLE registro ( 
    id int PRIMARY KEY NOT NULL AUTO_INCREMENT, 
    codigo varchar(6), 
    pais_destino varchar(20), 
    sequenciacrt int, 
    transportadora text, 
    logo text, 
    enlastre int, 
    nometransportadora varchar(100), 
    cnpj varchar(14)
 );

CREATE TABLE sistemaimagens ( 
    id int PRIMARY KEY NOT NULL AUTO_INCREMENT, 
    nome varchar(10), 
    imagem text
 );

CREATE TABLE status_crt ( 
    id int PRIMARY KEY NOT NULL AUTO_INCREMENT, 
    nome varchar(20), 
    cor varchar(50)
 );





ALTER TABLE conhecimento ADD CONSTRAINT conhecimento_status_crt_id_fkey FOREIGN KEY (status_crt_id) REFERENCES status_crt (id); 
ALTER TABLE conhecimento ADD CONSTRAINT conhecimento_remetente_id_fkey FOREIGN KEY (remetente_id) REFERENCES clientes (id); 
ALTER TABLE conhecimento ADD CONSTRAINT conhecimento_destinatario_id_fkey FOREIGN KEY (destinatario_id) REFERENCES clientes (id); 
ALTER TABLE conhecimento ADD CONSTRAINT conhecimento_consignatario_id_fkey FOREIGN KEY (consignatario_id) REFERENCES clientes (id); 
ALTER TABLE conhecimento ADD CONSTRAINT conhecimento_notificar_id_fkey FOREIGN KEY (notificar_id) REFERENCES clientes (id); 
ALTER TABLE conhecimento ADD CONSTRAINT conhecimento_pagador_id_fkey FOREIGN KEY (pagador_id) REFERENCES clientes (id); 
ALTER TABLE enlastre ADD CONSTRAINT enlastre_enlastre_id_fkey FOREIGN KEY (enlastre_id) REFERENCES registro (id); 
ALTER TABLE faturacobranca ADD CONSTRAINT faturacobranca_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES clientes (id); 
ALTER TABLE faturacobranca ADD CONSTRAINT faturacobranca_conhecimento_fkey FOREIGN KEY (conhecimento) REFERENCES conhecimento (id);

CREATE INDEX registro_id_idx ON registro (id);