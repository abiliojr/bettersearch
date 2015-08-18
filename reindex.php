#!/usr/bin/env php
<?php

/**
 * Use this tool to reindex your wiki
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Abilio Marques <https://github.com/abiliojr>
 */

if(!defined('DOKU_ROOT')) define('DOKU_ROOT', realpath(dirname(__FILE__).'/../../../').'/');
define('NOSESSION', 1);

require_once(DOKU_ROOT.'inc/init.php');

global $conf;

$sphinx = new SphinxSearch();

// clear the index
idx_get_indexer()->clear();


// must complete the basic indexing first
search($data, $conf['datadir'], 'search_allpages', array('skipacl' => true));

foreach($data as $val) {
	idx_addPage($val['id'], false, true);
}


// only now the backlinks counters in the dokuwiki index are valid
// so lets update sphinxsearch to reflect them

$pages = idx_get_indexer()->getPages();

foreach($pages as $page) {
	$namespace = getNS($page);
	if (!$namespace) $namespace = 'root';
	$title = str_replace($conf['sepchar'], ' ', noNS($page));
	$sphinx->updateReferences($namespace, $title, count(ft_backlinks($page, true)));
}
