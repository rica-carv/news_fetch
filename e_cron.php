<?php

if (!defined('e107_INIT')) {
    require_once '../../class2.php';
}

//require_once e_PLUGIN . 'news_fetch/handlers/news_fetch_class.php';

class news_fetch_cron
{
/*
    protected $db;
    protected $log;

    public function __construct()
    {
        $this->db  = e107::getDb();
        $this->log = e107::getLog();
    }
*/
    public function config()
    {
        $cron = [];
    
        $cron[] = [
            'name'        => "Importação automática de notícias",
            'function'    => "news_fetch", // método que irá correr
            'category'    => 'content',    // categoria para o admin
            'description' => "Importa conteúdos de fontes RSS ou scraping definidas em 'news_fetch'.",
            'interval'    => e107::pref('news_fetch', 'cron_interval', 3600)  // em segundos (opcional, mas recomendado)
        ];
    
        return $cron;
    }


public function cron_fetch($rowdata = null)
{
    require_once e_PLUGIN . 'news_fetch/handlers/news_fetch_class.php';
//    $helper = new news_fetch_helper($this->db, $this->log);
    return (new news_fetch_helper)->news_fetch();
}

}

// Execução local/manual para testes
if (php_sapi_name() === 'cli' || in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1'])) { 
    (new news_fetch_cron)->cron_fetch();
//    (new news_fetch_helper)->news_fetch();
}
