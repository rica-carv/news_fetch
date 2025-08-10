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
//    var_Dump(in_array($field, ['src_xpath_title', 'src_xpath_body', 'src_xpath_img']));
//    var_Dump(!empty($link));
//    var_Dump(in_array($field, ['src_xpath_title', 'src_xpath_body', 'src_xpath_img']) && !empty($link));
        if (in_array($field, ['src_xpath_title', 'src_xpath_body', 'src_xpath_img']) && !empty($link)) {
//        $linkNode = $xpathObj->evaluate("string($link)");

        $linkNode = news_fetch_helper::apply_xpath($rawHtml, $link);
//        $targetUrl = trim($linkNode);
//        $targetUrl = $linkNode;
//var_dump($linkNode);

        if (!empty($linkNode)) {
            $targetUrl = $linkNode[0];
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

//    var_dump($result);
    if (empty($result)) {
        echo "<div class='alert alert-warning'>Nenhum conteúdo encontrado com o XPath informado.</div>";
    } else {
        echo "<div class='alert alert-success'>Resultado:</div>";
////        echo "<div class='small text-muted'>URL final: <code>$url</code></div>";

//            echo "<pre style='white-space:pre-wrap'>" . htmlspecialchars($result) . "</pre>";
echo "<ul class='list-group mb-2'>";
foreach ($result as $item) {
///    var_dump($item);
    echo "<li class='list-group-item'><pre style='white-space:pre-wrap'>" . htmlspecialchars($item) . "</pre></li>";
    $text .= news_fetch_helper::resolve_url($url, $item)."<br>";
}
echo "</ul>";

//        echo $field;
//        echo ($field=="src_xpath_link");
/*
        echo ($field=="src_xpath_link" || $field=="src_xpath_img")?"<div class='small text-muted'>URL(s) final(is): <code>".news_fetch_helper::resolve_url($url, $result)."</code></div>
        </div>":"";
*/
        echo ($field=="src_xpath_link" || $field=="src_xpath_img")?"<div class='small text-muted'>URL(s) final(is): <code>".$text."</code></div>
        </div>":"";
//        echo "</div>";
    }
    exit;
}

if (!getperms('P')) {
    e107::redirect('admin');
    exit;
}

trait news_fetch_admin_common_fields
{
    protected $commonFields = [

        'src_xpath_title' => [
            'title' => 'XPath do Título',
            'type'  => 'text',
            'data'  => 'str',
            'width' => 'auto',
            'inline' => true,
            'help'   => 'Expressão XPath para localizar o título do artigo.',
            'writeParms' => [
                'tdClassRight' => 'form-inline',
                'placeholder'  => '//h1',
                'size'         => '2xxlarge',
                'post'         => "<span class='radio-inline radio inline'>
                                       <button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_title'>
                                           Testar XPath do Título
                                       </button>
                                   </span>"
            ],
            'class'   => 'left',
            'thclass' => 'left'
        ],

        'src_xpath_body' => [
            'title' => 'XPath do Corpo',
            'type'  => 'text',
            'data'  => 'str',
            'width' => 'auto',
            'inline' => true,
            'help'   => 'Expressão XPath para localizar o corpo do artigo.',
            'writeParms' => [
                'tdClassRight' => 'form-inline',
                'placeholder'  => '//article',
                'size'         => '2xxlarge',
                'post'         => "<span class='radio-inline radio inline'>
                                       <button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_body'>
                                           Testar XPath do Corpo
                                       </button>
                                   </span>"
            ],
            'class'   => 'left',
            'thclass' => 'left'
        ],

        'src_xpath_img' => [
            'title' => 'XPath da Imagem',
            'type'  => 'text',
            'data'  => 'str',
            'width' => 'auto',
            'inline' => true,
            'help'   => 'Expressão XPath para localizar a(s) imagem(s) do artigo.',
            'writeParms' => [
                'tdClassRight' => 'form-inline',
                'placeholder'  => '//img/@src',
                'size'         => '2xxlarge',
                'post'         => "<span class='radio-inline radio inline'>
                                       <button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_img'>
                                           Testar XPath da Imagem
                                       </button>
                                   </span>"
            ],
            'class'   => 'left',
            'thclass' => 'left'
        ],

        'src_xpath_date' => [
            'title' => 'XPath da Data<br><span class="label label-primary">Deixe em branco para colocar a data actual do sistema</span>',
            'type'  => 'text',
            'data'  => 'str',
            'width' => 'auto',
            'inline' => true,
            'help'   => 'Expressão XPath para localizar a data original da notícia.',
            'writeParms' => [
                'tdClassRight'=>'form-inline',
                'placeholder' => '//time/@datetime ou //span[@class="date"]',
                'size' => '2xxlarge',
                'post' => "<span class='radio-inline radio inline'>
                              <button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_date'>
                                  Testar XPath da Data
                              </button>
                           </span>"
            ],
            'class'   => 'left',
            'thclass' => 'left'
        ]
    ];
/*
    public function __call($method, array $parameters){
    $action = (int)($_GET['ation'] ?? $_POST['action'] ?? 0);
    if (in_array($action, ['create', 'edit', 'view'])) {
    // Reutilizar JS existente para botões "Testar XPath"
        e107::js('footer', e_PLUGIN_ABS.'news_fetch/js/news_fetch.js');
        e107::css('inline', '.input-2xxlarge {width:85% !important;}');
    }
    }
*/

}

class news_fetch_admin_dispatcher extends e_admin_dispatcher
{
    use news_fetch_admin_common_fields;
        protected $modes = [
        'main' => [
            'controller' => 'news_fetch_admin_ui',
            'ui'         => 'news_fetch_form_ui'
        ],
        'import' => [
            'controller' => 'news_fetch_import_ui',
        ],
        'manual_import' => [
            'controller' => 'news_fetch_manual_import_ui',
            'ui'         => 'news_fetch_manual_import_form_ui'
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
        'import/main' => ['caption' => 'Importar site agora',  'perm' => 'P'],
        'manual_import/create' => ['caption' => 'Importação Manual', 'perm' => 'P'],
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
    use news_fetch_admin_common_fields;
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
        ],
        'cron_interval' => [
            'title' => 'Intervalo do cron (segundos)',
            'type'  => 'number',
            'help'  => 'Intervalo em segundos entre execuções do cron (por ex. 3600 para 1 hora)',
            'writeParms' => ['pattern' => '[0-9]+']
        ],
    ];

    /*
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
    'help' => 'Expressão XPath para localizar a(s) imagem(s) do artigo.',
    'writeParms' => [
        'tdClassRight'=>'form-inline',
        'placeholder' => '//img',
        'size' => '2xxlarge',
        'post' => "<span class='radio-inline radio inline'><button type='button' class='test-xpath-btn btn btn-primary btn-sm'>Testar XPath da Imagem</button></span>"
    ],
    'class'   => 'left',
    'thclass' => 'left'
],
'src_xpath_date' => [
    'title' => 'XPath da Data',
    'type'  => 'text',
    'data'  => 'str',
    'width' => 'auto',
    'inline' => true,
    'help' => 'Expressão XPath para localizar a data original da notícia.',
    'writeParms' => [
        'tdClassRight'=>'form-inline',
        'placeholder' => '//time/@datetime ou //span[@class="date"]',
        'size' => '2xxlarge',
        'post' => "<span class='radio-inline radio inline'>
                      <button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_date'>
                          Testar XPath da Data
                      </button>
                   </span>"
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
'src_last_run' => [
    'title' => 'Última Data execução',
    'type'  => 'datestamp',
    'data'  => 'int',
    'width' => 'auto',
    'readParms' => ['format' => 'long']
],
'src_last_date' => [
    'title' => 'Última Data Extraída',
    'type'  => 'datestamp',
    'data'  => 'int',
    'width' => 'auto',
    'readParms' => ['format' => 'long'],
    'help'  => 'Data mais recente obtida da fonte (via XPath de data, se configurado).'
],

        'options' =>   array ( 'title' => LAN_OPTIONS, 'type' => null, 'data' => null, 'width' => '10%', 'thclass' => 'center last', 'class' => 'center last', 'forced' => '1',  ),

    ];
*/
    protected $fields      = [];

//    protected $fieldpref = ['src_name', 'src_url', 'src_cat', 'src_active', 'src_xpath_link', 'src_xpath_title', 'src_xpath_body', 'src_xpath_img', 'src_img2media'];
    protected $fieldpref = ['src_name', 'src_url', 'src_cat', 'src_active', 'src_submit_pending', 'src_last_run', 'src_last_date'];
    
    public function __construct($request, $response)
    {
            $this->fields = array_merge(
            [
                'id' => [
                    'title' => LAN_ID,
                    'data' => 'int',
                    'width' => '5%',
                    'class' => 'left',
                    'thclass' => 'left',
                    'filter' => true
                ],
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
                    'writeParms' => ['size' => 'xxlarge']
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
                        'post' => "<span class='radio-inline radio inline'>
                                       <button type='button' class='test-xpath-btn btn btn-primary btn-sm'>
                                           Testar XPath do Link
                                       </button>
                                   </span>"
                    ],
                    'class'   => 'left',
                    'thclass' => 'left'
                ]
            ],
            $this->commonFields,
            [
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
                    'help' => "Se ativo, a notícia será guardada como submissão pendente.",
                    'width' => 'auto',
                    'thclass' => 'left',
                    'class' => 'left'
                ],
                'src_last_run' => [
                    'title' => 'Última Data execução',
                    'type'  => 'datestamp',
                    'data'  => 'int',
                    'width' => 'auto',
                    'readParms' => ['format' => 'long']
                ],
                'src_last_date' => [
                    'title' => 'Última Data Extraída',
                    'type'  => 'datestamp',
                    'data'  => 'int',
                    'width' => 'auto',
                    'readParms' => ['format' => 'long'],
                    'help'  => 'Data mais recente obtida da fonte.'
                ],
                'options' => [
                    'title' => LAN_OPTIONS,
                    'type' => null,
                    'data' => null,
                    'width' => '10%',
                    'thclass' => 'center last',
                    'class' => 'center last',
                    'forced' => '1'
                ]
            ]
        );

        parent::__construct($request, $response);
    }

    public function init()
    {
        $db = e107::getDb();
        $cats = $db->retrieve('news_category', '*', 'ORDER BY category_name ASC', true);
            $opts = [];
    
            foreach ($cats as $c) {
                $opts[$c['category_id']] = $c['category_name'];
            }
    
///            return $opts;

            $this->fields['src_cat']['writeParms']['optArray'] = $opts;

            $action = $_GET['action'] ?? $_POST['action'] ?? '';

            if ($action === 'create') {
                unset($this->fields['src_last_run'], $this->fields['src_last_date']);
            }
    // EDIT ou VIEW → só mostra se tiver valor e em readonly
    elseif (in_array($action, ['edit', 'view'])) {
// em edit/view/list: tentar obter o registo actual (se houver id)
//        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
//        $row = [];
/*
        if ($id && $db->select('news_fetch', '*', 'id=' . $id)) {
            $row = $db->fetch();
        }
*/        
//        $row = e107::getDb()->retrieve('news_fetch', '*', 'id=' . (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $row = $id ? $db->retrieve('news_fetch', '*', 'id='.$id) : null;
        
        // remover se vazio, senão tornar readonly
/*
        if (empty($row['src_last_run'])) {
            unset($this->fields['src_last_run']);
        } else {
            $this->fields['src_last_run']['readonly'] = true;
//            $this->fields['src_last_run']['writeParms']['pre'] = "<span class='label label-primary'>";
//            $this->fields['src_last_run']['writeParms']['post'] = "</span>";
        }

        if (empty($row['src_last_date'])) {
            unset($this->fields['src_last_date']);
        } else {
            $this->fields['src_last_date']['readonly'] = true;
//            $this->fields['src_last_run']['writeParms']['pre'] = "<div class='field-help'>";
//            $this->fields['src_last_run']['writeParms']['post'] = "</div>";
        }
*/
        unset($this->fields['src_last_run'], $this->fields['src_last_date']);

        $lastRun  = $row['src_last_run'] 
        ? e107::getDate()->convert_date($row['src_last_run'], 'long') 
        : 'Nunca executado';
    $lastDate = $row['src_last_date'] 
        ? e107::getDate()->convert_date($row['src_last_date'], 'long') 
        : 'Nenhuma data extraída';

    // Mensagem no estilo admin (info com botão de fechar)
    e107::getMessage()->addInfo("
        <strong>Última execução:</strong> {$lastRun}<br>
        <strong>Última data extraída:</strong> {$lastDate}
    ")->setClose(true, E_MESSAGE_INFO);

            }
        
           e107::js('footer', e_PLUGIN_ABS.'news_fetch/js/news_fetch.js');
           e107::css('inline', '.input-2xxlarge {width:85% !important;}');

/*
    // Detecta modo create de forma simples (funciona com o admin dispatcher padrão)
    $mode = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($mode === 'create') {
        // durante criação, remove estes campos para não aparecerem
        unset($this->fields['src_last_run'], $this->fields['src_last_date']);
    } else {
        // em edição/visualização, garante que são readonly
        $this->fields['src_last_run']['readonly'] = true;
        $this->fields['src_last_date']['readonly'] = true;
        // opcional: evita input e força visualização formatada
        $this->fields['src_last_run']['nodb'] = false;
        $this->fields['src_last_date']['nodb'] = false;
    }
*/

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
/*
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
    */
}

/*
class news_fetch_manual_import_ui extends e_admin_ui
{
    protected $pluginTitle = 'News Fetch';
    protected $pluginName  = 'news_fetch';
    protected $eventName   = 'news_fetch_manual';

    public function mainPage()
    {
        $frm = e107::getForm();
        $tp  = e107::getParser();
        $db  = e107::getDb();
    
        $sources = $db->retrieve('news_fetch', '*', 'src_active=1', true);
    
        $opts = ['' => '- Nenhuma -'];
        $jsData = [];
    
        foreach ($sources as $row) {
            $opts[$row['id']] = $row['src_name'];
            $jsData[$row['id']] = [
                'title' => $row['src_xpath_title'],
                'body'  => $row['src_xpath_body'],
                'img'   => $row['src_xpath_img'],
                'cat'   => $row['src_cat']
            ];
        }
    
        $text = $frm->open('manual_import', 'post', e_SELF);
        $text .= "<fieldset><legend>Importar Notícia Manualmente</legend>";
    
        $text .= $frm->text('url', '', 100, ['placeholder' => 'https://www.exemplo.com/noticia.html']);
    
        // Select com onchange JS
        $text .= $frm->select('fonte_id', $opts, '', ['id' => 'fonte_id_select']);
    
        $text .= "<div class='text-muted'>Ou preencha manualmente os XPath abaixo:</div>";
    
        $text .= $frm->text('xpath_title', '', 100, [
            'placeholder' => '//h1',
            'id' => 'xpath_title',
            'post' => "<button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_title'>Testar</button>"
        ]);
        
        $text .= $frm->text('xpath_body', '', 100, [
            'placeholder' => '//article',
            'id' => 'xpath_body',
            'post' => "<button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_body'>Testar</button>"
        ]);
        
        $text .= $frm->text('xpath_img', '', 100, [
            'placeholder' => '//img/@src',
            'id' => 'xpath_img',
            'post' => "<button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_img'>Testar</button>"
        ]);
    
        $text .= $frm->number('cat', '', 5, [
            'placeholder' => 'ID da categoria',
            'id' => 'cat_id'
        ]);
    
        $text .= $frm->admin_button('import_manual', 'Importar', 'submit', 'primary');
        $text .= "</fieldset>";
        $text .= $frm->close();
    
        // JS inline com os dados das fontes
        $text .= "<script>
            var newsFetchTemplates = " . json_encode($jsData) . ";
    
            document.addEventListener('DOMContentLoaded', function () {
                const select = document.getElementById('fonte_id_select');
                if (!select) return;
    
                select.addEventListener('change', function () {
                    const id = this.value;
                    if (!id || !newsFetchTemplates[id]) return;
    
                    const tpl = newsFetchTemplates[id];
                    document.getElementById('xpath_title').value = tpl.title || '';
                    document.getElementById('xpath_body').value  = tpl.body || '';
                    document.getElementById('xpath_img').value   = tpl.img || '';
                    document.getElementById('cat_id').value      = tpl.cat || '';
                });
            });
        </script>";
    
        return $text;
    }
    
    public function postImport_manual()
    {
        $msg = e107::getMessage();
        $db  = e107::getDb();
        $tp  = e107::getParser();
        $url = trim($_POST['url']);

        if (!$url) {
            $msg->addError("URL inválido.");
            return;
        }

        $fonteId = intval($_POST['fonte_id']);
        $xpath_title = $_POST['xpath_title'] ?? '';
        $xpath_body  = $_POST['xpath_body'] ?? '';
        $xpath_img   = $_POST['xpath_img'] ?? '';
        $cat         = intval($_POST['cat'] ?? 0);

        // Usa fonte pré-definida
        if ($fonteId > 0 && $db->select('news_fetch', '*', 'id='.$fonteId)) {
            $row = $db->fetch();
        } else {
            // Usa XPath manual
            $row = [
                'src_url'         => $url,
                'src_xpath_title' => $xpath_title,
                'src_xpath_body'  => $xpath_body,
                'src_xpath_img'   => $xpath_img,
                'src_cat'         => $cat,
                'src_submit_pending' => 0,
            ];
        }

        $row['fullUrl'] = $url;

        // Força scraping
        require_once(e_PLUGIN . 'news_fetch/handlers/news_fetch_class.php');
        require_once(e_PLUGIN . 'news_fetch/e_cron.php');
        $cron = new news_fetch_cron;
        $cron->processRow($row); // <--- usa função já existente

        $msg->addInfo("Importação concluída.");
    }
}
*/

class news_fetch_manual_import_ui extends e_admin_ui
{
    use news_fetch_admin_common_fields;

    protected $pluginTitle = 'News Fetch';
    protected $pluginName  = 'news_fetch';
    protected $eventName   = 'news_fetch_manual';
    protected $prefs       = [];
    protected $fields      = [];
    protected $fieldpref   = [];
    protected $formMode    = 'create';
    protected $noBatch     = true;
    protected $noFilter    = true;
    protected $noCreate    = false;
    protected $noEdit      = true;
    protected $noDelete    = true;
    protected $noExport    = true;

/*
    protected $actionButtons = [
        'submit' => [
            'label' => 'Importar notícia',
            'class' => 'btn btn-success',
            'icon'  => 'fa-download'
        ]
    ];
*/
    public function init()
    {
        $db = e107::getDb();
        $cats = $db->retrieve('news_category', '*', 'ORDER BY category_name ASC', true);
        $catOptions = [];
        foreach ($cats as $cat) {
            $catOptions[$cat['category_id']] = $cat['category_name'];
        }

        $sources = $db->retrieve('news_fetch', '*', 'src_active=1', true);
        $srcOptions = ['' => '- Nenhuma -'];
        $srcData = [];

        foreach ($sources as $s) {
            $srcOptions[$s['id']] = $s['src_name'];
            $srcData[$s['id']] = [
                'src_xpath_title' => $s['src_xpath_title'],
                'src_xpath_body'  => $s['src_xpath_body'],
                'src_xpath_img'   => $s['src_xpath_img']
            ];
        }

        $sourceDataJson = json_encode($srcData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
/*
        $this->fields = [
            'src_url' => [
                'title' => 'URL do artigo',
                'type'  => 'url',
                'data'  => 'str',
                'required' => true,
                'writeParms' => [
                    'tdClassRight'=>'form-inline',
                    'size' => 'xxlarge',
                    'post' => "
                        <button type='button' 
                                class='btn btn-light btn-sm' 
                                style='margin-left:5px'
                                onclick=\"document.querySelector('[name=\\'src_url\\']').value='';\">
                            <i class='fa fa-times-circle'></i>
                        </button>"
                ]
            ],
            'fonte_id' => [
                'title' => 'Fonte configurada',
                'type'  => 'dropdown',
                'data'  => 'int',
                'writeParms' => ['optArray' => $srcOptions],
            ],

/*
            'xpath_title' => [
                'title' => 'XPath do título',
                'type'  => 'text',
                'data'  => 'str',
                'writeParms' => [
                    'placeholder' => '//h1',
                    'post' => "<button type='button' class='test-xpath-btn btn btn-sm btn-secondary' data-field='src_xpath_title'>Testar</button>"
                ]
            ],
*/
/*
            'xpath_body' => [
                'title' => 'XPath do corpo',
                'type'  => 'text',
                'data'  => 'str',
                'writeParms' => [
                    'placeholder' => '//article',
                    'post' => "<button type='button' class='test-xpath-btn btn btn-sm btn-secondary' data-field='src_xpath_body'>Testar</button>"
                ]
            ],
*/
/*
            'xpath_img' => [
                'title' => 'XPath da imagem',
                'type'  => 'text',
                'data'  => 'str',
                'writeParms' => [
                    'placeholder' => '//img/@src',
                    'post' => "<button type='button' class='test-xpath-btn btn btn-sm btn-secondary' data-field='src_xpath_img'>Testar</button>"
                ]
            ],
*/
/*            'cat' => [
                'title' => 'Categoria da notícia',
                'type'  => 'dropdown',
                'data'  => 'int',
                'writeParms' => $catOptions
            ]
*/
/*
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
        'post' => "<span class='radio-inline radio inline'><button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_title'>Testar XPath do Título</button></span>"
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
        'post' => "<span class='radio-inline radio inline'><button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_body'>Testar XPath do Corpo</button></span>"
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
    'help' => 'Expressão XPath para localizar a(s) imagem(s) do artigo.',
    'writeParms' => [
        'tdClassRight'=>'form-inline',
        'placeholder' => '//img/@src',
        'size' => '2xxlarge',
        'post' => "<span class='radio-inline radio inline'><button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_img'>Testar XPath da Imagem</button></span>"
    ],
    'class'   => 'left',
    'thclass' => 'left'
],
'src_xpath_date' => [
    'title' => 'XPath da Data',
    'type'  => 'text',
    'data'  => 'str',
    'width' => 'auto',
    'inline' => true,
    'help' => 'Expressão XPath para localizar a data original da notícia.',
    'writeParms' => [
        'tdClassRight'=>'form-inline',
        'placeholder' => '//time/@datetime ou //span[@class="date"]',
        'size' => '2xxlarge',
        'post' => "<span class='radio-inline radio inline'>
                      <button type='button' class='test-xpath-btn btn btn-primary btn-sm' data-field='src_xpath_date'>
                          Testar XPath da Data
                      </button>
                   </span>"
    ],
    'class'   => 'left',
    'thclass' => 'left'
],
        'cat' => [
            'title' => 'Categoria da notícia',
            'type'  => 'dropdown',
            'data'  => 'int',
            'width' => 'auto',
            'writeParms' => $catOptions
        ]


        ];
*/
        $this->fields = array_merge(
            [
                // campos específicos dessa classe
                'src_url' => [
                    'title' => 'URL do artigo',
                    'type'  => 'url',
                    'data'  => 'str',
                    'required' => true,
                    'writeParms' => [
                        'tdClassRight'=>'form-inline',
                        'size' => 'xxlarge',
                        'post' => "
                            <button type='button' 
                                    class='btn delete btn-danger' 
                                    style='margin-left:5px'
                                    onclick=\"document.querySelector('[name=\\'src_url\\']').value='';\">
                                <span>Limpar URL</span>
                            </button>"
                    ]
                ],
                'fonte_id' => [
                    'title' => 'Fonte configurada',
                    'type'  => 'dropdown',
                    'data'  => 'int',
                    'writeParms' => ['optArray' => $srcOptions],
                ]
            ],
            $this->commonFields,
            [
                // mais campos específicos se necessário
                'cat' => [
                    'title' => 'Categoria da notícia',
                    'type'  => 'dropdown',
                    'data'  => 'int',
                    'width' => 'auto',
                    'writeParms' => $catOptions
                ]
            ]
        );

        // Agora sim, gerar o script JS com segurança
        e107::js('footer-inline', <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    var sourceData = {$sourceDataJson};

    var select = document.querySelector('[name="fonte_id"]');
    if (select) {
        select.addEventListener('change', function() {
            var val = this.value;
            if (!val || !sourceData[val]) return;

            document.querySelector('[name="src_xpath_title"]').value = sourceData[val].src_xpath_title || '';
            document.querySelector('[name="src_xpath_body"]').value  = sourceData[val].src_xpath_body || '';
            document.querySelector('[name="src_xpath_img"]').value   = sourceData[val].src_xpath_img || '';
        });
    }
});
</script>
JS);

        // Reutilizar JS existente para botões "Testar XPath"
        e107::js('footer', e_PLUGIN_ABS . 'news_fetch/js/news_fetch.js');
        e107::css('inline', '.input-2xxlarge {width:85% !important;}');

        e107::js('footer-inline', <<<JS
<script>
document.addEventListener('DOMContentLoaded', function(){
    // Remove o dropdown inteiro (não só o botão toggle, mas o grupo)
    var dropdownGroup = document.querySelector('button.btn.btn-success.dropdown-toggle.left');
    if(dropdownGroup){
        dropdownGroup.remove();
    }

    // Alterar o botão principal (create)
    var btn = document.querySelector('button.btn.create.btn-success');
    if(btn){
        btn.textContent = "Importar Notícia";
    }
});
</script>
JS);

    }

    public function beforeCreate($new_data, $old_data)
    {
        // força manual scrape
        require_once(e_PLUGIN . 'news_fetch/e_cron.php');
        require_once(e_PLUGIN . 'news_fetch/handlers/news_fetch_class.php');

        $row = [
            'src_url'            => $new_data['src_url'],
            'fullUrl'            => $new_data['src_url'],
            'src_xpath_title'    => $new_data['src_xpath_title'],
            'src_xpath_body'     => $new_data['src_xpath_body'],
            'src_xpath_img'      => $new_data['src_xpath_img'],
            'src_cat'            => $new_data['cat'],
            'src_submit_pending' => 0,
        ];

//        $runner = new news_fetch_cron;
        if (!empty($result = (new news_fetch_cron)->news_fetch($row))){
            if ($result['type'] === 'news') {
                $url = e_ADMIN."newspost.php?action=edit&id={$result['id']}";
            } else {
                $url = e_ADMIN."newspost.php?mode=pending&id={$result['id']}";
            }
            e107::getMessage()->addSuccess("Notícia criada com sucesso: <a href='{$url}' target='_blank'>Editar notícia</a>");
        }
//        var_dump ($temp);
/*        list($type, $id) = $runner->news_fetch($row); 
        
//        var_dump ($type);
//        var_dump ($id);
//        echo "<hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr><hr>";

        if ($type === 'news') {
            $link = e107::url('news', 'item', $id);
            e107::getMessage()->addInfo("Importação concluída. <a href='{$link}' target='_blank'>Ver notícia</a>");
        } elseif ($type === 'submitnews') {
            $link = e_ADMIN."news.php?mode=submitted&action=edit&id=".$id;
            e107::getMessage()->addInfo("Submetido para aprovação. <a href='{$link}' target='_blank'>Rever submissão</a>");
        }
*/
  //      e107::getMessage()->addInfo("Importação concluída.");
        return false; // impede gravação na base de dados
    }
/*
    public function renderButtons()
{
    return e107::getForm()->admin_button('import_manual', 'Importar notícia', 'submit', 'success');
}
*/

}


class news_fetch_manual_import_form_ui extends e_admin_form_ui
    {
    }

class news_fetch_log_ui extends e_admin_ui
{
    public function ListPage()
    {
        $sql = e107::getDb();
        $tp  = e107::getParser();

        $text = "<h4>Últimos logs de importação (news_fetch)</h4>";
        $text .= "<div class='adminlist' style='max-height:50em; overflow:auto;'>";

        $qry = "
            SELECT * FROM #admin_log
            WHERE dblog_eventcode = 'news_fetch'
            ORDER BY dblog_datestamp DESC
            LIMIT 100
        ";

        if ($sql->gen($qry)) {

            $text .= "<table class='table table-striped'><thead><tr>
    <th>Data Log</th>
    <th>Data Original</th>
    <th>Tipo</th>
    <th>Mensagem</th>
</tr></thead><tbody>";

while ($row = $sql->fetch()) {
    $datestamp = date('d-m-Y H:i', $row['dblog_datestamp']);

    // tenta extrair data original do texto do log
    preg_match('/Data Original: ([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2})/', $row['dblog_remarks'], $match);
    $dataOriginal = $match[1] ?? '-';

    $type = match($row['log_severity']) {
        E_LOG_FATAL       => "<span class='text-danger'>Erro</span>",
        E_LOG_INFORMATIVE => "<span class='text-success'>Info</span>",
        E_LOG_WARNING     => "<span class='text-warning'>Aviso</span>",
        default           => "Outro"
    };

    $text .= "<tr>
        <td>{$datestamp}</td>
        <td>{$dataOriginal}</td>
        <td>{$type}</td>
        <td>{$tp->toHTML($row['dblog_remarks'])}</td>
    </tr>";
}

/*
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
*/
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