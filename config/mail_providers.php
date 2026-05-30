<?php

return [
    'brevo' => [
        'name' => 'Brevo',
        'region' => 'EU',
        'host' => 'smtp-relay.brevo.com',
        'port' => 587,
        'encryption' => 'tls',
        'auth_method' => 'smtp_credentials', // Email as username, SMTP key as password
        'username_label' => 'SMTP Login Email',
        'password_label' => 'SMTP Key',
        'password_required' => true,
        'username_hint' => 'Your SMTP login email (not your Brevo account email)',
        'free_tier' => '300 emails/day',
        'documentation' => 'https://help.brevo.com/hc/en-us/articles/209467485',
        'get_api_key_url' => 'https://app.brevo.com/settings/keys/smtp',
    ],

    'mailjet' => [
        'name' => 'Mailjet',
        'region' => 'EU',
        'host' => 'smtp.mailjet.com',
        'port' => 587,
        'encryption' => 'tls',
        'auth_method' => 'api_key_secret', // API Key as username, Secret Key as password
        'username_label' => 'API Key',
        'password_label' => 'Secret Key',
        'password_required' => true,
        'free_tier' => '6,000 emails/month (200/day)',
        'documentation' => 'https://dev.mailjet.com/email/guides/send-api-v31/',
        'get_api_key_url' => 'https://app.mailjet.com/account/api_keys',
    ],

    'postmark' => [
        'name' => 'Postmark',
        'region' => 'US',
        'host' => 'smtp.postmarkapp.com',
        'port' => 587,
        'encryption' => 'tls',
        'auth_method' => 'api_token', // Server API Token as username
        'username_label' => 'Server API Token',
        'password_label' => 'Server API Token (optional)',
        'password_required' => false,
        'free_tier' => '100 emails/month (testing)',
        'documentation' => 'https://postmarkapp.com/developer/user-guide/send-email-with-smtp',
        'get_api_key_url' => 'https://account.postmarkapp.com/servers',
    ],

    'mailgun' => [
        'name' => 'Mailgun',
        'region' => 'US',
        'host' => 'smtp.mailgun.org',
        'port' => 587,
        'encryption' => 'tls',
        'auth_method' => 'smtp_credentials', // postmaster@domain.mailgun.org as username
        'username_label' => 'SMTP Username',
        'password_label' => 'SMTP Password',
        'password_required' => true,
        'username_hint' => 'postmaster@your-domain.mailgun.org',
        'free_tier' => '6,000 emails/month',
        'documentation' => 'https://documentation.mailgun.com/en/latest/user_manual.html#sending-via-smtp',
        'get_api_key_url' => 'https://app.mailgun.com/app/sending/domains',
    ],

    'smtp2go' => [
        'name' => 'SMTP2Go',
        'region' => 'Asia',
        'host' => 'mail.smtp2go.com',
        'port' => 587,
        'encryption' => 'tls',
        'auth_method' => 'smtp_credentials',
        'username_label' => 'SMTP Username',
        'password_label' => 'SMTP Password',
        'password_required' => true,
        'free_tier' => '1,000 emails/month',
        'documentation' => 'https://www.smtp2go.com/docs/',
        'get_api_key_url' => 'https://www.smtp2go.com/settings/',
    ],
];
