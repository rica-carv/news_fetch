<?php
/*
if (!defined('e107_INIT')) {
    require_once('../../class2.php');
}
*/
// Apenas executa se a preferência estiver ativa
$prefs = e107::pref('news_fetch');

if (!empty($prefs['use_module_fallback'])) {
    e107::getLog()->add('news_fetch', 'Execução via e_module.php (fallback cron)', E_LOG_INFORMATIVE);

    require_once(e_PLUGIN . 'news_fetch/e_cron.php'); // Reutiliza a mesma classe cron
    require_once(e_HANDLER . 'pref_class.php');

define('NEWSFETCH_INTERVAL', 3600); // Tempo mínimo entre execuções (em segundos)
define('NEWSFETCH_PREF_KEY', 'news_fetch_last_run'); // Chave de preferência

$pref = e107::getPref();
$lastRun = (int) vartrue($pref[NEWSFETCH_PREF_KEY], 0);

if (time() - $lastRun > NEWSFETCH_INTERVAL) {
    // Atualiza a preferência com o novo timestamp
    $pref->set(NEWSFETCH_PREF_KEY, time());
    $pref->save(false, true, true); // (no-cache, override, force-write)

    // Executa a importação
    (new news_fetch_cron)->news_fetch();
}
}