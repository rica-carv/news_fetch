<?php

if (!defined('e107_INIT')) {
    require_once('../../class2.php');
}

require_once(e_PLUGIN . 'news_fetch/handlers/news_fetch_class.php');

class news_fetch_cron
{
    protected $db;
    protected $log;

    public function __construct()
    {
        $this->db  = e107::getDb();
        $this->log = e107::getLog();
    }

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

    public function news_fetch($rowdata = null)
    {
        $rows = $rowdata ? array($rowdata) : $this->db->retrieve('news_fetch', '*', 'src_active=1', true);
//        var_dump ($rows);

        foreach ($rows as $row) {
//            var_dump ($row);
//            echo "<hr>";
            //            $this->processRow($row);
//        }
//    }

//    protected function processRow($row)
//    {
        if (news_fetch_helper::is_rss($row['src_url'])) {
//            $this->processRSS($row);
//        } else {
//            $this->processScraping($row);
//        }
//    }

//    protected function processRSS($row)
//    {
        $content = news_fetch_helper::fetch_url($row['src_url']);
        if (!$content) {
            $this->log->add('news_fetch', "Erro ao obter RSS: {$row['src_url']}", E_LOG_ERROR);
            return;
        }

        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($content);
        if (!$rss || empty($rss->channel->item[0])) {
            $this->log->add('news_fetch', "RSS inválido: {$row['src_url']}", E_LOG_ERROR);
            return;
        }

        $item = $rss->channel->item[0];
        $row['fullUrl']  = (string) $item->link;

        if ($row['fullUrl'] === trim($row['src_last_url'] ?? '')) {
            return; // já importado
        }

        $row['title']     = (string) $item->title;
        $row['body']      = (string) $item->description;
//        $row['datestamp'] = strtotime((string) $item->pubDate) ?: time();
        $row['datestamp'] = news_fetch_helper::parseDate((string) $item->pubDate);

        $lastresult=$this->submitNews($row, 'RSS');
//    }
} else {
//    var_dump ($row);

//    protected function processScraping($row)
//    {
if ($row['fullUrl'] && !$row['src_xpath_link']) {
    $sources[] = $row['fullUrl'];
} else {
        $html = news_fetch_helper::fetch_url($row['src_url']);
        if (!$html) {
            $this->log->add('news_fetch', "Erro: HTML vazio ao obter {$row['src_url']}", E_LOG_WARNING);
            return;
        }

        $sources = news_fetch_helper::apply_xpath($html, $row['src_xpath_link']);
        if (empty($sources)) {
            $this->log->add('news_fetch', "XPath falhou para link: {$row['src_xpath_link']}", E_LOG_WARNING);
            return;
        }
    }
        foreach ($sources as $link) {
            $row['fullUrl'] = news_fetch_helper::resolve_url($row['src_url'], $link);
            if (empty($row['fullUrl']) || $row['fullUrl'] === trim($row['src_last_url'] ?? '')) {
                $this->log->add('news_fetch', "Ignorado (já importado): {$row['fullUrl']}", E_LOG_INFORMATIVE);
                return;
            }

            $articleHtml = news_fetch_helper::fetch_url($row['fullUrl']);
            if (!$articleHtml) {
                $this->log->add('news_fetch', "Erro ao obter artigo: {$row['fullUrl']}", E_LOG_ERROR);
                return;
            }

            $row['title'] = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_title'])[0];
            $row['body']  = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_body'], true)[0];

            if (empty($row['title']) || empty($row['body'])) {
                $this->log->add('news_fetch', "Falha ao extrair título ou corpo", E_LOG_WARNING);
                return;
            }

            // Imagem
            if (!empty($row['src_xpath_img'])) {
                $imgUrllist = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_img']);

                foreach ($imgUrllist as $imgUrl) {
                    $imgUrl = news_fetch_helper::resolve_url($row['fullUrl'], $imgUrl);

                    $parts = parse_url($imgUrl);
                    if (!empty($parts['path'])) {
                        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $parts['path'])));
                        $query       = isset($parts['query']) ? '?' . $parts['query'] : '';
                        $imgUrl      = "{$parts['scheme']}://{$parts['host']}{$encodedPath}{$query}";
                    }

                    if ($imgUrl && preg_match('#^https?://#', $imgUrl)) {
                        $ch = curl_init($imgUrl);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_USERAGENT      => 'Mozilla/5.0',
                            CURLOPT_TIMEOUT        => 15,
                            CURLOPT_SSL_VERIFYPEER => false
                        ]);
                        $imageData = curl_exec($ch);
                        curl_close($ch);

                        if ($imageData) {
                            $ext        = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                            $filename   = 'newsfetch_' . md5($imgUrl) . '.' . $ext;
                            $mediaPath  = e_MEDIA . 'images/' . date('Y-m');
                            $targetFile = $mediaPath . '/' . $filename;

//                    if (!is_dir($mediaPath)) {
//                        mkdir($mediaPath, 0755, true);
//                    }
                            if (!is_dir($mediaPath) && !mkdir($mediaPath, 0755, true)) {
                                $this->log->add('news_fetch', "Erro ao criar pasta de imagem: $mediaPath", E_LOG_FATAL);
                                return;
                            }
    
                            file_put_contents($targetFile, $imageData);

                            if (getimagesize($targetFile)) {
                                e107::getMedia()->import('news', $mediaPath);
//                                $row['image'] = e107::getParser()->createConstants($targetFile, 1);
                                $imageList[] = e107::getParser()->createConstants($targetFile, 1);
                            } else {
                                unlink($targetFile);
                                $this->log->add('news_fetch', "Imagem inválida: $imgUrl", E_LOG_WARNING);
                                return;
                            }
                        }
                    }
                }

//                $row['image'] = e107::getParser()->createConstants($targetFile, 1);
                // Agora sim, cria o CSV no campo:
                if (!empty($imageList)) {
                    $row['image'] = implode(',', $imageList); // ou ';' se preferires
                }                
            }

// Data (se houver XPath configurado)
if (!empty($row['src_xpath_date'])) {
    $rawDate = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_date'])[0] ?? '';
    $row['datestamp'] = news_fetch_helper::parseDate($rawDate);
}
            
//            var_dump ($row);

            $lastresult=$this->submitNews($row);
            var_dump($lastresult);
            }
        }
    }
    return $rowdata ? $lastresult : null;
}
protected function submitNews($row, $modo = 'scraping')
{
//    $tp = e107::getParser();
//    $idCriado = null;
//    $table = '';

    if (!empty($row['src_submit_pending'])) 
    {
        // Grava em submitnews (pendente)
        $news = [
            'submitnews_name'      => USERNAME ?: 'newsfetch',
            'submitnews_email'     => USEREMAIL ?: '',
            'submitnews_subject'   => $row['title'],
            'submitnews_item'      => $row['body'],
            'submitnews_datestamp' => $row['datestamp'] ?? time(),
            'submitnews_category'  => (int) $row['src_cat'],
            'submitnews_file'      => $row['image'] ?? ''
        ];

//$idCriado = $this->db->insert('submitnews', $newsData);

//if($idCriado) 
//{
           $table = 'submitnews';
/*
// Atualiza quando insere em submitnews
$this->db->update(
    'news_fetch',
    "src_last_url='{$row['fullUrl']}', src_last_run=" . time() . " WHERE id=" . (int)$row['id']
);

            $this->log->add('news_fetch', "Submetido para aprovação: {$row['title']}", E_LOG_INFORMATIVE);
        } 
        else {
            $this->log->add('news_fetch', "Erro ao inserir submitnews: {$row['title']}", E_LOG_FATAL);
        }
    */
    } 
    else 
    {
        // Publicação direta
//        require_once(e_HANDLER . 'news_class.php');

        $news = [
            'news_title'     => $row['title'],
            'news_body'      => $row['body'],
            'news_datestamp' => $row['datestamp'] ?? time(),
            'news_author'    => USERID ?: 1,
            'news_category'  => (int) $row['src_cat'],
            'news_class'     => 255
        ];

        if (!empty($row['image'])) {
            $news['news_thumbnail'] = $row['image'];
        }


//        $idCriado = $this->db->insert('news', $news);

//        if($idCriado) {
        $table = 'news';
/*
// Atualiza quando insere direto em news
$this->db->update(
    'news_fetch',
    "src_last_url='{$row['fullUrl']}', src_last_run=" . time() . " WHERE id=" . (int)$row['id']
);
            $this->log->add('news_fetch', "Importado ({$modo}): {$row['title']}", E_LOG_INFORMATIVE);
        } 
        else {
            $this->log->add('news_fetch', "Erro ao submeter: {$row['title']}", E_LOG_FATAL);
        }
    */
    }

    if($idCriado = $this->db->insert($table, $news)){
//    if($idCriado) {
///        $table = 'news';
$this->db->update(
    'news_fetch',
    "src_last_url='{$row['fullUrl']}', 
     src_last_run=" . time() . ", 
     src_last_date=" . intval($row['datestamp']) . "
     WHERE id=" . (int)$row['id']
);
/*
$this->db->update(
'news_fetch',
"src_last_url='{$row['fullUrl']}', src_last_run=" . time() . " WHERE id=" . (int)$row['id']
);
*/
$this->log->add(
    'news_fetch',
    "Importado ({$modo}): {$row['title']} (Data Original: " . date('Y-m-d H:i', $row['datestamp']) . ")",
    E_LOG_INFORMATIVE
);
//        $this->log->add('news_fetch', "Importado ({$modo}): {$row['title']}", E_LOG_INFORMATIVE);
    } 
    else {
        $this->log->add('news_fetch', "Erro ao submeter: {$row['title']}", E_LOG_FATAL);
    }

    // ✅ Apenas mostra link no admin
//    if (ADMIN && $idCriado && $table) 
//var_dump ($table);
//var_dump ($idCriado);
//echo "<hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr>";

/*
    if (ADMIN && $idCriado) 
    {
        $url = e_ADMIN."newspost.php?action=edit&id={$idCriado}";
        e107::getMessage()->addSuccess("Notícia criada com sucesso: <a href='{$url}' target='_blank'>Editar notícia</a>");
    }

    */

    /*var_dump ($table);
var_dump ($idCriado);
echo "<hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr>";
return array($table, $idCriado);
*/
return array('type'=>$table, 'id'=>$idCriado);

}

}

// Execução local/manual para testes
if (php_sapi_name() === 'cli' || in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1'])) {
    (new news_fetch_cron)->news_fetch();
}
