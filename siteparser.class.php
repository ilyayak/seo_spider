<?php

/*
todo:
1) Парсеры
    - sitemap.xml
    - robots.txt
2) Тесты на существование
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
3) Тесты на уникальность
    - title
    - description
    - content
    - h1
4) Тесты на проиводительность
    - кол-во и сжатость css
    - кол-во и сжатость js
    - где расположены css и js
    - размеры контента/картинок
5) Тест на lazyload
6) Тест на ошибки в верстке (https://validator.w3.org/)
7) Тест урлов на 404
8) Тест урлов на 301
9) Тест на htpp / https, должен правильно редиректить
10) Тест если добавлять удалять слеши с конце
11) Тест если добвить в конец случайные символы должен вернуть 404
12) Тест микроразметка Хлебных крошек
13) Использование webp
14) Тест на Last Modified и If-Modified-Since
15) ссылки на внешние ресурсы с target="_blacnk" rel="nofollow"

*/
include_once('htmlparser.class.php');

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

        $query = 'SELECT id, url FROM url LIMIT ' . $start . ', ' . $limit;
        $result = $this->DB->query($query);

        while ($row = $result->fetchArray()) {
            $id = $row['id'];
            $url = $row['url'];
            echo '<p>' . $url . ':';
            $res = $this->get_contents($url);

            if ($res['error'] != '') {
                $query = 'INSERT INTO errors (id_page, error) VALUES (
                    ' . $id . ',
                    "' . $res['error'] . '"
                );';
                $this->DB->query($query);
                echo 'ERROR - '.$res['error'];
            } else {
                
                $query = 'INSERT INTO info (id_page, section, key, value) VALUES ';
                $query .= '(' . $id . ', "HEADER", "response_code", "'.$res['info']['http_code'].'"),';
                foreach ($res['info'] as $key=>$value) {
                    $query .= '(' . $id . ', "HEADER", "'.$key.'", "'.$value.'"),';
                };

                $this->DB->query(trim($query, ','));

                echo $res['info']['http_code'];

                if ($res['info']['http_code'] == 200) {
                    if (strpos($res['info']['content_type'], 'html') !== false) { 
                        $this->parse_html($id, $res['content']);
                        echo '  HTML ';
                    }
                }

            };
            echo '</p>';
            $count_pages++;
        };
        $query = 'SELECT COUNT(*) as count FROM page';
        $count = $this->DB->querySingle($query);

        $this->count = $count;

        return $count_pages;
    }

    function parse_html($id, $content)
    {
        $res2 = CHtmlParser::parse($content);
        
        $query = 'INSERT INTO info (id_page, section, key, value) VALUES ';
        $query .= '(' . $id . ', "HEAD", "title", "'.$res2['title'].'"),';

        if (is_array($res2['meta'])) {
            foreach ($res2['meta'] as $name => $content) {
                $query .= '(' . $id . ', "META", "'.$name.'", "'.$content.'"),';
            }
        }

        if (is_array($res2['metalink'])) {
            foreach ($res2['metalink'] as $name => $values) {
                if (is_array($values)) {
                    foreach ($values  as $content) {
                        $query .= '(' . $id . ', "LINK", "'.$name.'", "'.$content.'"),';
                    };
                };
            };
        };

        $h = 1;
        while ($h < 6) {
            if (is_array($res2['h' . $h])) {
                foreach ($res2['h' . $h] as $hcontent) {
                    $query .= '(' . $id . ', "TAG H", "h' . $h.'", "'.$hcontent.'"),';
                }
            };
            $h++;
        };
        if (is_array($res2['links'])) {
            foreach ($res2['links'] as $link) {
                
                $id_page = $this->add_url($link[0]);
                if ($id_page) {
                    $query = 'INSERT INTO source (id_page, id_page_source) VALUES';
                    $query .= '('.$id_page.', '.$id.')';
                }
            }
        }

        $this->DB->query(trim($query, ','));
        
    }

    function get_contents($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            $result['error'] = 'This is not url';
        } else {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $info = curl_getinfo($ch);
                $result['content'] = $response;
                $result['info'] = $info;
            }
        }
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

    function add_url($url)
    {
        $need_add = false;

        if ((substr($url, 0, 4) != 'tel:') &&
            (substr($url, 0, 7) != 'mailto:')
        ) {
            if ($url != '') {
                $url = $this->prepare_url($url);
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
