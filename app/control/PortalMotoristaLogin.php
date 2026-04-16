<?php

class PortalMotoristaLogin extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        if (TSession::getValue('portal_motorista_logged')) {
            AdiantiCoreApplication::gotoPage('PortalMotoristaHome');
            return;
        }

        $cadastroUrl = htmlspecialchars(PortalMotoristaSupportService::buildApplicationUrl('index.php', ['class' => 'PortalMotoristaSolicitarCadastro']), ENT_QUOTES, 'UTF-8');
        $adminUrl = htmlspecialchars(PortalMotoristaSupportService::buildApplicationUrl('index.php', ['class' => 'LoginForm']), ENT_QUOTES, 'UTF-8');

        $style = new TElement('style');
        $style->add('
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap");
            html, body {
                margin: 0; padding: 0; min-height: 100vh;
                font-family: "Inter", system-ui, sans-serif;
                background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
            }
            .pm-bg {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
            }
            .pm-bg::before {
                content: "";
                position: absolute;
                top: -10%; right: -5%;
                width: 40vw; height: 40vw;
                background: radial-gradient(circle, rgba(79,70,229,0.1) 0%, transparent 70%);
                border-radius: 50%;
                z-index: 0;
            }
            .pm-bg::after {
                content: "";
                position: absolute;
                bottom: -10%; left: -5%;
                width: 40vw; height: 40vw;
                background: radial-gradient(circle, rgba(16,185,129,0.05) 0%, transparent 70%);
                border-radius: 50%;
                z-index: 0;
            }
            .pm-login-wrap {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                padding: 24px 16px;
                box-sizing: border-box;
                z-index: 1;
            }
            .pm-login-card {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 24px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05);
                border: 1px solid rgba(255, 255, 255, 0.6);
                padding: 48px 40px 40px;
                width: 100%;
                max-width: 420px;
            }
            .pm-login-logo {
                text-align: center;
                margin-bottom: 32px;
            }
            .pm-login-logo .icon-wrap {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 64px; height: 64px;
                background: linear-gradient(135deg, #4F46E5 0%, #3730A3 100%);
                border-radius: 16px;
                margin-bottom: 16px;
                box-shadow: 0 10px 20px rgba(79,70,229,0.3);
            }
            .pm-login-logo i {
                font-size: 1.8rem;
                color: #fff;
            }
            .pm-login-logo h2 {
                font-size: 1.6rem;
                font-weight: 800;
                color: #0F172A;
                margin: 0 0 4px;
                letter-spacing: -0.02em;
            }
            .pm-login-logo p {
                font-size: .95rem;
                color: #64748B;
                margin: 0;
            }
            .pm-input-group {
                position: relative;
                margin-bottom: 20px;
            }
            .pm-input-group i {
                position: absolute;
                left: 16px;
                top: 50%;
                transform: translateY(-50%);
                color: #94A3B8;
                font-size: 1.1rem;
                pointer-events: none;
                transition: color 0.3s;
            }
            .pm-input-group input {
                width: 100%;
                padding: 14px 16px 14px 44px;
                border: 1px solid #E2E8F0;
                border-radius: 12px;
                font-size: 1rem;
                color: #0F172A;
                transition: all 0.3s ease;
                box-sizing: border-box;
                background: #F8FAFC;
                font-family: "Inter", sans-serif;
            }
            .pm-input-group input:focus {
                outline: none;
                border-color: #4F46E5;
                box-shadow: 0 0 0 4px rgba(79,70,229,0.15);
                background: #fff;
            }
            .pm-input-group input:focus + i {
                color: #4F46E5;
            }
            .pm-input-group input::placeholder { color: #94A3B8; }
            .pm-btn-entrar {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #4F46E5 0%, #4338CA 100%);
                color: #fff;
                border: none;
                border-radius: 12px;
                font-size: 1.05rem;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-top: 8px;
                box-shadow: 0 8px 16px rgba(79,70,229,0.25);
            }
            .pm-btn-entrar:hover  {
                transform: translateY(-2px);
                box-shadow: 0 12px 20px rgba(79,70,229,0.3);
            }
            .pm-btn-entrar:active { transform: translateY(1px); }
            .pm-login-link {
                display: block;
                text-align: center;
                margin-top: 16px;
                color: #4F46E5;
                text-decoration: none;
                font-weight: 700;
            }
            .pm-login-back {
                text-align: center;
                margin-top: 18px;
                font-size: .9rem;
                color: #64748B;
            }
            .pm-login-back a {
                color: #4F46E5;
                text-decoration: none;
                font-weight: 600;
                transition: color 0.3s;
            }
            .pm-login-back a:hover,
            .pm-login-link:hover { color: #3730A3; }
        ');

        $this->form = new BootstrapFormBuilder('form_portal_login');
        $this->form->style = 'display:none';
        $telefone = new TEntry('telefone');
        $senha = new TPassword('senha_portal');
        $this->form->addFields([$telefone]);
        $this->form->addFields([$senha]);
        $this->form->addAction('Entrar', new TAction([$this, 'onLogin']), '');

        $html = new TElement('div');
        $html->class = 'pm-bg';
        $html->add('
            <div class="pm-login-wrap">
                <div class="pm-login-card">
                    <div class="pm-login-logo">
                        <div class="icon-wrap">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h2>Portal do Motorista</h2>
                        <p>Acesse com seu telefone e senha</p>
                    </div>
                    <div class="pm-input-group">
                        <input type="tel" id="pm_telefone" name="pm_telefone"
                               placeholder="(00) 00000-0000" maxlength="15"
                               oninput="pmMaskPhone(this)" autocomplete="off" />
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="pm-input-group">
                        <input type="password" id="pm_senha" name="pm_senha"
                               placeholder="Senha" autocomplete="off" />
                        <i class="fas fa-lock"></i>
                    </div>
                    <button class="pm-btn-entrar" onclick="pmDoLogin(); return false;">
                        Entrar &nbsp;<i class="fas fa-arrow-right"></i>
                    </button>
                    <a href="' . $cadastroUrl . '" class="pm-login-link">Novo motorista ou solicitar cadastro</a>
                    <div class="pm-login-back">
                        <a href="' . $adminUrl . '"><i class="fas fa-arrow-left"></i> Voltar ao sistema</a>
                    </div>
                </div>
            </div>
        ');

        $script = new TElement('script');
        $script->add('
            function pmMaskPhone(el) {
                var v = el.value.replace(/\D/g,"");
                if (v.length > 11) v = v.substr(0,11);
                if (v.length > 7)      v = "("+v.substr(0,2)+")"+v.substr(2,5)+"-"+v.substr(7);
                else if (v.length > 2) v = "("+v.substr(0,2)+")"+v.substr(2);
                el.value = v;
            }
            function pmDoLogin() {
                var tel = document.getElementById("pm_telefone").value;
                var sen = document.getElementById("pm_senha").value;
                document.querySelector("#form_portal_login input[name=telefone]").value = tel;
                document.querySelector("#form_portal_login input[name=senha_portal]").value = sen;
                __adianti_post_data("form_portal_login",
                    "class=PortalMotoristaLogin&method=onLogin&static=1");
            }
            document.addEventListener("keydown", function(e){
                if (e.key === "Enter") pmDoLogin();
            });
        ');

        $wrap = new TVBox;
        $wrap->style = 'width:100%';
        $wrap->add($style);
        $wrap->add($this->form);
        $wrap->add($html);
        $wrap->add($script);

        parent::add($wrap);
    }

    public static function onLogin($param)
    {
        try {
            PortalMotoristaAuthService::login(
                (string) ($param['telefone'] ?? ''),
                (string) ($param['senha_portal'] ?? '')
            );

            AdiantiCoreApplication::gotoPage('PortalMotoristaHome');
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Throwable $rollbackError) {
            }
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onLogout()
    {
        TSession::setValue('portal_motorista_logged', false);
        TSession::setValue('portal_motorista_id', null);
        TSession::setValue('portal_motorista_nome', null);
        AdiantiCoreApplication::gotoPage('PortalMotoristaLogin');
    }
}
