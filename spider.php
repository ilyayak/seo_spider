<?
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

        /* История сканирования */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT,
                errors TEXT,
                todo TEXT,
                comment TEXT
            );
        ';

        /* Таблица хранит url страниц */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS page (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT
            );
        ';

        /* Код ответа сайтева на страницу */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS code (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                responce_code TEXT,
                date TEXT
            );
        ';

        /* Мета теши и тайтл страницы */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS meta (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                name TEXT,
                content TEXT,
                date TEXT
            );
        ';

        /* Link canonical, styles, alternate страницы */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS link (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                rel TEXT,
                href TEXT,
                full TEXT,
                date TEXT
            );
        ';

        /* h1-h6 на странице */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS h16 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                name TEXT,
                content TEXT,
                date TEXT
            );
        ';

        /* Источники ссылок на страницу */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS source (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                id_page_source INTEGER,
                date TEXT
            );
        ';

        /* Картинки */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS image (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                src TEXT,
                alt TEXT,
                title TEXT,
                date TEXT
            );
        ';

        /* Ошибки */
        $querys[] = '
            CREATE TABLE IF NOT EXISTS errors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_page INTEGER,
                error TEXT,
                date TEXT
            );
        ';

        foreach ($querys as $query) {
            $this->DB->query($query);
        };

        $this->site = trim($site, '/');
        $query = 'SELECT COUNT(*) as count FROM page';
        $count = $this->DB->querySingle($query);
        if ($count == 0) {
            $this->add_url($this->site);
        }
        $this->count = $count;
    }

    function do($start, $limit, $date)
    {
        $i = 0;
        if ($start = 0) {
            /* Запишем в историю что начали сканирование */
            $query = 'SELECT COUNT(*) as count FROM history WHERE date="'.$date.'";';
            $count_history = $this->DB->querySingle($query);
            if ($count_history == 0) {
                $query = 'INSERT INTO history (date) VALUES ("'.$date.'")';
                $this->DB->query($query);
            };
        };

        $query = 'SELECT page.id as id, page.url as url
                    FROM page
                    WHERE
                        page.id NOT IN (
                            SELECT id_page FROM code WHERE code.date =="'.$date.'"
                        )
                    LIMIT '.$start.', '.$limit;
        $result = $this->DB->query($query);

        while ($row = $result->fetchArray()) {
            $id = $row['id'];
            $url = $row['url'];

            $res = $this->get_contents($url);

            if ($res['error'] != '') {
                $query = 'INSERT INTO errors (id_page, error, date) VALUES (
                    '.$id.',
                    "'.$res['error'].'",
                    "'.$date.'"
                );';
                $this->DB->query($query);
                $query = 'INSERT INTO code (id_page, responce_code, date) VALUES (
                    '.$id.',
                    "LOOK ERROR",
                    "'.$date.'"
                );';
                $this->DB->query($query);

            } else {
                $query = 'INSERT INTO code (id_page, responce_code, date) VALUES (
                    '.$id.',
                    "'.$res['header']['reponse_code'].'",
                    "'.$date.'"
                );';
                $this->DB->query($query);

                if ($res['header']['reponse_code'] == 200) {
                    $res2 = $this->parse_page($res['content'], $id);

                    $query = 'INSERT INTO meta (id_page, name, content, date) VALUES (
                        '.$id.',
                        "title",
                        "'.$res2['title'].'",
                        "'.$date.'"
                    );';
                    $this->DB->query($query);

                    foreach ($res2['meta'] as $name=>$content) {
                        $query = 'INSERT INTO meta (id_page, name, content, date) VALUES (
                            '.$id.',
                            "'.$name.'",
                            "'.$content.'",
                            "'.$date.'"
                        );';
                        $this->DB->query($query);
                    }

                    $h = 1;
                    while ($h < 6) {
                        if (is_array($res2['h'.$h])) {
                            foreach ($res2['h'.$h] as $hcontent) {
                                $query = 'INSERT INTO h16 (id_page, name, content, date) VALUES (
                                    '.$id.',
                                    "'.'h'.$h.'",
                                    "'.$hcontent.'",
                                    "'.$date.'"
                                );';
                                $this->DB->query($query);
                            }
                        };
                        $h++;
                    };
                    echo '<p>' . $url . ' (new links: '.count($res2['links']).')</p>';
                }
            };
            $i++;
        };
        return $i + $start;
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

        if (preg_match_all($pattern, $str, $out))
            return array_combine($out[1], $out[2]);
        return array();
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

        $metalink  = $this->getMetaLinks($content);
        $result['metalink'] = $metalink;
        print_r($metalink);
        die();
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
                $query = 'SELECT id FROM page WHERE url ="'.$url.'";';
                $id_page = $this->DB->querySingle($query);

                if (is_numeric($id_page)) {
                    $query = 'INSERT INTO source (id_page, id_page_source, date) VALUES (
                        '.$id_page.',
                        '.$id_page_source.',
                        "'.$date.'"
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
            $result['content'] = file_get_contents($url);
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

    function prepare_url($url) {
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
                    if ($this->site.'/' != $url) {
                        $query = 'SELECT COUNT(*) as count FROM page WHERE url = "'.$url.'"';
                        $count = $this->DB->querySingle($query);
                        if ($count == 0) {
                            $need_add = true;
                        }
                    };
                };
            };
        };

        if ($need_add) {
            $query = 'INSERT INTO page (url) VALUES ("'.$url.'");';
            $this->DB->query($query);
            $ID = $this->DB->lastInsertRowID();
            return $url;
        };
        return $need_add;
    }


}


?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Spider</title>
</head>

<body>
    <h1>SEO Сканер</h1>
    <?
    $SITE = '';
    if ($_REQUEST['site'] != '') {
        if (filter_var($url, FILTER_VALIDATE_URL) !== FALSE) {
            $SITE = $_REQUEST['site'];
        };
    };
    if ($SITE != '') {
        $spider = new siteparser($SITE);
    };
    if (($SITE != '') && ($_REQUEST['date'] != '')) {
        $date = $_REQUEST['date'];
        /******************** */
        /* Сканирование сайта */
        if ($_REQUEST['a'] == 'scan') {

            $nstart = 0 + $_REQUEST['n'];
            $limit = max(1, $_REQUEST['limit'];)
            $nfinish = $spider->do($nstart, $limit, $date);
            echo '<p>Проанализировано: ' . $nfinish . ' / '.$spider->count.'</p>';
            $link = '?a=scan&site=' . $SITE . '&date=' . $date . '&n=' . $nfinish;
            echo '<p><a href="' . $link . '">Далее</a></p>';
            if ($nfinish > $nstart) {
                ?>
                <script>
                    setTimeout(function() {
                        location = "<?=$link?>";
                    }, 2000);
                </script>
                <?
            };
        };
        /******************** */
        /* Сканирование сайта */
        if ($_REQUEST['a'] == 'report') {

        };
    } else  if ($SITE != '') {
        echo '<h2>'.$SITE.'</h2>';
        echo '<p>Известно страниц: '.$spider->count.'</p>';

    ?>
        <h3>Cканировать</h3>
        <form action="">
            <input type="hidden" name="a" value="scan">
            <input type="hidden" name="site" value="<?= $_REQUEST['site']?>">
            <input type="hidden" name="date" value="<?= date('Y.m.d H:i:s') ?>">

            <p>
                <label title="Сколько страниц сканировать за один шаг">Шаг сканирования (страниц)</label>
                <input type="number" name="limit" value="3">
            </p>
            <p>
                <input type="submit" name="submit" value="Сканировать">
            </P>
        </form>

        <!-- todo
        <h3>Истории сканирования</h3>
        <form action="?a=report" target="_blank">
            <input type="hidden" name="site" value="<?= $_REQUEST['site']?>">
            <input type="hidden" name="date" value="<?= date('Y.m.d H:i:s') ?>">
            <input type="submit" name="submit" value="Отчет">
        </form>
        -->
    <? } else { ?>
        <h3>Начало</h3>
        <form action="">
            <input type="text" name="site" placeholder="https://site.ru">
            <input type="submit" name="submit" value="Начать">
        </form>


    <? } ?>
</body>

</html>