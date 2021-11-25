<?
class siteparser
{
    var $site = '',
        $pages_links = array(),
        $pages = array(),
        $filename = '',
        $tmp_filename = '',
        $download = '';


    function __construct($site, $date)
    {
        $this->site = trim($site, '/');
        $this->filename = str_replace(array('https://', 'http://', ':', '/'), '', $site) . '_' . $date . '.csv';
        $this->tmp_filename =  $this->filename . '.tmp';
        $this->download = '/spider_' . $this->filename;
        $this->filename = __DIR__ . '/spider_' . $this->filename;
        $this->tmp_filename = __DIR__ . '/spider_' . $this->tmp_filename;
        if (file_exists($this->tmp_filename)) {

            $links = file($this->tmp_filename);
            if (is_array($links)) {
                foreach ($links as $url) {
                    $url = trim($url);
                    $this->pages_links[$url] = '-';
                };
            };
        } else {
            $this->add_url($this->site);
        };
    }

    function do($start, $count)
    {
        $i = 0;

        $link_to_parse = array_slice($this->pages_links, $start, $count);

        foreach ($link_to_parse as $url => $r) {
            $res = $this->get_contents($url);

            if ($res['error'] != '') {
                $this->pages[$url]['response'] = $res['error'];
            } else if ($res['header']['reponse_code'] != 200) {
                $this->pages[$url]['response'] = $res['header']['reponse_code'];
            } else {
                $res2 = $this->parse_page($res['content']);

                $this->pages[$url] =  array(
                    'response'          => $res['header']['reponse_code'],
                    'title'             => $res2['title'],
                    'description'       => $res2['meta']['description'],
                    'keywords'          => $res2['meta']['keywords'],
                    'h1'                => implode(', ', $res2['h1']),
                    'h2'                => implode(', ', $res2['h2']),
                    'h3'                => implode(', ', $res2['h3']),
                    'h4'                => implode(', ', $res2['h4']),
                    'h5'                => implode(', ', $res2['h5']),
                    'h6'                => implode(', ', $res2['h6']),
                );
                echo '<p>' . $url . ' (new links: '.count($res2['links']).')</p>';
            };
            $i++;
        };
        $this->save();
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

    function parse_page($content)
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



        if (preg_match_all("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if ($this->add_url($match[2])) {
                    $result['links'][] = $match[2];
                };
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

    function add_url($url)
    {
        $need_add = false;
        if (!isset($this->pages_links[$url])) {

            if ((substr($url, 0, 4) != 'tel:') &&
                (substr($url, 0, 7) != 'mailto:')
            ) {
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

                    if (strpos($url, $this->site) !== false) {
                        if ($this->site.'/' != $url) {
                            if (!isset($this->pages_links[$url])) {
                                $need_add = true;
                            };
                        };
                    };
                };
            };
        };

        if ($need_add) {
            $this->pages_links[$url] = '-';
            file_put_contents($this->tmp_filename, $url . "\n", FILE_APPEND);
        };
        return $need_add;
    }

    function save()
    {
        foreach ($this->pages as $url => $value) {
            if (!file_exists($this->filename)) {
                $str = 'URL;' .
                    'response' . ';' .
                    'title' . ';' .
                    'description' . ';' .
                    'keywords' . ';' .
                    'h1' . ';' .
                    'h2' . ';' .
                    'h3' . ';' .
                    'h4' . ';' .
                    'h5' . ';' .
                    'h6' . ';';
                file_put_contents($this->filename, $str . "\n", FILE_APPEND);
            };
            $str = $url . ';' . implode(';', $value);
            $str = iconv('utf-8//IGNORE', 'windows-1251//IGNORE', $str);
            file_put_contents($this->filename, $str . "\n", FILE_APPEND);
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Spider</title>
</head>

<body>
    <?
    if (($_REQUEST['site'] != '') && ($_REQUEST['date'] != '')) {
        $spider = new siteparser($_REQUEST['site'], $_REQUEST['date']);
        $n = 0 + $_REQUEST['n'];
        $n = $spider->do($n, 10);
        echo '<p>Проанализировано: ' . $n . ' / '.count($spider->pages_links).'</p>';
        $link = '?site=' . $_REQUEST['site'] . '&date=' . $_REQUEST['date'] . '&n=' . $n;
        echo '<p><a href="' . $link . '">Далее</a></p>';
        echo '<p><a href="' . $spider->download . '">Смотреть промежуточный результат</a></p>';
        if ($n > 0 + $_REQUEST['n']) {
            ?>
            <script>
                setTimeout(function() {
                    location = "<?=$link?>";
                }, 2000);
            </script>
            <?
        };
    } else {
    ?>
        <form action="">
            <input type="text" name="site" placeholder="https://site.ru">
            <input type="hidden" name="date" value="<?= date('YmdHis') ?>">
            <input type="submit" name="submit" value="Сканировать">
        </form>
    <? } ?>
</body>

</html>