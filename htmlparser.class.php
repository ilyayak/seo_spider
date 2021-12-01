<?
class CHtmlParser {
    function static parse($content) {
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
        $meta  = $this->($content);
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
                $result['links'][] = array($match[2], $match[3]);
            }
        }
        return $result;
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
}