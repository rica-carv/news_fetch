<?php
if (!defined('e107_INIT')) { require_once('../../class2.php'); }

// -- AJAX preview handler --
if (isset($_GET['ajax_xpath']))
{
    require_once(e_PLUGIN . 'news_fetch/handlers/news_fetch_class.php');

    $url    = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
    $xpath  = $_GET['xpath'] ?? '';
    $link   = $_GET['link'] ?? '';
    $field  = $_GET['field'] ?? 'src_xpath_body';

    if (!$url || !$xpath) {
        echo "<div class='alert alert-danger'>URL ou XPath inválido.</div>";
        exit;
    }

//    libxml_use_internal_errors(true);
//    $rawHtml = file_get_contents($url);
    $rawHtml = news_fetch_helper::fetch_url($url);
/*
    file_put_contents(e_LOG."debug-admin.html", $rawHtml);
    if (!$rawHtml) {
        echo "<div class='alert alert-danger'>Não foi possível carregar o URL: <code>$url</code></div>";
        exit;
    }
*/
//    $dom = new DOMDocument();
//    $dom->loadHTML($rawHtml);
//    $xpathObj = new DOMXPath($dom);

    // Se for campo de conteúdo, e houver XPath para link, segue o link
    if (in_array($field, ['src_xpath_title', 'src_xpath_body', 'src_xpath_img']) && !empty($link)) {
//        $linkNode = $xpathObj->evaluate("string($link)");

        $linkNode = news_fetch_helper::apply_xpath($rawHtml, $link);
//        $targetUrl = trim($linkNode);
        $targetUrl = $linkNode;

        if (!empty($targetUrl)) {
//            require_once(e_PLUGIN . 'news_fetch/handlers/news_fetch_class.php');
            $url = news_fetch_helper::resolve_url($url, $targetUrl);
            $rawHtml = file_get_contents($url);
        }
    }
/*
    if (!$rawHtml) {
        echo "<div class='alert alert-danger'>Falha ao seguir link: <code>$url</code></div>";
        exit;
    }
*/    
//    $dom = new DOMDocument();
//    $dom->loadHTML($rawHtml);
//    $xpathObj = new DOMXPath($dom);
    
//    $result = $xpathObj->evaluate("string($xpath)");
//var_dump($xpath);

    $result = news_fetch_helper::apply_xpath($rawHtml, $xpath, ($field=='src_xpath_body'?true:false));

    if (empty($result)) {
        echo "<div class='alert alert-warning'>Nenhum conteúdo encontrado com o XPath informado.</div>";
    } else {
        echo "<div class='alert alert-success'>Resultado:</div>";
////        echo "<div class='small text-muted'>URL final: <code>$url</code></div>";
        echo "<pre style='white-space:pre-wrap'>" . htmlspecialchars($result) . "</pre>";
//        echo $field;
//        echo ($field=="src_xpath_link");
        echo ($field=="src_xpath_link" || $field=="src_xpath_img")?"<div class='small text-muted'>URL final: <code>".news_fetch_helper::resolve_url($url, $result)."</code></div>
        </div>":"";
    }
    exit;
}

if (!getperms('P')) {
    e107::redirect('admin');
    exit;
}

class news_fetch_admin_dispatcher extends e_admin_dispatcher
{
    protected $modes = [
        'main' => [
            'controller' => 'news_fetch_admin_ui',
            'ui'         => 'news_fetch_form_ui'
        ],
        'import' => [
            'controller' => 'news_fetch_import_ui',
        ],
        'log' => [
            'controller' => 'news_fetch_log_ui',
        ]
    ];

    protected $adminTitle = 'News Fetch';
    protected $menuTitle  = 'News Fetch';

    protected $adminMenu = [
        'main/list'   => ['caption' => 'Fontes',          'perm' => 'P'],
        'main/create' => ['caption' => 'Adicionar Fonte', 'perm' => 'P'],
        'divider1'     => ['divider' => true],
        'import/main' => ['caption' => 'Importar agora',  'perm' => 'P'],
        'divider2'     => ['divider' => true],
        'main/prefs' => ['caption' => 'Preferências', 'perm' => 'P'],
        'divider3'   => ['divider' => true],
        'log/list'    => ['caption' => 'Logs',            'perm' => 'P'],
    ];

	public function init()
	{
        $count = e107::getDb()->select("news_fetch");

        $this->adminMenu['main/list']['badge'] = array('value' => (int)$count, 'type' => ($count?'info':'warning'));
    }
}

class news_fetch_admin_ui extends e_admin_ui
{
    protected $pluginTitle = 'News Fetch';
    protected $pluginName  = 'news_fetch';
    protected $table	    = 'news_fetch';

    protected $pid				= 'id';
    protected $perPage     = 10;
    protected $batchDelete		= true;
    protected $batchCopy		= true;		
   protected $defaultOrderField = 'src_name';
   protected $defaultOrder = 'asc';

       // ✅ Preferências globais do plugin (armazenadas em e107_prefs)
       protected $prefs = [
        'use_module_fallback' => [
            'title' => 'Usar fallback via e_module.php',
            'type'  => 'boolean',
            'data'  => 'int',
            'help'  => 'Se ativado, permite que o plugin corra via e_module.php caso o cron do servidor não esteja ativo.'
        ]
    ];

    protected $fields = [
        'id' =>   array ( 'title' => LAN_ID, 'data' => 'int', 'width' => '5%', 'class' => 'left', 'thclass' => 'left', 'filter' => true),
        'src_name' => [
            'title' => 'Nome',
            'type'  => 'text',
            'data'  => 'str',
            'width' => 'auto'
        ],
        'src_url' => [
            'title' => 'URL',
            'type'  => 'url',
            'data'  => 'str',
            'width' => 'auto',
            'writeParms' => [
                'size' => 'xxlarge'
            ],
        ],
        'src_xpath_link' => [
    'title' => 'XPath do Link',
    'type' => 'text',
    'data' => 'str',
    'width' => 'auto',
    'inline' => true,
    'help' => 'Expressão XPath para localizar o link do artigo.',
    'writeParms' => [
        'tdClassRight'=>'form-inline',
        'placeholder' => '//link',
        'size' => '2xxlarge',
        'post' => "<span class='radio-inline radio inline'><button type='button' class='test-xpath-btn btn btn-primary btn-sm'>Testar XPath do Link</button></span></div>"
    ],
    'class'   => 'left',
    'thclass' => 'left'
],
'src_xpath_title' => [
    'title' => 'XPath do Título',
    'type' => 'text',
    'data' => 'str',
    'width' => 'auto',
    'inline' => true,
    'help' => 'Expressão XPath para localizar o título do artigo.',
    'writeParms' => [
        'tdClassRight'=>'form-inline',
        'placeholder' => '//h1',
        'size' => '2xxlarge',
        'post' => "<span class='radio-inline radio inline'><button type='button' class='test-xpath-btn btn btn-primary btn-sm'>Testar XPath do Título</button></span>"
    ],
    'class'   => 'left',
    'thclass' => 'left'
],
'src_xpath_body' => [
    'title' => 'XPath do Corpo',
    'type' => 'text',
    'data' => 'str',
    'width' => 'auto',
    'inline' => true,
    'help' => 'Expressão XPath para localizar o corpo do artigo.',
    'writeParms' => [
        'tdClassRight'=>'form-inline',
        'placeholder' => '//article',
        'size' => '2xxlarge',
        'post' => "<span class='radio-inline radio inline'><button type='button' class='test-xpath-btn btn btn-primary btn-sm'>Testar XPath do Corpo</button></span>"
    ],
    'class'   => 'left',
    'thclass' => 'left'
],
'src_xpath_img' => [
    'title' => 'XPath da Imagem',
    'type' => 'text',
    'data' => 'str',
    'width' => 'auto',
    'inline' => true,
    'help' => 'Expressão XPath para localizar a imagem do artigo.',
    'writeParms' => [
        'tdClassRight'=>'form-inline',
        'placeholder' => '//img',
        'size' => '2xxlarge',
        'post' => "<span class='radio-inline radio inline'><button type='button' class='test-xpath-btn btn btn-primary btn-sm'>Testar XPath da Imagem</button></span>"
    ],
    'class'   => 'left',
    'thclass' => 'left'
],
        'src_cat' => [
            'title' => 'Categoria',
            'type'  => 'dropdown',
            'data'  => 'int',
            'width' => 'auto'
        ],
        'src_active' => [
            'title' => 'Ativo',
            'type'  => 'boolean',
            'data'  => 'int',
            'width' => 'auto'
        ],
        'src_submit_pending' => [
    'title' => "Requer aprovação?",
    'type' => 'boolean',
    'data' => 'int',
    'help' => "Se ativo, a notícia será guardada como submissão pendente (tabela submitnews).",
    'width' => 'auto',
    'thclass' => 'left',
    'class' => 'left'
],
        'options' =>   array ( 'title' => LAN_OPTIONS, 'type' => null, 'data' => null, 'width' => '10%', 'thclass' => 'center last', 'class' => 'center last', 'forced' => '1',  ),

    ];

//    protected $fieldpref = ['src_name', 'src_url', 'src_cat', 'src_active', 'src_xpath_link', 'src_xpath_title', 'src_xpath_body', 'src_xpath_img', 'src_img2media'];
    protected $fieldpref = ['src_name', 'src_url', 'src_cat', 'src_active', 'src_submit_pending'];
    
    public function init()
    {
        $cats = e107::getDb()->retrieve('news_category', '*', 'ORDER BY category_name ASC', true);
            $opts = [];
    
            foreach ($cats as $c) {
                $opts[$c['category_id']] = $c['category_name'];
            }
    
///            return $opts;

            $this->fields['src_cat']['writeParms'] = $opts;

            e107::js('footer', e_PLUGIN_ABS.'news_fetch/js/news_fetch.js');
            e107::css('inline', '.input-2xxlarge {width:85% !important;}');

    }
    // ------- Customize Create --------

    public function beforeCreate($new_data, $old_data)
    {

    }

public function afterCreate($new_data, $old_data, $id)
{
    // do something
}

public function onCreateError($new_data, $old_data)
{
    // do something		
}		
// ------- Customize Update --------

public function beforeUpdate($new_data, $old_data, $id)
{

}

public function afterUpdate($new_data, $old_data, $id)
{
    // do something	
}

public function onUpdateError($new_data, $old_data, $id)
{
    // do something		
}	
}
    class news_fetch_form_ui extends e_admin_form_ui
    {
    }
    
    class news_fetch_import_ui extends e_admin_ui
{
    protected $pluginTitle = 'News Fetch';
    protected $pluginName  = 'news_fetch';
    protected $eventName   = 'news_fetch';

    public function MainPage()
    {
        $frm = e107::getForm();
        $tp  = e107::getParser();
        $db  = e107::getDb();

        $sources = $db->retrieve('news_fetch', '*', 'WHERE src_active = 1', true);

        if (empty($sources)) {
            return "<div class='alert alert-warning'>Nenhuma fonte ativa encontrada.</div>";
        }

        // Buscar categorias de notícias
        $catList = $db->retrieve('news_category', 'category_id, category_name', 'ORDER BY category_name ASC', true);
        $cats = [];
        foreach ($catList as $c) {
            $cats[$c['category_id']] = $c['category_name'];
        }

        $text = "<div><div id='results-container'></div>
        <form action='".e_SELF."' method='post' id='scanform'>";

        $text .= '<table class="table adminform table-striped">';
        $text .= '<thead><tr>
            <th>Importar?</th>
            <th>Nome</th>
            <th>URL</th>
            <th>Categoria</th>
            <th>Ultima importação</th>
        </tr></thead><tbody>';

        foreach ($sources as $row)
        {
            $catName = $cats[$row['src_cat']] ?? 'Desconhecida';

            $text .= "<tr>";
            $text .= "<td>".$frm->renderElement('import['.$row['id'].']', 1, array('type'=>'boolean', 'writeParms' => array('label' => 'yesno'))). "</td>";
            $text .= "<td>" . $tp->toHTML($row['src_name']) . "</td>";
            $text .= "<td><a href='".$row['src_url']."' target='_blank'>" . $tp->toHTML($row['src_url']) . "</a></td>";
            $text .= "<td>" . $tp->toHTML($catName) . "</td>";
            $text .= "<td>" . $tp->toDate($row['src_last_run'], 'long') . "</td>";
            $text .= "</tr>";
        }

        $text .= "</tbody></table>";

        $text .= "<div class='buttons-bar center'>";
        $text .= '<button class="btn btn-primary" type="submit" name="scan" value="1">'.LAN_GO.'</button>';
        $text .= "</div>";

        $text .= $frm->close();
        $text .= "</div>";

        return $text;
    }

    public function scanPage()
    {
        $msg = e107::getMessage();
        $db  = e107::getDb();
        $selected = array_keys($_POST['import'] ?? []);
        $count = 0;

        require_once(e_PLUGIN.'news_fetch/e_cron.php');
        $cron = new news_fetch_cron;
        $cron->news_fetch();

        foreach ($selected as $id)
        {
            $row = $db->retrieve('news_fetch', '*', 'id='.(int)$id, true);
            if (empty($row)) continue;

//                $url = $row['src_url'];
//                $cat = $row['src_cat'];
            
                if (news_fetch_import($row)) {
                    $count++;
                }
        }

        $msg->addInfo("Importação concluída: $count fontes processadas.");
        return true;
    }
}

class news_fetch_log_ui extends e_admin_ui
{
    public function ListPage()
    {
        $sql = e107::getDb();
        $tp  = e107::getParser();

        $text = "<h4>Últimos logs de importação (news_fetch)</h4>";
        $text .= "<div class='adminlist' style='max-height:400px; overflow:auto;'>";

        $qry = "
            SELECT * FROM #admin_log
            WHERE dblog_eventcode = 'news_fetch'
            ORDER BY dblog_datestamp DESC
            LIMIT 50
        ";

        if ($sql->gen($qry)) {
            $text .= "<table class='table table-striped'><thead><tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Mensagem</th>
            </tr></thead><tbody>";

            while ($row = $sql->fetch()) {
                $datestamp = date('d-m-Y H:i', $row['dblog_datestamp']);
                $type = match($row['log_severity']) {
                    E_LOG_FATAL       => "<span class='text-danger'>Erro</span>",
                    E_LOG_INFORMATIVE => "<span class='text-success'>Info</span>",
                    E_LOG_WARNING     => "<span class='text-warning'>Aviso</span>",
                    default           => "Outro"
                };
                $text .= "<tr>
                    <td>{$datestamp}</td>
                    <td>{$type}</td>
                    <td>{$tp->toHTML($row['dblog_remarks'])}</td>
                </tr>";
            }

            $text .= "</tbody></table>";
        } else {
            $text .= "<div class='alert alert-info'>Nenhum log encontrado.</div>";
        }

        $text .= "</div>";
        return $text;
    }
}


new news_fetch_admin_dispatcher();
require_once(e_ADMIN.'auth.php');
e107::getAdminUI()->runPage();
require_once(e_ADMIN.'footer.php');