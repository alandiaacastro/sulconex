<?php

class PortalMotoristaSolicitarCadastro extends TPage
{
    public function __construct()
    {
        parent::__construct();

        $portalLoginUrl = htmlspecialchars(PortalMotoristaSupportService::buildApplicationUrl('index.php', ['class' => 'PortalMotoristaLogin']), ENT_QUOTES, 'UTF-8');
        $adminLoginUrl = htmlspecialchars(PortalMotoristaSupportService::buildApplicationUrl('index.php', ['class' => 'LoginForm']), ENT_QUOTES, 'UTF-8');

        $style = new TElement('style');
        $style->add('
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap");
            html, body {
                margin: 0;
                padding: 0;
                min-height: 100vh;
                font-family: "Inter", system-ui, sans-serif;
                background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
            }
            .pm-request-bg {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px 16px;
                box-sizing: border-box;
            }
            .pm-request-card {
                width: 100%;
                max-width: 720px;
                background: rgba(255,255,255,0.96);
                border-radius: 28px;
                box-shadow: 0 24px 48px rgba(15, 23, 42, 0.12);
                border: 1px solid rgba(148, 163, 184, 0.22);
                padding: 40px 36px;
                color: #0F172A;
            }
            .pm-request-eyebrow {
                margin: 0 0 10px;
                color: #475569;
                text-transform: uppercase;
                letter-spacing: .12em;
                font-size: .75rem;
                font-weight: 800;
            }
            .pm-request-card h1 {
                margin: 0 0 12px;
                font-size: 2rem;
                line-height: 1.1;
            }
            .pm-request-copy {
                margin: 0 0 22px;
                font-size: 1rem;
                line-height: 1.7;
                color: #475569;
            }
            .pm-request-alert {
                margin-bottom: 20px;
                padding: 14px 16px;
                border-radius: 16px;
                background: #EFF6FF;
                color: #1D4ED8;
                border: 1px solid #BFDBFE;
                font-weight: 600;
            }
            .pm-request-grid {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                margin: 0 0 24px;
            }
            .pm-request-item {
                padding: 16px 18px;
                border-radius: 18px;
                background: #F8FAFC;
                border: 1px solid #E2E8F0;
            }
            .pm-request-item strong {
                display: block;
                margin-bottom: 6px;
                font-size: .95rem;
            }
            .pm-request-item span {
                color: #64748B;
                font-size: .9rem;
                line-height: 1.55;
            }
            .pm-request-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 8px;
            }
            .pm-request-actions a {
                text-decoration: none;
                border-radius: 14px;
                padding: 13px 18px;
                font-weight: 700;
                transition: transform .18s ease, box-shadow .18s ease;
            }
            .pm-request-actions a:hover {
                transform: translateY(-1px);
            }
            .pm-request-primary {
                background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
                color: #fff;
                box-shadow: 0 12px 22px rgba(37, 99, 235, 0.24);
            }
            .pm-request-secondary {
                background: #E2E8F0;
                color: #0F172A;
            }
            @media (max-width: 720px) {
                .pm-request-card {
                    padding: 28px 22px;
                }
                .pm-request-grid {
                    grid-template-columns: 1fr;
                }
                .pm-request-card h1 {
                    font-size: 1.6rem;
                }
            }
        ');

        $html = new TElement('div');
        $html->class = 'pm-request-bg';
        $html->add(" 
            <div class='pm-request-card'>
                <p class='pm-request-eyebrow'>Portal do Motorista</p>
                <h1>Novo motorista ou solicitar cadastro</h1>
                <p class='pm-request-copy'>Se voce ainda nao recebeu seu acesso, o cadastro inicial precisa ser analisado e aprovado pela equipe da Sulconex antes da liberacao do portal.</p>
                <div class='pm-request-alert'>Depois da aprovacao, voce recebe uma senha temporaria e conclui o primeiro acesso no app.</div>
                <div class='pm-request-grid'>
                    <div class='pm-request-item'>
                        <strong>1. Envie seus dados</strong>
                        <span>Nome completo, CPF, telefone com WhatsApp e dados da CNH.</span>
                    </div>
                    <div class='pm-request-item'>
                        <strong>2. Informe o veiculo</strong>
                        <span>Placa do cavalo, semi-reboque e documentos do conjunto que vai operar.</span>
                    </div>
                    <div class='pm-request-item'>
                        <strong>3. Separe as fotos</strong>
                        <span>Porta do motorista, varanda do veiculo e lonas, junto dos documentos exigidos.</span>
                    </div>
                    <div class='pm-request-item'>
                        <strong>4. Aguarde a liberacao</strong>
                        <span>O administrativo revisa o cadastro e envia a senha temporaria para o primeiro acesso.</span>
                    </div>
                </div>
                <div class='pm-request-actions'>
                    <a href='{$portalLoginUrl}' class='pm-request-primary'>Voltar ao acesso do motorista</a>
                    <a href='{$adminLoginUrl}' class='pm-request-secondary'>Voltar ao sistema administrativo</a>
                </div>
            </div>
        ");

        $wrap = new TVBox;
        $wrap->style = 'width:100%';
        $wrap->add($style);
        $wrap->add($html);

        parent::add($wrap);
    }
}
