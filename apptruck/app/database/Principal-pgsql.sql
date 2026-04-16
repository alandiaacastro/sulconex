CREATE TABLE clientes ( 
    id serial PRIMARY KEY NOT NULL, 
    nome character varying(100) NOT NULL, 
    inscricao_estadual character varying(40), 
    cnpj character varying(40), 
    cidade character varying(100), 
    estado character varying(2), 
    email character varying(40), 
    telefone character varying(20), 
    endereco character varying(200), 
    cep character varying(20), 
    atividade character varying(200), 
    dados_crt text
 );

CREATE TABLE conhecimento ( 
    id serial PRIMARY KEY NOT NULL, 
    data_emissao date, 
    permisso character varying(6), 
    pais_destino text, 
    numero character varying(11), 
    fatura_crt text, 
    status_crt_id integer, 
    remetente_id integer, 
    nome_remetente character varying(100), 
    endereco_remetente text, 
    destinatario_id integer, 
    endereco_destinatario text, 
    nome_destinatario character varying(100), 
    consignatario_id integer, 
    nome_consignatario text, 
    endereco_consignatario text, 
    notificar_id integer, 
    notificar_nome text, 
    notificar_endereco text, 
    pagador_id integer, 
    endereco_transportador text, 
    local_emissao text, 
    local_responsabilidade text, 
    local_entrega text, 
    transportadores_sucessivos text, 
    quantidade_volumes integer, 
    descricao_mercadoria text, 
    peso_bruto_kg double precision, 
    peso_liq_kg double precision, 
    volume_m3 text, 
    incoterm character varying(4), 
    incoterm16 character varying(4), 
    valor_mercadorias double precision, 
    moeda_valor_mercadorias text, 
    valor_declarado double precision, 
    documentos_anexos text, 
    nome_transporte text, 
    nome_transportador text, 
    observacoes text, 
    instrucoes_alfandega text, 
    valor_reembolso double precision, 
    valor_frete_externo double precision, 
    moeda_frete_externo text, 
    textogasto1 text, 
    textogasto2 text, 
    textogasto3 text, 
    custoremetente1 double precision, 
    custoremetente2 double precision, 
    custoremetente3 double precision, 
    custodestino1 double precision, 
    custodestino2 double precision, 
    custodestino3 double precision, 
    gastosmoeda text, 
    total_custo_destinatario double precision, 
    total_custo_remetente double precision, 
    valorfaturausd double precision, 
    assinatura_nome text, 
    porteador text, 
    especie_vol text, 
    fatura_brl double precision, 
    fatura_usd double precision, 
    nome_pagador text, 
    copiar integer
 );

CREATE TABLE enlastre ( 
    id serial PRIMARY KEY NOT NULL, 
    dta_emissao date, 
    numeroenlastre integer, 
    nometransporte character varying(100), 
    trator character varying(6), 
    semi character varying(6), 
    motoristaedoc character varying(50), 
    transportadora character varying(300), 
    cnpj character varying(14), 
    enlastre_id integer
 );

CREATE TABLE faturacobranca ( 
    id serial PRIMARY KEY NOT NULL, 
    numero text, 
    emissao date, 
    cliente_id integer, 
    vencimento date, 
    conhecimento integer, 
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
    id serial PRIMARY KEY NOT NULL, 
    cnh_numero character varying(20), 
    data_emissao_cnh date, 
    data_validade_cnh date, 
    categoria text, 
    registro_num character varying(20), 
    nome character varying(100), 
    data_nascimento date, 
    local_nascimento character varying(50), 
    cpf character varying(14), 
    rg_numero character varying(20), 
    rg_emissor character varying(30), 
    rg_uf character varying(2), 
    filiacao_pai character varying(100), 
    filiacao_mae character varying(100)
 );

CREATE TABLE registro ( 
    id serial PRIMARY KEY NOT NULL, 
    codigo character varying(6), 
    pais_destino character varying(20), 
    sequenciacrt integer, 
    transportadora text, 
    logo text, 
    enlastre integer, 
    nometransportadora character varying(100), 
    cnpj character varying(14)
 );

CREATE TABLE sistemaimagens ( 
    id serial PRIMARY KEY NOT NULL, 
    nome character varying(10), 
    imagem text
 );

CREATE TABLE status_crt ( 
    id serial PRIMARY KEY NOT NULL, 
    nome character varying(20), 
    cor character varying(50)
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