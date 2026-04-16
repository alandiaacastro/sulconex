<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\THBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Util\TXMLBreadCrumb;

class EmailComposerView extends TPage
{
    protected $form;
    private $database = 'sample';

    public function __construct()
    {
        parent::__construct();

        $this->form = new TForm('form_email_composer');

        $opportunity_id = new TEntry('opportunity_id');
        $opportunity_id->setEditable(false);
        $opportunity_id->style = 'display:none;';

        $template_id = new TCombo('template_id');
        $template_id->setChangeAction(new TAction([__CLASS__, 'onSelectTemplate']));

        $subject = new TEntry('subject');
        $subject->setSize('100%');

        $body = new TText('body');
        $body->setSize('100%', 250);

        $template_variables_json = new TEntry('template_variables_json');
        $template_variables_json->style = 'display:none;';

        // Carregar templates
        try {
            TTransaction::open($this->database);
            $repo = new TRepository('EmailTemplate');
            $items = ['' => '-- Selecione um Modelo --'];
            foreach ($repo->load(new TCriteria()) as $template) {
                $items[$template->id] = $template->title;
            }
            $template_id->addItems($items);
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar templates: ' . $e->getMessage());
            if (TTransaction::isActive()) {
                TTransaction::rollback();
            }
        }

        $variables_container = new TElement('div');
        $variables_container->id = 'template_variables_fields_container';
        $variables_container->style = 'padding: 10px; border: 1px dashed #ccc; margin-top: 10px; min-height:50px;';

        $table = new TTable;
        $table->width = '100%';

        $table->addRowSet($opportunity_id);
        $table->addRowSet($template_variables_json);
        $table->addRowSet(new TLabel('Modelo de E-mail:'), $template_id);
        $table->addRowSet(new TLabel('Assunto:'), $subject);
        $table->addRowSet(new TLabel('Corpo da Mensagem:'), $body);
        $table->addRowSet(new TLabel('Variáveis do Modelo:'), $variables_container);

        $this->form->add($table);

        // Botão de envio
        $btn_send = TButton::create('send_mailto', [$this, 'onSendToEmailClient'], 'Abrir Cliente de E-mail', 'fa:envelope green');

        $hbox = new THBox;
        $hbox->style = 'margin-top: 20px';
        $hbox->add($btn_send);

        $panel = new TPanelGroup('Compor E-mail');
        $panel->add($this->form);
        $panel->addFooter($hbox);

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        if (is_file('menu.xml')) {
            $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        }
        $vbox->add($panel);

        parent::add($vbox);

        // ✅ Registrar os campos corretamente no formulário
        $this->form->setFields([
            $opportunity_id,
            $template_id,
            $subject,
            $body,
            $template_variables_json,
            $btn_send
        ]);
    }

    public static function onLoadFromOpportunity($param)
    {
        $data = new stdClass;
        $data->opportunity_id = $param['opportunity_id'] ?? '';
        $data->template_id = '';
        $data->subject = '';
        $data->body = '';
        $data->template_variables_json = '';
        TForm::sendData('form_email_composer', $data);
        TScript::create("$('#template_variables_fields_container').html('');");
    }

    public static function onSelectTemplate($param)
    {
        $data = new stdClass;
        $data->subject = '';
        $data->body = '';
        $data->template_variables_json = '';
        TScript::create("$('#template_variables_fields_container').html('');");

        try {
            TTransaction::open('sample');
            $template = new EmailTemplate($param['template_id']);

            $data->subject = $template->subject ?? '';
            $data->body = $template->body ?? '';
            $data->template_variables_json = $template->variables_json ?? '';

            $vars = json_decode($template->variables_json ?? '', true);
            if (is_array($vars)) {
                $html = '';
                foreach ($vars as $key => $label) {
                    $field_name = "var_{$key}";
                    $input = new TEntry($field_name);
                    $input->setSize('100%');
                    $input->setProperty('placeholder', $label);
                    TForm::addFieldTo('form_email_composer', $input);

                    $html .= "<div style='margin-bottom:10px'>";
                    $html .= "<label>{$label}:</label>";
                    $html .= $input->getContents();
                    $html .= "</div>";
                }
                $html = addslashes($html);
                TScript::create("$('#template_variables_fields_container').html('{$html}');");
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::isActive()) {
                TTransaction::rollback();
            }
        }

        TForm::sendData('form_email_composer', $data);
    }

    public function onSendToEmailClient($param)
    {
        try {
            TTransaction::open($this->database);

            $subject = $param['subject'] ?? '';
            $body    = $param['body'] ?? '';
            $email   = '';

            if (!empty($param['opportunity_id'])) {
                $opportunity = new Opportunity($param['opportunity_id']);
                $email = $opportunity->email;
                foreach ($opportunity->toArray() as $key => $val) {
                    $subject = str_replace("{{{$key}}}", $val, $subject);
                    $body    = str_replace("{{{$key}}}", $val, $body);
                }
            }

            if (!empty($param['template_variables_json'])) {
                $vars = json_decode($param['template_variables_json'], true);
                foreach ($vars as $key => $label) {
                    $val = $param["var_{$key}"] ?? '';
                    $subject = str_replace("{{{$key}}}", $val, $subject);
                    $body    = str_replace("{{{$key}}}", $val, $body);
                }
            }

            $mailto = "mailto:{$email}?subject=" . rawurlencode($subject) . "&body=" . rawurlencode($body);
            TScript::create("window.open('{$mailto}', '_blank');");

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::isActive()) {
                TTransaction::rollback();
            }
        }
    }
}
