<?php
// Bu dosyayı config.php adıyla kopyalayıp kendi bilgilerinizi girin.
return [
    'site_url' => 'https://alanadiniz.com',
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'u123456789_arkesoft',
    'db_user' => 'u123456789_admin',
    'db_pass' => '',
    // Kendiniz en az 32 karakterli rastgele bir değer yazın. Kurulum ekranında sorulur.
    'setup_token' => '',
    // İsteğe bağlı eski PHP mail göndericisi; .env BRIEF_FROM doluysa onu kullanır.
    // Güvenli sunucu ağacında SMTP .env dosyası arkesoft-private içinde tutulur.
    // Veritabanı bilgileri burada kalır. Posta bilgileri tamamlanınca bildirimler otomatik çalışır.
    'mail_from' => '',
    'debug' => false,
];
