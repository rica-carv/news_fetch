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

}
