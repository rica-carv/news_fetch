<?php
if (!defined('e107_INIT')) { exit; }

class news_fetch_helper
{
/*    
    protected $log;
    protected $logEventCode; 

    public function __construct($eventCode  = 'news_fetch') // padrão
    {
        $this->log = e107::getLog();
//        if ($eventCode) {
            $this->logEventCode = $eventCode;
//        }
    }
*/    
    protected $db;
    protected $log;

    public function __construct($db = null, $log = null)
    {
        $this->db  = $db ?: e107::getDb();
        $this->log = $log ?: e107::getLog();
    }
    /**
     * Verifica se a URL parece ser um RSS.
     */
    public static function is_rss($url)
    {
        return (stripos($url, '.xml') !== false || stripos($url, '/rss') !== false);
    }

    /**
     * Resolve um link relativo ou parcial para absoluto com base na origem.
     */
    public static function resolve_url($base, $relative)
    {
        // Se já for uma URL absoluta
        if (parse_url($relative, PHP_URL_SCHEME)) {
            return $relative;
        }
    
        // Parse base
        $parsed = parse_url($base);
        $scheme = $parsed['scheme'] ?? 'http';
        $host   = $parsed['host'] ?? '';
        $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    
        // Se path começar com "/", é relativo à raiz do site
        if (strpos($relative, '/') === 0) {
            return $scheme . '://' . $host . $port . $relative;
        }
    
        // Caso contrário, junta ao path atual
        $basePath = rtrim(dirname($parsed['path'] ?? '/'), '/') . '/';
        return $scheme . '://' . $host . $port . $basePath . $relative;
    }

/**
 * Aplica uma expressão XPath e retorna o(s) resultado(s).
 *
 * @param string $html        HTML bruto.
 * @param string $xpathQuery  Expressão XPath.
 * @param bool   $asHtml      Se verdadeiro, retorna HTML interno dos nós.
 * @param bool   $alwaysArray Se verdadeiro, retorna sempre array, mesmo com 1 resultado.
 * @return string|array
 */
public static function apply_xpath($html, $xpathQuery, $asHtml = false)
{
    $xpathQuery = html_entity_decode($xpathQuery);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $nodes = $xpath->query($xpathQuery);

    if ($nodes === false || $nodes->length === 0) {
        return $asHtml ? '' : '';
    }

    $result = [];
    // Modo HTML
    if ($asHtml) {
        foreach ($nodes as $node) {
            $innerHTML = '';
            foreach ($node->childNodes as $child) {
                $innerHTML .= $dom->saveHTML($child);
            }
            $result[] = trim($innerHTML);
        }

////        return count($result) === 1 ? $result[0] : $result;
    }

    // Modo texto (atributo ou texto)
//    $result = [];
    foreach ($nodes as $node) {
        $result[] = trim($node->nodeValue);
    }

    return $result;
}


    /**
     * Obtem conteúdo remoto com timeout e validação.
     */
    public static function fetch_url($url, $timeout = 10)
    {
        $opts = [
            'http' => [
                'method'  => 'GET',
                'timeout' => $timeout,
                'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0 Safari/537.36"
            ]
        ];

        $context = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }

    /**
     * Regista uma entrada no log do sistema do e107.
     */
/*
    public static function log($msg, $type = 'NOTICE')
    {
        e107::getLog()->add("gnfdng", $msg, $type);
    }
*/   
public static function parseDate($rawDate)
{
    if (empty($rawDate)) {
        return time(); // default para agora
    }

    $rawDate = trim($rawDate);

    if (preg_match('/hoje|today/i', $rawDate)) {
        return strtotime('today');
    }
    if (preg_match('/ontem|yesterday/i', $rawDate)) {
        return strtotime('yesterday');
    }

    // Normalizar meses em português e inglês
    $meses = [
        'janeiro'=>'January','fevereiro'=>'February','março'=>'March','abril'=>'April',
        'maio'=>'May','junho'=>'June','julho'=>'July','agosto'=>'August',
        'setembro'=>'September','outubro'=>'October','novembro'=>'November','dezembro'=>'December',
        'jan'=>'Jan','fev'=>'Feb','mar'=>'Mar','abr'=>'Apr','mai'=>'May','jun'=>'Jun',
        'jul'=>'Jul','ago'=>'Aug','set'=>'Sep','out'=>'Oct','nov'=>'Nov','dez'=>'Dec'
    ];
    $rawDate = str_ireplace(array_keys($meses), array_values($meses), $rawDate);

    // Tentar alguns formatos comuns
    $formatos = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'd/m/Y H:i',
        'd/m/Y',
        'M d, Y H:i',
        'M d, Y',
        'd M Y',
        'd M Y H:i',
        'l, d M Y H:i', // segunda-feira, 29 Jul 2025 12:34
    ];

    foreach ($formatos as $formato) {
        $dt = \DateTime::createFromFormat($formato, $rawDate);
        if ($dt) {
            return $dt->getTimestamp();
        }
    }

    // Fallback para strtotime
    $ts = strtotime($rawDate);
    return $ts ?: time();
}

// ... seus métodos utilitários (is_rss, resolve_url, etc) podem continuar estáticos ou não ...

    public function news_fetch($rowdata = null)
    {
        $rows = $rowdata ? array($rowdata) : $this->db->retrieve('news_fetch', '*', 'src_active=1', true);

        foreach ($rows as $row) {
            if (self::is_rss($row['src_url'])) {
                $content = self::fetch_url($row['src_url']);
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
                    return;
                }

                $row['title']     = (string) $item->title;
                $row['body']      = (string) $item->description;
                $row['datestamp'] = self::parseDate((string) $item->pubDate);

                $lastresult = $this->submitNews($row, 'RSS');
            } else {
                $sources = [];
                if ($row['fullUrl'] && !$row['src_xpath_link']) {
                    $sources[] = $row['fullUrl'];
                } else {
                    $html = self::fetch_url($row['src_url']);
                    if (!$html) {
                        $this->log->add('news_fetch', "Erro: HTML vazio ao obter {$row['src_url']}", E_LOG_WARNING);
                        return;
                    }

                    $sources = self::apply_xpath($html, $row['src_xpath_link']);
                    if (empty($sources)) {
                        $this->log->add('news_fetch', "XPath falhou para link: {$row['src_xpath_link']}", E_LOG_WARNING);
                        return;
                    }
                }
                foreach ($sources as $link) {
                    $row['fullUrl'] = self::resolve_url($row['src_url'], $link);
                    if (empty($row['fullUrl']) || $row['fullUrl'] === trim($row['src_last_url'] ?? '')) {
                        $this->log->add('news_fetch', "Ignorado (já importado): {$row['fullUrl']}", E_LOG_INFORMATIVE);
                        return;
                    }

                    $articleHtml = self::fetch_url($row['fullUrl']);
                    if (!$articleHtml) {
                        $this->log->add('news_fetch', "Erro ao obter artigo: {$row['fullUrl']}", E_LOG_ERROR);
                        return;
                    }

                    $row['title'] = self::apply_xpath($articleHtml, $row['src_xpath_title'])[0] ?? '';
                    $row['body']  = self::apply_xpath($articleHtml, $row['src_xpath_body'], true)[0] ?? '';

                    if (empty($row['title']) || empty($row['body'])) {
                        $this->log->add('news_fetch', "Falha ao extrair título ou corpo", E_LOG_WARNING);
                        return;
                    }

                    $imageList = [];
                    if (!empty($row['src_xpath_img'])) {
                        $imgUrllist = self::apply_xpath($articleHtml, $row['src_xpath_img']);
                        foreach ($imgUrllist as $imgUrl) {
                            $imgUrl = self::resolve_url($row['fullUrl'], $imgUrl);

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

                                    if (!is_dir($mediaPath) && !mkdir($mediaPath, 0755, true)) {
                                        $this->log->add('news_fetch', "Erro ao criar pasta de imagem: $mediaPath", E_LOG_FATAL);
                                        return;
                                    }
                                    file_put_contents($targetFile, $imageData);

                                    if (getimagesize($targetFile)) {
                                        e107::getMedia()->import('news', $mediaPath);
                                        $imageList[] = e107::getParser()->createConstants($targetFile, 1);
                                    } else {
                                        unlink($targetFile);
                                        $this->log->add('news_fetch', "Imagem inválida: $imgUrl", E_LOG_WARNING);
                                        return;
                                    }
                                }
                            }
                        }
                        if (!empty($imageList)) {
                            $row['image'] = implode(',', $imageList);
                        }
                    }

                    if (!empty($row['src_xpath_date'])) {
                        $rawDate = self::apply_xpath($articleHtml, $row['src_xpath_date'])[0] ?? '';
                        $row['datestamp'] = self::parseDate($rawDate);
                    }

                    $lastresult = $this->submitNews($row);
                }
            }
        }
        return $rowdata ? $lastresult : null;
    }

    public function submitNews($row, $modo = 'scraping')
    {
$importType = $row['src_import_type'] ?? 0;
$showLink = !isset($row['src_show_link']) ? true : (bool)$row['src_show_link'];

if ($importType === 0) {
    $row['body'] = mb_substr(strip_tags($row['body']), 0, 300);
    if ($showLink) {
        $row['body'] .= '... <a href="'.$row['fullUrl'].'" target="_blank">Ler na fonte</a>';
    }
} else {
    if ($showLink) {
        $row['body'] .= '<br><a href="'.$row['fullUrl'].'" target="_blank">Fonte original</a>';
    }
}

        $table = '';
        if (!empty($row['src_submit_pending'])) {
            $news = [
                'submitnews_name'      => USERNAME ?: 'newsfetch',
                'submitnews_email'     => USEREMAIL ?: '',
                'submitnews_subject'   => $row['title'],
                'submitnews_item'      => $row['body'],
                'submitnews_datestamp' => $row['datestamp'] ?? time(),
                'submitnews_category'  => (int) $row['src_cat'],
                'submitnews_file'      => $row['image'] ?? ''
            ];
            $table = 'submitnews';
        } else {
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
            $table = 'news';
        }

        if ($idCriado = $this->db->insert($table, $news)) {
            $this->db->update(
                'news_fetch',
                "src_last_url='{$row['fullUrl']}', 
                 src_last_run=" . time() . ", 
                 src_last_date=" . intval($row['datestamp']) . "
                 WHERE id=" . (int)$row['id']
            );
            $this->log->add(
                'news_fetch',
                "Importado ({$modo}): {$row['title']} (Data Original: " . date('Y-m-d H:i', $row['datestamp']) . ")",
                E_LOG_INFORMATIVE
            );
        } else {
            $this->log->add('news_fetch', "Erro ao submeter: {$row['title']}", E_LOG_FATAL);
        }
        return array('type' => $table, 'id' => $idCriado);
    }

}
