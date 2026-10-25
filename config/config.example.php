<?php
// Copy this file to config.local.php and fill in your database details.
// config.local.php is ignored by git. Never commit real credentials.
return [
    'db_host' => 'localhost',
    'db_user' => 'your_database_user',
    'db_pass' => 'your_database_password',
    'db_name' => 'your_database_name',
    'db_port' => 3306,

    // Email. Use 'log' on your own computer (messages go to logs/mail.log).
    // On a live host use 'smtp' (best on free hosts) or 'mail'. Set app_url and mail_from too.
    'mail_mode' => 'log',
    'mail_from' => 'no-reply@example.com', // with Gmail this must be the same address as smtp_user
    'app_url' => '', // for example https://medxpert.example.com

    // Only for mail_mode 'smtp'. Gmail needs 2-step verification and an App Password.
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls', // tls (587), ssl (465) or none
    'smtp_user' => '',
    'smtp_pass' => '', // the 16 character App Password, not your normal password
];
