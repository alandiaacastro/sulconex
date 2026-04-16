CREATE TABLE clientes ( 
    id INTEGER PRIMARY KEY NOT NULL, 
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
    id INTEGER PRIMARY KEY NOT NULL, 
    data_emissao date, 
    permisso varchar(6), 
    pais_destino text, 
    numero varchar(11), 
    fatura_crt text, 
    status_crt_id INTEGER, 
    remetente_id INTEGER, 
    nome_remetente varchar(100), 
    endereco_remetente text, 
    destinatario_id INTEGER, 
    endereco_destinatario text, 
    nome_destinatario varchar(100), 
    consignatario_id INTEGER, 
    nome_consignatario text, 
    endereco_consignatario text, 
    notificar_id INTEGER, 
    notificar_nome text, 
    notificar_endereco text, 
    pagador_id INTEGER, 
    endereco_transportador text, 
    local_emissao text, 
    local_responsabilidade text, 
    local_entrega text, 
    transportadores_sucessivos text, 
    quantidade_volumes INTEGER, 
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
    copiar INTEGER, 
    FOREIGN KEY (status_crt_id) REFERENCES status_crt (id), 
    FOREIGN KEY (remetente_id) REFERENCES clientes (id), 
    FOREIGN KEY (destinatario_id) REFERENCES clientes (id), 
    FOREIGN KEY (consignatario_id) REFERENCES clientes (id), 
    FOREIGN KEY (notificar_id) REFERENCES clientes (id), 
    FOREIGN KEY (pagador_id) REFERENCES clientes (id)
 );

CREATE TABLE enlastre ( 
    id INTEGER PRIMARY KEY NOT NULL, 
    dta_emissao date, 
    numeroenlastre INTEGER, 
    nometransporte varchar(100), 
    trator varchar(6), 
    semi varchar(6), 
    motoristaedoc varchar(50), 
    transportadora varchar(300), 
    cnpj varchar(14), 
    enlastre_id INTEGER, 
    FOREIGN KEY (enlastre_id) REFERENCES registro (id)
 );

CREATE TABLE faturacobranca ( 
    id INTEGER PRIMARY KEY NOT NULL, 
    numero text, 
    emissao date, 
    cliente_id INTEGER, 
    vencimento date, 
    conhecimento INTEGER, 
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
    obs text, 
    FOREIGN KEY (cliente_id) REFERENCES clientes (id), 
    FOREIGN KEY (conhecimento) REFERENCES conhecimento (id)
 );

CREATE TABLE motoristas ( 
    id INTEGER PRIMARY KEY NOT NULL, 
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
    id INTEGER PRIMARY KEY NOT NULL, 
    codigo varchar(6), 
    pais_destino varchar(20), 
    sequenciacrt INTEGER, 
    transportadora text, 
    logo text, 
    enlastre INTEGER, 
    nometransportadora varchar(100), 
    cnpj varchar(14)
 );

CREATE TABLE sistemaimagens ( 
    id INTEGER PRIMARY KEY NOT NULL, 
    nome varchar(10), 
    imagem text
 );

CREATE TABLE status_crt ( 
    id INTEGER PRIMARY KEY NOT NULL, 
    nome varchar(20), 
    cor varchar(50)
 );







CREATE INDEX registro_id_idx ON registro (id);