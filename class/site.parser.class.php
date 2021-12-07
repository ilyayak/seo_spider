<?php

/*
todo:
- Параметры сканирования
- Тесты на существование
    - Favicons
    - OpenGraph
    - title
    - description
    - canonical
    - h1
    - ссылки на страницу в sitemap.xml
    - YM
    - GA
    - meta viewport
- Тесты на уникальность
    - title
    - description
    - content
    - h1
- Тесты на проиводительность
    - кол-во и сжатость css
    - кол-во и сжатость js
    - где расположены css и js
    - размеры контента/картинок
- Тест на lazyload
- Тест на ошибки в верстке (https://validator.w3.org/)


- Тест на htpp / https, должен правильно редиректить
- Тест если добавлять удалять слеши с конце
- Тест если добвить в конец случайные символы должен вернуть 404
- Тест микроразметка Хлебных крошек
- Использование webp
- Тест на Last Modified и If-Modified-Since
- ссылки на внешние ресурсы с target="_blacnk" rel="nofollow"
- Тест есть ли формы на всех страницах
*/
include_once('html.parser.class.php');
include_once('sitemap.parser.class.php');
include_once('robots.parser.class.php');

class siteparser
{
    var $site = '',
        $DBname = '',
        $DB,
        $count,
        $params;

    function __construct($site)
    {
        $this->DBname = str_replace(array('https://', 'http://', ':', '/'), '', $site) . '.db';
        $this->DBname = __DIR__ . '/spider_' . $this->DBname;
        $this->DB = new SQLite3($this->DBname);

        $this->create_table();

        $this->site = trim($site, '/');
        $query = 'SELECT COUNT(*) as count FROM url';
        $count = $this->DB->querySingle($query);

        $this->count = $count;

        $this->params = array(
            'SCAN_URLS_PER_STEP' => '3',
            'SKIP_SITEMAP' => 'N',
            'SKIP_ROBOTS' => 'N',
            'SKIP_IMAGES' => 'N',
            'SKIP_HTML' => 'N',
            'SKIP_FILES' => 'N',
            'SKIP_30x_ERROR' => 'N',
            'SKIP_40x_ERROR' => 'N',
            'SKIP_50x_ERROR' => 'N',
            'SKIP_REDIRECT_URLS' => 'N',
            'IGNORE' => ''
        );

    }

    function create_table()
    {

        /* Таблица хранит url страниц */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS url (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT
            );
        ';

        /* Код ответа сайтева на страницу */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS info (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                section TEXT,
                key TEXT,
                value TEXT
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
                datasrc TEXT,
                srcset TEXT,
                datasrcset TEXT,
                alt TEXT,
                title TEXT
            );
        ';

        /* Ошибки */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS errors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                error TEXT,
                section TEXT,
                level TEXT
            );
        ';

        /* Ошибки */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS params (
                setting TEXT,
                value TEXT
            );
        ';

        foreach ($querys as $query) {
            $this->DB->query($query);
        };
    }

    function set_params($params)
    {
        if (is_array($params)) {
            foreach ($this->params as $key=>$val) {
                $this->params[$key] = $params[$key];
            };
        };
        $this->params['IGNORE_preg'] = array();
        if ($this->params['IGNORE'] != '') {
            $arIgnore = explode("\n", $this->params['IGNORE']);
            if (is_array($arIgnore)) {
                foreach ($arIgnore as $ignorestr) {
                    $ignorestr = '|'.str_replace('*', '.*', $ignorestr).'|';
                    $this->params['IGNORE_preg'][] = $ignorestr;
                };
            };
        };
        if ($this->params['SKIP_IMAGES'] == 'Y') {
            $this->params['IGNORE_preg'][] = '|.*\.(gif|png|jpg|jpeg|bmp|svg|ico)|';
        };
        if ($this->params['SKIP_FILES'] == 'Y') {
            $this->params['IGNORE_preg'][] = '|.*\.(doc|docx|xls|xlsx|pdf|zip|)|';
        };

    }

    function load_params() {
        $query = 'SELECT * FROM params';
        $res = $this->DB->query($query);
        while ($row = $res->fetchArray()) {
            if (isset($this->params[$row['setting']])) {
                $this->params[$row['setting']] = $row['value'];
            };
        };
    }

    function save_params() {
        $query = 'DELETE FROM params;';
        $query .= 'INSERT INTO params (setting, value) VALUES ';
        foreach ($this->params as $key=>$val) {
            $query .= '("'.$key.'" , "'.$val.'" ),';
        }
        $this->DB->query($query);
    }
    /******************************** */
    /******************************** */
    /******************************** */

    function scan($start, $limit)
    {
        if ($start == 0) {
            $this->add_url($this->site, $date);
            if ($this->params['SKIP_SITEMAP'] != 'Y') {
                $this->add_url($this->site . '/sitemap.xml', $date);
            };
            if ($this->params['SKIP_ROBOTS'] != 'Y') {
                $this->add_url($this->site . '/robots.txt', $date);
            };
        };

        $count_pages = $start;

        $query = 'SELECT id, url FROM url LIMIT ' . $start . ', ' . $limit;
        $result = $this->DB->query($query);

        while ($row = $result->fetchArray()) {
            $id = $row['id'];
            $url = $row['url'];
            echo '<p>' . $url . ':';
            $res = $this->get_contents($url);

            if ($res['error'] != '') {
                $this->add_error($id, $res['error'], 'SCAN', '5');
                echo 'ERROR - '.$res['error'];
            } else {

                $query = 'INSERT INTO info (id_page, section, key, value) VALUES ';
                $query .= '(' . $id . ', "HEADER", "response_code", "'.$res['info']['http_code'].'"),';
                foreach ($res['info'] as $key=>$value) {
                    if (is_array($value)) {
                        $value = print_r($value, true);
                    };
                    $query .= '(' . $id . ', "HEADER", "'.$key.'", "'.$value.'"),';
                };

                $this->DB->query(trim($query, ','));

                echo $res['info']['http_code'];
                if ($res['info']['http_code'] != 200) {
                    if ($res['info']['http_code'] >= 500) {
                        if ($this->params['SKIP_50x_ERROR'] != 'Y') {
                            $this->add_error($id, '50x', 'SCAN', '9');
                        };
                    } else if ($res['info']['http_code'] >= 400) {
                        if ($this->params['SKIP_40x_ERROR'] != 'Y') {
                            $this->add_error($id, '40x', 'SCAN', '7');
                        };
                    } else if ($res['info']['http_code'] >= 300) {
                        if ($this->params['SKIP_30x_ERROR'] != 'Y') {
                            $this->add_error($id, '30x', 'SCAN', '5');
                        };
                    };
                    if ($url == $this->site . '/robots.txt') {
                        if ($this->params['SKIP_ROBOTS'] != 'Y') {
                            $this->add_error($id, $url.' response code not 200', 'SCAN', '10');
                        };
                    };
                    if ($url == $this->site . '/sitemap.xml') {
                        if ($this->params['SKIP_SITEMAP'] != 'Y') {
                            $this->add_error($id, $url.' response code not 200', 'SCAN', '10');
                        };
                    };
                };

                if ($res['info']['redirect_url'] != '') {
                    echo ' REDIRECT TO '.$res['info']['redirect_url'];
                    if ($this->params['SKIP_REDIRECT_URLS'] != 'Y') {
                        $this->add_url($res['info']['redirect_url']);
                        $this->add_error($id, $url.' REDIRECT TO '.$res['info']['redirect_url'], 'SCAN', '5');
                    };
                };

                if ($res['info']['http_code'] == 200) {

                    if (strpos($res['info']['content_type'], 'text/plain') !== false) {
                        if ($url == $this->site . '/robots.txt') {
                            if ($this->params['SKIP_ROBOTS'] != 'Y') {
                                $this->parse_robots($id, $res['content']);
                            };
                            echo ' ROBOTS.TXT ';
                        };
                    };

                    if (strpos($res['info']['content_type'], 'html') !== false) {
                        if ($this->params['SKIP_HTML'] != 'Y') {
                            $this->parse_html($id, $res['content']);
                        };
                        echo ' HTML ';
                    };

                    if (strpos($res['info']['content_type'], 'xml') !== false) {
                        if (strpos($res['content'], 'sitemap')) {
                            if ($this->params['SKIP_SITEMAP'] != 'Y') {
                                $this->parse_sitemap($id, $res['content']);
                            };
                            echo ' SITEMAP.XML ';
                        } else {
                            echo ' XML ';
                        };
                    };

                    if (strpos($res['info']['content_type'], 'image') !== false) {
                        echo ' IMAGE ';
                    };

                };


            };
            echo '</p>';
            $count_pages++;
        };
        $query = 'SELECT COUNT(*) as count FROM url';
        $count = $this->DB->querySingle($query);

        $this->count = $count;

        return $count_pages;
    }

    function parse_robots($id, $content)
    {
        $robots_parser = new robots_parser();
        $parsed = $robots_parser->parse($content);
        if (is_array($parsed)) {
            foreach ($parsed as $agent) {
                if (is_array($agent)) {
                    if (is_array($agent['host'])) {
                        foreach ($agent['host'] as $host) {
                            if ($host != $this->site) {
                                $this->add_error($id, 'HOST is NOT '.$this->site, 'ROBOTS', '7');
                            };
                        };
                    };
                    if (is_array($agent['sitemap'])) {
                        if ($this->params['SKIP_SITEMAP'] != 'Y') {
                            foreach ($agent['sitemap'] as $url) {
                                $preurl = $this->prepare_url($url);
                                if ($preurl != $url) {
                                    $this->add_error($id, 'SITEMAP '.$url.' NOT '.$preurl, 'ROBOTS', '7');
                                };
                                $id_page = $this->add_url($url);
                                $this->add_source_page($id_page, $id);
                            };
                        };
                    };
                };
            };
        };
    }

    function parse_sitemap($id, $content)
    {
        $sitemap_parser = new sitemap_parser();
        $parsed = $sitemap_parser->parse($content);
        if (is_array($parsed['ERROR'])) {
            foreach($parsed['ERROR'] as $error) {
                $this->add_error($id, $error, 'SITEMAP', '8');
            };
        };
        if (is_array($parsed['sitemap'])) {
            foreach($parsed['sitemap'] as $url) {
                $preurl = $this->prepare_url($url);
                if ($preurl != $url) {
                    $this->add_error($id, 'BAD url "'.$url.'"', 'SITEMAP', '8');
                };
                $id_page = $this->add_url($url);
                $this->add_source_page($id_page, $id);
            };
        };
        if (is_array($parsed['url'])) {
            foreach($parsed['url'] as $url) {
                $preurl = $this->prepare_url($url);
                if ($preurl != $url) {
                    $this->add_error($id, 'BAD url "'.$url.'"', 'SITEMAP', '8');
                };
                $id_page = $this->add_url($url);
                $this->add_source_page($id_page, $id);
            };
        };
    }

    function parse_html($id, $content)
    {
        $htmlparser = new CHtmlParser;
        $parsed = $htmlparser->parse($content);

        $query = 'INSERT INTO info (id_page, section, key, value) VALUES ';
        if (is_array($parsed['title'])) {
            foreach ($parsed['title'] as $title) {
                $query .= '(' . $id . ', "HEAD", "title", "'.$title['text'].'"),';
            };
        };

        if (is_array($parsed['meta'])) {
            foreach ($parsed['meta'] as $meta) {
                $name = '';
                $content = '';
                if ($meta['charset'] != '') {
                    $name = 'charset';
                    $content = $meta['charset'];
                };
                if ($meta['http-equiv'] != '') {
                    $name = $meta['http-equiv'];
                };
                if ($meta['property'] != '') {
                    $name = $meta['property'];
                };
                if ($meta['name'] != '') {
                    $name = $meta['name'];
                };
                if ($meta['content'] != '') {
                    $content = $meta['content'];
                };
                if (($name != '') && ($content != '')) {
                    $query .= '(' . $id . ', "META", "'.$name.'", "'.$content.'"),';
                };
            };
        };

        if (is_array($parsed['link'])) {
            foreach ($parsed['link'] as $link) {
                $name = '';
                $content = '';
                if ($link['type'] != '') {
                    $name = $link['type'];
                };
                if ($link['rel'] != '') {
                    $name = $link['rel'];
                };
                if ($link['href'] != '') {
                    $content = $link['href'];
                };
                if (($name != '') && ($content != '')) {
                    $query .= '(' . $id . ', "LINK", "'.$name.'", "'.$content.'"),';
                };
            };
        };

        $h = 1;
        while ($h < 6) {
            if (is_array($parsed['h' . $h])) {
                foreach ($parsed['h' . $h] as $hcontent) {
                    if ($hcontent['text'] != '') {
                        $query .= '(' . $id . ', "TAG", "H' . $h.'", "'.$hcontent['text'].'"),';
                    }
                }
            };
            $h++;
        };
        if (is_array($parsed['a'])) {
            foreach ($parsed['a'] as $link) {
                $query .= '(' . $id . ', "LINKS", "'.$link['href'].'", "'.$link['text'].'"),';
                $id_page = $this->add_url($link['href']);
                $this->add_source_page($id_page, $id);
            };
        };

        $this->DB->query(trim($query, ','));

        if ($this->params['SKIP_IMAGES'] != 'Y') {
            if (is_array($parsed['images'])) {
                $query = 'INSERT INTO image (id_page, src, datasrc, srcset, datasrcset, alt, title) VALUES';
                $arSrc = ['src', 'datasrc', 'srcset', 'datasrcset'];
                foreach ($parsed['images'] as $img) {
                    $query .= '('.$id.', "'.$img['src'].'",  "'.$img['datasrc'].'", "'.$img['srcset'].'", "'.$img['datasrcset'].'", "'.$img['alt'].'", "'.$img['title'].'"),';
                    foreach ($arSrc as $src) {
                        if ($img[$src] != '') {
                            $id_page = $this->add_url($img[$src]);
                            $this->add_source_page($id_page, $id);
                        };
                    };
                };
                $this->DB->query(trim($query, ','));
            };
        };


    }

    function get_contents($url)
    {

        $result = array();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $result['content'] = $response;
        $result['info'] = $info;

        return $result;
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

    function add_error($id_page, $errortext, $section = "", $level = "Low")
    {
        $query = 'INSERT INTO errors (id_page, error, section, level) VALUES (
            ' . $id_page . ',
            "' . $errortext . '",
            "' . $section . '",
            "' . $level . '"
        );';
        $this->DB->query($query);
    }

    function add_url($url)
    {
        $need_add = false;
        if ((substr($url, 0, 4) != 'tel:') &&
            (substr($url, 0, 7) != 'mailto:')
        ) {
            if ($url != '') {
                $url = $this->prepare_url($url);
                if (is_array($this->params['IGNORE_preg'])) {
                    foreach ($this->params['IGNORE_preg'] as $ignorestr) {
                        if (preg_match($ignorestr, $url)) {
                            $url = '';
                            break;
                        };
                    };
                };
                if (strpos($url, $this->site) !== false) {
                    if ($this->site . '/' != $url) {
                        $query = 'SELECT COUNT(*) as count FROM url WHERE url = "' . $url . '"';
                        $count = $this->DB->querySingle($query);
                        if ($count == 0) {
                            $need_add = true;
                        }
                    };
                };
            };
        } else {
            /* Todo -сохраняить ссылки не по протоколу http/s */
        };

        if ($need_add) {
            $query = 'INSERT INTO url (url) VALUES ("' . $url . '");';
            $this->DB->query($query);
            $ID = $this->DB->lastInsertRowID();
            return $ID;
        };
        return $need_add;
    }

    function add_source_page($id_page, $id_page_source)
    {
        if ((is_numeric($id_page)) && (is_numeric($id_page_source))) {
            $query = 'INSERT INTO source (id_page, id_page_source) VALUES';
            $query .= '('.$id_page.', '.$id_page_source.')';
        }
        $this->DB->query($query);
    }

    function report($type)
    {
        $result = '';
        if ($type == 'main') {
            $result = $this->report_total_errors();
        }
        return $result;
    }

    function report_table($query)
    {
        $result = '';
        if (trim($query) != '') {
            $res = $this->DB->query($query);
            if ($res) {
                $result .= '<table>';
                while ($row = $res->fetchArray()) {
                    $result .= '<tr>';
                    foreach ($row as $col) {
                        $result .= '<td>'.$col.'</td>';
                    }
                    $result .= '</tr>';
                }
                $result .= '</table>';
            };
        };
        return $result;
    }

    function report_total_errors()
    {
        $result = '';
        $query = 'SELECT * FROM url JOIN errors ON url.id = errors.id_page ORDER BY errors.level DESC';
        $result = $this->report_table($query);
        return $result;

    }

    function report_urls()
    {
        $query = 'SELECT * FROM url';
        $result = $this->report_table($query);
        return $result;
    }

    function report_urls_response_code()
    {
        $query = 'SELECT url.url, info.value FROM url JOIN info ON url.id = info.id_page WHERE  ';
        $result = $this->report_table($query);
        return $result;
    }
}