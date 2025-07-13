<?php
if (!defined('e107_INIT')) { exit; }

class news_fetch_helper
{
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
     * Aplica uma expressão XPath e retorna o texto encontrado.
     */
    public static function apply_xpath($html, $xpathQuery, $asHtml = false)
    {
        $xpathQuery = html_entity_decode($xpathQuery);
    
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
    
        if ($asHtml) {
            // Obter o primeiro nó correspondente
            $nodeList = $xpath->query($xpathQuery);
            if ($nodeList->length === 0) return '';
    
            $innerHTML = '';
            foreach ($nodeList as $node) {
                foreach ($node->childNodes as $child) {
                    $innerHTML .= $dom->saveHTML($child);
                }
            }
            return trim($innerHTML);
        }
    
        // Modo padrão (apenas texto)
        return trim($xpath->evaluate("string({$xpathQuery})"));
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
    public static function log($msg, $type = 'NOTICE')
    {
        e107::getLog()->add('news_fetch', $msg, $type);
    }
}
