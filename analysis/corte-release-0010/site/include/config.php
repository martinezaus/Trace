<?php
    /**
     * --- CONFIGURACIONES ---
     * 
     * 
     */  
    
    DEFINE("hostBD","db");
    DEFINE("usuarioBD","root");
    DEFINE("claveBD","");
    DEFINE("nombreBD","trace_corte_test_20261009");


    // Ruta absoluta y consistente para Windows (evita construir desde __DIR__).
    DEFINE("cmysqldump", '/usr/bin/mysqldump');
    DEFINE("directorioCodigo", __DIR__);                 //DIRECTORY_SEPARATOR
    DEFINE("carpetaBackup", "/var/www/backups/");

    DEFINE("carpetaDescarga", carpetaBackup);

    DEFINE("LOG", __DIR__ . "/logs/");
    DEFINE("ZONA", "America/Argentina/Mendoza");

    DEFINE('GS_PATH', '/usr/bin/gs');