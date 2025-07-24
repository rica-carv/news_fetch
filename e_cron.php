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

    public function news_fetch()
    {
        $rows = $this->db->retrieve('news_fetch', '*', 'src_active=1', true);

        foreach ($rows as $row) {
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
        $row['datestamp'] = strtotime((string) $item->pubDate) ?: time();

        $this->submitNews($row, 'RSS');
//    }
} else {

//    protected function processScraping($row)
//    {
        $html = news_fetch_helper::fetch_url($row['src_url']);
        if (!$html) {
            $this->log->add('news_fetch', "Erro: HTML vazio ao obter {$row['src_url']}", E_LOG_WARNING);
            return;
        }

        $link = news_fetch_helper::apply_xpath($html, $row['src_xpath_link']);
        if (empty($link)) {
            $this->log->add('news_fetch', "XPath falhou para link: {$row['src_xpath_link']}", E_LOG_WARNING);
            return;
        }

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

        $row['title'] = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_title']);
        $row['body']  = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_body'], true);

        if (empty($row['title']) || empty($row['body'])) {
            $this->log->add('news_fetch', "Falha ao extrair título ou corpo", E_LOG_WARNING);
            return;
        }

        // Imagem
        if (!empty($row['src_xpath_img'])) {
            $imgUrl = news_fetch_helper::apply_xpath($articleHtml, $row['src_xpath_img']);
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
                        $row['image'] = e107::getParser()->createConstants($targetFile, 1);
                    } else {
                        unlink($targetFile);
                        $this->log->add('news_fetch', "Imagem inválida: $imgUrl", E_LOG_WARNING);
                        return;
                    }
                }
            }
        }

        $this->submitNews($row);
    }
    }
}
    protected function submitNews($row, $modo = 'scraping')
    {
        if (!empty($row['src_submit_pending'])) {
            // Grava em submitnews (pendente)
            $newsData = [
                'submitnews_name'      => USERNAME ?? 'newsfetch',
                'submitnews_email'     => defined('USEREMAIL') ? USEREMAIL : '',
                'submitnews_subject'   => $row['title'],
                'submitnews_item'      => $row['body'],
                'submitnews_datestamp' => $row['datestamp'] ?? time(),
                'submitnews_category'  => (int) $row['src_cat'],
                'submitnews_file'      => $row['image'] ?? ''
            ];

            if ($this->db->insert('submitnews', $newsData)) {
                $this->db->update('news_fetch', [
                    'src_last_url' => $row['fullUrl'],
                    'src_last_run' => time()
                ], "id=" . (int) $row['id']);

                $this->log->add('news_fetch', "Submetido para aprovação: {$row['title']}", E_LOG_INFORMATIVE);
            } else {
                $this->log->add('news_fetch', "Erro ao inserir submitnews: {$row['title']}", E_LOG_FATAL);
            }

        } else {
            // Publicação direta
            require_once(e_HANDLER . 'news_class.php');

            $news = [
                'news_title'     => $row['title'],
                'news_body'      => $row['body'],
                'news_datestamp' => $row['datestamp'] ?: time(),
                'news_author'    => USERID ?: 1,
                'news_category'  => (int) $row['src_cat'],
                'news_class'     => 255
            ];

            if (!empty($row['image'])) {
                $news['news_thumbnail'] = $row['image'];
            }

            if ((new news)->submit_item($news)) {
                $this->db->update('news_fetch', [
                    'src_last_url' => $row['fullUrl'],
                    'src_last_run' => time()
                ], "id=" . (int) $row['id']);

                $this->log->add('news_fetch', "Importado ({$modo}): {$row['title']}", E_LOG_INFORMATIVE);
            } else {
                $this->log->add('news_fetch', "Erro ao submeter: {$row['title']}", E_LOG_FATAL);
            }
        }
    }
}

// Execução local/manual para testes
if (php_sapi_name() === 'cli' || in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1'])) {
    (new news_fetch_cron)->news_fetch();
}
