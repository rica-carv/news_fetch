<?php
if (!defined('e107_INIT')) { exit; }

class news_fetch_dashboard
{
    public function status()
    {
/*
        $prefs = e107::pref('news_fetch');
        $sources = vartrue($prefs['sources'], []);
        $activeCount = 0;

        foreach ($sources as $src) {
            if (!empty($src[3])) {
                $activeCount++;
            }
        }
*/
        $activeCount =  e107::getDb()->select('news_fetch', '*', 'src_active=1');

        $out = [];

        $out[] = [
            'icon'  => "<i class='fa fa-rss'></i>",
            'title' => " Fontes ativas",
            'url'   => e_PLUGIN_ABS."news_fetch\admin_config.php",
            'total' => $activeCount
        ];

        return $out;
    }

    public function latest()
    {
        $logFile = e_LOG . 'log.log';
        $totalErros = 0;

        if (file_exists($logFile)) {
            $lines = file($logFile);
            foreach ($lines as $line) {
                if (stripos($line, 'news_fetch') !== false && stripos($line, 'falhou') !== false) {
                    $totalErros++;
                }
            }
        }

        $out[] = [
            'icon'  => "<i class='fa fa-exclamation-triangle text-danger'></i>",
            'title' => " Erros recentes",
            'url'   => e_ADMIN_ABS."plugin.php?plugin=news_fetch&mode=log&action=list",
            'total' => $totalErros
        ];

        return $out;
    }
}
