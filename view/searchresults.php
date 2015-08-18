<?php
/**
* Better Search for DokuWiki (HTML Generator)
  *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Abilio Marques <https://github.com/abiliojr>
 */

class SearchResults {

    public function noSphinxConnection() {
        echo '<div style="color: red; margin: 1em 0em 2em;">Warning: no connection to Sphinx</div>';
    }

    private function echoCSS() {
        echo '<style>';
        echo '.pageSelector {margin-top: 4em; text-align:center;}';
        echo '.pageSelector .left {font-size: 1.4em; margin: 0px 10px 0px 0px;}';
        echo '.pageSelector .right {font-size: 1.4em; margin: 0px 0px 0px 10px;}';
        echo '.pageSelector .centralColumn {display: inline; width: 40px;}';
        echo '.pageSelector .column {display: inline;}';
        echo '.stats {color: #999; font-size: 0.8em; text-align:center;}';
        echo '</style>';
    }


    private function echoBasicHeader() {
        $this->echoCSS();
        echo '<h1 class="sectionedit1" id="search">Search</h1>';
        echo    '<div class="level1">' .
                '<p>' .
                'You can find the results of your search below. If you didn\'t find what you were looking for, ' . 
                'you can create or edit the page named after your query with the appropriate tool.' .
                '</p>' .
                '</div>';
        echo '<h2 class="sectionedit2" id="results">Results</h2>';

    }


    public function renderResults($query, $results, $meta, $m, $n) {
        $this->echoBasicHeader();

        if (empty($results)) {
            echo "Your search - <b>$query</b> - did not match any documents."; 
            return;
        }

        echo '<dl class="search_results">';
        foreach ($results as $item) {
            $this->renderResultItem($item);
        }
        echo '</dl>';

        $this->renderPageSelector($m, 0 + $meta['total_found'], $n);
        $this->renderFooter($meta);
    }


    private function renderResultItem($item) {
        global $conf;
        $item['namespace'] .= ':';
        if ($item['namespace'] === 'root:') $item['namespace'] = '';

        $completeName = $item['namespace'] . str_replace(' ', $conf['sepchar'], $item['title']);

        $url = wl($completeName) . "&pragma=tracksearch";

        // done here instead of doing it at sphinx because it doesn't support
        // whitespaces in the "before_match" parameter
        $data = preg_replace (array('/<b>/','/<\/b>/'),
                    array('<strong class="search_hit">','</strong>'), 
                    $item['contentsnippet']);

        $a = '<a href="' . $url . '" class="wikilink1" title="' . $completeName . '"> ' . 
                $item['namespace'] . $item['titlesnippet'] . '</a>';

        echo "<dt>$a</dt><dd>$data</dd>";
    }


    private function renderFooter($data) {
        echo '<div class="stats">' . $data['total_found'] . ' results (' . ($data['time'] * 1000) . ' ms)</div>';
    }


    private function renderPageSelector($currentPos, $totalItems, $itemsPerPage) {
        $base_url = preg_replace('/&m=[0-9]+/','', $_SERVER[REQUEST_URI]);
        $base_url = preg_replace('/&n=[0-9]+/','', $base_url) . '&n=' . $itemsPerPage;

        $totalPages = (int) ceil($totalItems / $itemsPerPage);
        
        echo '<div class="pageSelector">';

        echo '<div class="column">';
        if ($currentPos != 1) {
            // render back buttons
            echo "<a class=\"left\" href=\"$base_url&m=1\">&laquo;</a>";
            echo "<a class=\"left\" href=\"$base_url&m="  . ($currentPos - 1) . '">&lsaquo;</a>';
        }
        echo '</div>';
        
        echo '<div class="centralColumn">';
        echo "Page $currentPos of $totalPages";
        echo '</div>';

        echo '<div class="column">';
        if ($currentPos < $totalPages) {
            //render forward buttons
            echo "<a class=\"right\" href=\"$base_url&m=" . ($currentPos + 1) . '">&rsaquo;</a>';
            echo "<a class=\"right\" href=\"$base_url&m=$totalPages\">&raquo;</a>";
        }
        echo '</div>';
        echo '</div>';

    }
}