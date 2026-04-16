<?php
return [
    'general' =>  [
        'token' => '944df36f8e28c70662c606a3154953b317aadf2b597c89ff471cbb95a440cc5be71536760857b452',
        'creator_url' => 'https://studio.creator.com.br',
        'timezone' => 'America/Sao_Paulo',
        'language' => 'pt',
        'application' => 'app-truckdma',
        'title' => 'App Truck(dma)',
        'theme' => 'adminbs5',
        'seed' => '716403198685983ef09175ed1200c0dce83fcda6',
        'rest_key' => '',
        'multiunit' => '0',
        'public_view' => '0',
        'public_entry' => '',
        'debug' => '1',
        'multi_lang' => '0',
        'require_terms' => '0',
        'concurrent_sessions' => '1',
        'lang_options' => [
          'pt' => 'Português',
          'en' => 'English',
          'es' => 'Español',
        ],
        'multi_database' => '0',
        'validate_strong_pass' => '1',
        'notification_login' => '0',
        'welcome_message' => 'Have a great jorney!',
        'request_log_service' => 'SystemRequestLogService',
        'request_log' => '0',
        'request_log_types' => 'cli,web,rest',
        'strict_request' => '1'
        /*'password_renewal_interval' => '90',*/
    ],
    'recaptcha' => [
        'enabled' => '0',
        'key' => '',
        'secret' => ''
    ],
    'permission' =>  [
        'public_classes' => [
          'SystemRequestPasswordResetForm',
          'SystemPasswordResetForm',
          'SystemRegistrationForm',
          'SystemPasswordRenewalForm',
          'SystemConcurrentAccessView',
          
        ],
        'user_register' => '1',
        'reset_password' => '1',
        'default_groups' => '2',
        'default_screen' => '30',
        'default_units' => '1',
    ],
    'highlight' => [
        'comment' => '#808080',
        'default' => '#FFFFFF',
        'html' => '#C0C0C0',
        'keyword' => '#62d3ea',
        'string' => '#FFC472',
    ],
    'login' => [
        'logo' => '',
        'background' => ''
    ],
    'template' => [
        'navbar' => [
            'has_program_search' => '1',
            'has_notifications' => '1',
            'has_messages' => '1',
            'has_docs' => '1',
            'has_contacts' => '1',
            'has_support_form' => '1',
            'has_wiki' => '1',
            'has_news' => '1',
            'has_menu_mode_switch' => '1',
            'has_main_mode_switch' => '1',
            'has_master_menu' => '0',
            'always_collapse' => '0',
            'allow_page_tabs' => '0'
        ],
        'dialogs' => [
            'use_swal' => '1'
        ],
        'theme' => [
            'menu_dark_color' => '',
            'menu_mode'  => 'dark',
            'main_mode'  => 'light'
        ]
    ]
];
