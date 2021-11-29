<?php
class siteparser
{
    var $site = '',
        $DBname = '',
        $DB,
        $count;

    function __construct($site)
    {
        $this->DBname = str_replace(array('https://', 'http://', ':', '/'), '', $site) . '.db';
        $this->DBname = __DIR__ . '/spider_' . $this->DBname;
        $this->DB = new SQLite3($this->DBname);

        $this->create_table();

        $this->site = trim($site, '/');
        $query = 'SELECT COUNT(*) as count FROM page';
        $count = $this->DB->querySingle($query);

        $this->count = $count;
    }

    function create_table()
    {

        /* Таблица хранит url страниц */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS page (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT
            );
        ';

        /* Таблица хранит url sitemaps */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS sitemap (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT
            );
        ';

        /* Код ответа сайтева на страницу */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS code (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                responce_code TEXT
            );
        ';

        /* Мета теши и тайтл страницы */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS meta (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                name TEXT,
                content TEXT
            );
        ';

        /* Link canonical, styles, alternate страницы */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS link (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                rel TEXT,
                href TEXT
            );
        ';

        /* h1-h6 на странице */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS h16 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                name TEXT,
                content TEXT
            );
        ';

        /* Источники ссылок на страницу */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS source (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                id_page_source INTEGER
            );
        ';

        /* Картинки */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS image (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                src TEXT,
                alt TEXT,
                title TEXT
            );
        ';

        /* Ошибки */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS errors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                error TEXT
            );
        ';

        foreach ($querys as $query) {
            $this->DB->query($query);
        };
    }

    /******************************** */
    /******************************** */
    /******************************** */

    function scan($start, $limit)
    {
        if ($start == 0) {
            $this->add_url($this->site, $date);
            $this->add_url($this->site . '/sitemap.xml', $date);
            $this->add_url($this->site . '/robots.txt', $date);
        };

        $count_pages = $start;

        $query = 'SELECT id, url FROM page LIMIT ' . $start . ', ' . $limit;
        $result = $this->DB->query($query);

        while ($row = $result->fetchArray()) {
            $id = $row['id'];
            $url = $row['url'];

            $res = $this->get_contents($url);

            if ($res['error'] != '') {
                $query = 'INSERT INTO errors (id_page, error) VALUES (
                    ' . $id . ',
                    "' . $res['error'] . '"
                );';
                $this->DB->query($query);
                $query = 'INSERT INTO code (id_page, responce_code) VALUES (
                    ' . $id . ',
                    "LOOK ERROR"
                );';
                $this->DB->query($query);
            } else {
                $query = 'INSERT INTO code (id_page, responce_code) VALUES (
                    ' . $id . ',
                    "' . $res['header']['reponse_code'] . '"
                );';
                $this->DB->query($query);

                if ($res['header']['reponse_code'] == 200) {
                    if ($url == $this->site . '/sitemap.xml') {
                    } else if ($url == $this->site . '/robots.txt') {
                    } else {
                        $res2 = $this->parse_page($res['content'], $id);

                        $query = 'INSERT INTO meta (id_page, name, content) VALUES (
                            ' . $id . ',
                            "title",
                            "' . $res2['title'] . '"
                        );';
                        $this->DB->query($query);

                        if (is_array($res2['meta'])) {
                            foreach ($res2['meta'] as $name => $content) {
                                $query = 'INSERT INTO meta (id_page, name, content) VALUES (
                                    ' . $id . ',
                                    "' . $name . '",
                                    "' . $content . '"
                                );';
                                $this->DB->query($query);
                            }
                        }

                        if (is_array($res2['metalink'])) {
                            foreach ($res2['meta'] as $name => $values) {
                                if (is_array($values)) {
                                    foreach ($values as $href) {
                                        $query = 'INSERT INTO meta (id_page, rel, href, full) VALUES (
                                            ' . $id . ',
                                            "' . $name . '",
                                            "' . $val . '",
                                            "' . $val . '"
                                        );';
                                        $this->DB->query($query);
                                    };
                                };
                            };
                        };

                        $h = 1;
                        while ($h < 6) {
                            if (is_array($res2['h' . $h])) {
                                foreach ($res2['h' . $h] as $hcontent) {
                                    $query = 'INSERT INTO h16 (id_page, name, content) VALUES (
                                        ' . $id . ',
                                        "' . 'h' . $h . '",
                                        "' . $hcontent . '"
                                    );';
                                    $this->DB->query($query);
                                }
                            };
                            $h++;
                        };
                        echo '<p>' . $url . ' (new links: ' . count($res2['links']) . ')</p>';
                    }
                }
            };
            $count_pages++;
        };
        $query = 'SELECT COUNT(*) as count FROM page';
        $count = $this->DB->querySingle($query);

        $this->count = $count;

        return $count_pages;
    }

    function getMetaTags($str)
    {
        $pattern = '
      ~<\s*meta\s

      # using lookahead to capture type to $1
        (?=[^>]*?
        \b(?:name|property|http-equiv)\s*=\s*
        (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
        ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
      )

      # capture content to $2
      [^>]*?\bcontent\s*=\s*
        (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
        ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
      [^>]*>

      ~ix';

        if (preg_match_all($pattern, $str, $out))
            return array_combine($out[1], $out[2]);
        return array();
    }


    function getMetaLinks($str)
    {
        $pattern = '
      ~<\s*link\s

      # using rel to $1
        (?=[^>]*?
        \b(?:rel)\s*=\s*
        (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
        ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
      )

      # capture href to $2
      [^>]*?\bhref\s*=\s*
        (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
        ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
      [^>]*>

      ~ix';
        $result = [];
        if (preg_match_all($pattern, $str, $out)) {
            foreach ($out[1] as $k => $v) {
                $result[$v][] = $out[2][$k];
            }
        }
        return $result;
    }

    function parse_page($content, $id_page_source)
    {
        /* title */
        $matches = [];
        $res = preg_match("/<title>(.*)<\/title>/siU", $content, $matches);
        if (!$res) {
            $result['title'] = '';
        } else {
            $title = preg_replace('/\s+/', ' ', $matches[1]);
            $title = trim($title);
            $result['title'] = $title;
        };

        /* meta tags */
        $meta  = $this->getMetaTags($content);
        $result['meta'] = $meta;

        /* link в head */
        $metalink  = $this->getMetaLinks($content);
        $result['metalink'] = $metalink;

        /* h1 - h6 */
        $i = 1;
        while ($i < 6) {
            $matches = [];

            $res = preg_match_all('/<h' . $i . '.*>(.*)<\/h' . $i . '>/siU', $content, $matches);
            $result['h' . $i] = array();
            if (!$res) {
            } else {
                foreach ($matches[1] as $match) {
                    $h = preg_replace('/\s+/', ' ', $match);
                    $h = trim(strip_tags($h));
                    $result['h' . $i][] .= $h;
                };
            };
            $i++;
        };


        $result['links'] = array();
        if (preg_match_all("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $url = $this->prepare_url($match[2]);
                if ($this->add_url($url)) {
                    $result['links'][] = $url;
                };
                $query = 'SELECT id FROM page WHERE url ="' . $url . '";';
                $id_page = $this->DB->querySingle($query);

                if (is_numeric($id_page)) {
                    $query = 'INSERT INTO source (id_page, id_page_source) VALUES (
                        ' . $id_page . ',
                        ' . $id_page_source . '
                    );';
                    $this->DB->query($query);
                }
                // $match[2] = link address
                // $match[3] = link text
            }
        }
        return $result;
    }

    function get_contents($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            $result['error'] = 'This is not url';
        } else {
            $result['content'] = @file_get_contents($url);
            $result['header'] = $this->parseHeaders($http_response_header);
            if (($result['content'] == '') && ($http_response_header === NULL)) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $result['content'] = $response;
                $result['header']['reponse_code'] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            }
        }
        return $result;
    }

    function parseHeaders($headers)
    {
        $head = array();
        foreach ($headers as $k => $v) {
            $t = explode(':', $v, 2);
            if (isset($t[1]))
                $head[trim($t[0])] = trim($t[1]);
            else {
                $head[] = $v;
                if (preg_match("#HTTP/[0-9\.]+\s+([0-9]+)#", $v, $out)) {
                    $head['reponse_code'] = intval($out[1]);
                }
            }
        }
        return $head;
    }

    function prepare_url($url)
    {
        list($url, $hashtag) = explode('#', $url);
        if ($url != '') {

            $need_add_site = false;
            if ((substr($url, 0, 6) != 'https:') &&
                (substr($url, 0, 5) != 'http:') &&
                (substr($url, 0, 2) != '//')
            ) {
                $need_add_site = true;
            };

            if ($need_add_site) {
                if (substr($url, 0, 1) != '/') {
                    $url = '/' . $url;
                };
                $url = $this->site . $url;
            };
        };
        return $url;
    }

    function add_url($url)
    {
        $need_add = false;

        if ((substr($url, 0, 4) != 'tel:') &&
            (substr($url, 0, 7) != 'mailto:')
        ) {
            if ($url != '') {
                if (strpos($url, $this->site) !== false) {
                    if ($this->site . '/' != $url) {
                        $query = 'SELECT COUNT(*) as count FROM page WHERE url = "' . $url . '"';
                        $count = $this->DB->querySingle($query);
                        if ($count == 0) {
                            $need_add = true;
                        }
                    };
                };
            };
        } else {
            /* Todo -сохраняить ссылки не по протоколу http */
        };

        if ($need_add) {
            $query = 'INSERT INTO page (url) VALUES ("' . $url . '");';
            $this->DB->query($query);
            $ID = $this->DB->lastInsertRowID();
            return $url;
        };
        return $need_add;
    }

    function report($type)
    {

        if ($type == 'main') {
            $result = '<table>';
            $query = 'SELECT * FROM page';
            $res = $this->DB->query($query);
            $result .= '<tr>';
            $result .= '<th>URL</th>';
            $result .= '<th>Title</th>';
            $result .= '<th>H1</th>';
            $result .= '<th>Keyword</th>';
            $result .= '<th>Description</th>';
            $result .= '</tr>';
            $count = 0;
            while ($page = $res->fetchArray()) {

                $result .= '<tr>';
                $result .= '<td>';
                $result .= '<a href="' . $page['url'] . '" target="_blank">' . $page['url'] . '</a>';
                $result .= '</td>';
                $query = 'SELECT name, content FROM meta WHERE name = "title" AND id_page = "' . $page['id'] . '"';
                $restitle = $this->DB->query($query);
                $result .= '<td>';
                while ($title = $restitle->fetchArray()) {
                    $result .= '<p>' . $title['content'] . '</p>';
                };
                $result .= '</td>';

                $query = 'SELECT name, content FROM h16 WHERE name = "h1" AND id_page = "' . $page['id'] . '"';
                $resh1 = $this->DB->query($query);
                $result .= '<td>';
                while ($h1 = $resh1->fetchArray()) {
                    $result .= '<p>' . $h1['content'] . '</p>';
                };

                $query = 'SELECT name, content FROM meta WHERE name = "keyword" AND id_page = "' . $page['id'] . '"';
                $reskeyword = $this->DB->query($query);
                $result .= '<td>';
                while ($keyword = $reskeyword->fetchArray()) {
                    $result .= '<p>' . $keyword['content'] . '</p>';
                };


                $query = 'SELECT name, content FROM meta WHERE name = "description" AND id_page = "' . $page['id'] . '"';
                $resdescription = $this->DB->query($query);
                $result .= '<td>';
                while ($description = $resdescription->fetchArray()) {
                    $result .= '<p>' . $description['content'] . '</p>';
                };



                $result .= '</td>';

                $result .= '</tr>';

                $count ++;
            }
            $result .= '</table>';
        }
        return $result;
    }
}
