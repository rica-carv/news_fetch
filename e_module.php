<?php

////require_once('../../class2.php');
if (!defined('e107_INIT')) exit;

require_once(e_PLUGIN . 'news_fetch/handlers/news_fetch_class.php');

class news_fetch_runner
{
//    protected $news;
    protected $db;
    protected $log;
    public function __construct()
    {
//        $this->news = new news;
        $this->db   = e107::getDb();
        $this->log   =e107::getLog();
    }

    public function run()
    {
//        $now  = time();
        $rows = $this->db->retrieve('news_fetch', '*', 'src_active=1', true);

        foreach ($rows as $row) {
/*
            $lastRun = (int) $row['src_last_run'];
            if ($now - $lastRun < NEWSFETCH_CRON_INTERVAL) {
                continue;
            }
*/
            $this->processRow($row);
        }
    }

    protected function processRow($row)
    {
        if (news_fetch_helper::is_rss($row['src_url'])) {
            $this->processRSS($row);
        } else {
            $this->processScraping($row);
        }
    }

    protected function processRSS($row)
    {
        $content = news_fetch_helper::fetch_url($row['src_url']);
        if (!$content) {
            news_fetch_helper::log("Falhou ao obter feed RSS: " . $row['src_url'], 'ERROR');
            return;
        }

        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($content);
        if (!$rss || empty($rss->channel->item[0])) {
            news_fetch_helper::log("RSS inválido: " . $row['src_url'], 'ERROR');
            return;
        }

        $item    = $rss->channel->item[0];
        $row['fullUrl'] = (string) $item->link;

        if ($row['fullUrl'] === trim($row['src_last_url'] ?? '')) {
            return;
        }

        $row['title']     = (string) $item->title;
        $row['datestamp'] = strtotime((string) $item->pubDate) ?: time();
        $row['body']      = (string) $item->description;

        /*
        $news = [
            'news_title'     => $title,
            'news_body'      => $body,
            'news_datestamp' => $datestamp,
            'news_author'    => USERID ?: 1,
            'news_category'  => (int) $row['src_cat'],
            'news_class'     => 255
        ];

        if ($this->news->submit_item($news)) {
            $this->db->update('news_fetch', [
                'src_last_url' => $row['fullUrl'],
                'src_last_run' => time()
            ], "id=" . (int) $row['id']);

            news_fetch_helper::log("Importado RSS: {$title}");
        }
        */
        $this->submitnews($row, 'RSS');
    }







    protected function processScraping($row)
    {
        $html = news_fetch_helper::fetch_url($row['src_url']);
        if (!$html) {
            $this->log->add('news_fetch', "Erro: HTML vazio ao obter {$row['src_url']}", E_LOG_WARNING);
            return;
        }
    
        // Extrair link do artigo
        $link = news_fetch_helper::apply_xpath($html, $row['src_xpath_link']);
        if (empty($link)) {
            $this->log->add('news_fetch', "Erro: XPath do link falhou ({$row['src_xpath_link']})", E_LOG_WARNING);
            return;
        }
    
        $row['fullUrl'] = news_fetch_helper::resolve_url($row['src_url'], $link);
        if (empty($row['fullUrl'])) {
            $this->log->add('news_fetch', "Erro ao compor URL final a partir de '{$link}'", E_LOG_WARNING);
            return;
        }
    
        if ($row['fullUrl'] === trim($row['src_last_url'] ?? '')) {
            $this->log->add('news_fetch', "Ignorado (já importado): {$row['fullUrl']}", E_LOG_INFORMATIVE);
            return;
        }
    
        // Obter HTML do artigo
        $articleHtml = news_fetch_helper::fetch_url($row['fullUrl']);
        if (!$articleHtml) {
            $this->log->add('news_fetch', "Erro ao obter conteúdo do artigo {$row['fullUrl']}", E_LOG_ERROR);
            return;
        }
    
        // Extrair dados com XPath
        $row['title'] = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_title']);
        $row['body']  = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_body'], true);
    
        if (empty($row['title']) || empty($row['body'])) {
            $this->log->add('news_fetch', "Erro: Falha ao extrair título ou corpo.", E_LOG_WARNING);
            return;
        }
    
        // Extrair imagem (se houver XPath)
        if (!empty($row['src_xpath_img'])) {
            $imgUrl = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_img']);
            $imgUrl = news_fetch_helper::resolve_url($row['fullUrl'], $imgUrl);
    
            if ($imgUrl && preg_match('#^https?://#', $imgUrl)) {
                // Download imagem via cURL para ficheiro temporário
//                $tmp = tempnam(e_TEMP, 'img_');




                $ch = curl_init($imgUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0',
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                $imageData = curl_exec($ch);
                curl_close($ch);
    
//                var_dump($imageData);
                if ($imageData) {
                    $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                    $filename = 'newsfetch_' . md5($imgUrl) . '.' . $ext;
                    
//                    $folder     = 'news_fetch';
//                    $filename   = 'newsfetch_' . md5($imageUrl) . '.jpg';
//                    $tempFolder = e_TEMP . $folder . '/';
                    $tempFolder = e_MEDIA."images/".date("Y-m");

                    if (!is_dir($tempFolder)) {
                        mkdir($tempFolder, 0755, true);
                    }
//var_dump($tempFolder);
                    
                    $tmp = $tempFolder."/".$filename;
                    
                    file_put_contents($tmp, $imageData);
    
                    // Verificar se é imagem válida
                    if (getimagesize($tmp)) {
//                        $media = e107::getMedia();
//                        $media->import('news', $tmp, '[a-zA-z0-9_-]+\.(tmp)$', ['overwrite' => true]);
/////                        $media->import('news', $tmp);
                        e107::getMedia()->import('news', $tempFolder);
                        $row['image']=e107::getParser()->createConstants($tmp,1);
//                        var_dump($tmp);
//var_dump($tmp);
//var_dump($row['image']);

/*
                        var_dump($res);
                        if (!empty($res[0]['media_url'])) {
                            $localImg = '{e_MEDIA}' . $res[0]['media_url'];
//                            $row['body'] .= "\n\n<img src='{$localImg}' class='img-responsive' alt='' />";
                            $row['image'] = $localImg;
                        } else {
                            $this->log->add('news_fetch', "Falha ao importar imagem: $imgUrl", E_LOG_WARNING);
                        }
*/
                    } else {
                        unlink($tmp);
                        $this->log->add('news_fetch', "Imagem inválida: $imgUrl", E_LOG_WARNING);
                        return;
                    }
//                   unlink($tmp);
                } else {
                    $this->log->add('news_fetch', "Erro ao fazer download da imagem: $imgUrl", E_LOG_WARNING);
                    return;
                }
            }
        }
    
        // Submeter notícia
        $this->submitnews($row);
    }
    





    protected function submitNews($row, $what = 'feita por scraping')
{
    require_once(e_HANDLER . 'news_class.php');

    $news = [
        'news_title'     => $row['title'],
        'news_body'      => $row['body'],
        'news_datestamp' => $row['datestamp']?:time(),
        'news_author'    => USERID ?: 1,
        'news_category'  => (int) $row['src_cat'],
        'news_class'     => 255
    ];

    if (!empty($row['image'])) {
        $news['news_thumbnail'] = $row['image'];
//        $news['news_image']     = $row['image'];
    }

    if ((new news)->submit_item($news)) {
        $now = time();
        $this->db->update('news_fetch', "src_last_url='{$row['fullUrl']}', src_last_run={$now} WHERE id=".(int)$row['id']);
        $this->log->add('news_fetch', "Importação {$what}: {$row['title']}", E_LOG_INFORMATIVE);
    } else {
        $this->log->add('news_fetch', "Erro ao submeter artigo: {$row['title']}", E_LOG_FATAL);
    }
}

}

// Execução condicional

define('NEWSFETCH_CRON_INTERVAL', 3600);
//echo "<hr>»»»";
//var_dump(isset($_GET['cron']) && $_GET['cron'] == 1); 
//echo "«««<hr>";
if ((isset($_GET['cron']) && $_GET['cron'] == 1) || (in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']))) {
    (new news_fetch_runner)->run();
}
