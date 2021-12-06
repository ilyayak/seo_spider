<?
class CHtmlParser {
    function parse($content) {

        /* title */
        $result['title'] = $this->get_tags('title', $content);

        /* meta tags */
        $result['meta']  = $this->get_tags('meta', $content);

        /* link в head */
        $result['metalink']  = $this->get_tags('link', $content);

        /* h1 - h6 */
        $result['h1'] = $this->get_tags('h1', $content);
        $result['h2'] = $this->get_tags('h2', $content);
        $result['h3'] = $this->get_tags('h3', $content);
        $result['h4'] = $this->get_tags('h4', $content);
        $result['h5'] = $this->get_tags('h5', $content);
        $result['h6'] = $this->get_tags('h6', $content);

        /* links */
        $result['links'] = $this->get_tags('a', $content);

        /* images */
        $result['images'] = $this->get_tags('img', $content);

        return $result;
    }

    function getTitle($content) {
        /* title */
        $result = '';
        $matches = [];
        $res = preg_match("/<title>(.*)<\/title>/siU", $content, $matches);
        if ($res) {
            $title = preg_replace('/\s+/', ' ', $matches[1]);
            $title = trim($title);
            $result = $title;
        };
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

    function getH16($content) 
    {
        $result = array();
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
        return $result;
    }

    function getLinks($content)
    {
        $result = array();
        if (preg_match_all("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $content, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $result[] = array($match[2], $match[3]);
            };
        };
        return $result;
    }

    function getImages($content) 
    {
        
        $result = array();
        if (preg_match_all('/<img[^>]+>/i',$content, $matches)) {
            foreach ($matches[0] as $match) {
                preg_match_all('/(alt|title|src|data-src|srcset|data-srcset)=("[^"]*")/i', $match, $img_matches);
                $img = array();
                foreach ($img_matches[1] as $key=>$val) {
                    $img[$val] = trim($img_matches[2][$key], '"');
                };
                $result[] = $img;
            };
        };
        return $result;
    }

    function get_tags($tag, $content) {

        $arTags['meta'] = array(
            'tag' => '/<meta[^>]+>/i',
            'attr'=> '/(name|property|http-equiv|charset|content)=("[^"]*")/i'
        );
        $arTags['link'] = array(
            'tag' => '/<link[^>]+>/i',
            'attr'=> '/(rel|href|type|media|sizes)=("[^"]*")/i'
        );
        $arTags['title'] = array(
            'tag' => '/<title[^>]+>([^<]+)<\/title>/i',
        );
        $arTags['h1'] = array(
            'tag' => '/<h1[^>]+>([^<]+)<\/h1>/i',
        );
        $arTags['h2'] = array(
            'tag' => '/<h2[^>]+>([^<]+)<\/h2>/i',
        );
        $arTags['h3'] = array(
            'tag' => '/<h3[^>]+>([^<]+)<\/h3>/i',
        );
        $arTags['h4'] = array(
            'tag' => '/<h4[^>]+>([^<]+)<\/h4>/i',
        );
        $arTags['h5'] = array(
            'tag' => '/<h5[^>]+>([^<]+)<\/h5>/i',
        );
        $arTags['h6'] = array(
            'tag' => '/<h6[^>]+>([^<]+)<\/h6>/i',
        );
        $arTags['a'] = array(
            'tag' => '/<a[^>]+>([^<]+)<\/a>/i',
            'attr'=> '/(href|rel|title}target)=("[^"]*")/i'
        );
        $arTags['img'] = array(
            'tag' => '/<img[^>]+>/i',
            'attr'=> '/(alt|title|src|data-src|srcset|data-srcset)=("[^"]*")/i'
        );
        $result = array();
        if (isset($arTags[$tag])) {
            $arTag = $arTags[$tag];
            if (preg_match_all($arTag['tag'], $content, $matches)) {
                foreach ($matches[0] as $k=>$match) {
                    $res_tag = array();
                    if (isset($arTag['attr'])) {
                        preg_match_all($arTag['attr'], $match, $attr_matches);
                        foreach ($attr_matches[1] as $key=>$val) {
                            $res_tag[$val] = trim($attr_matches[2][$key], '"');
                        };
                    }
                    if (isset($matches[1][$k])) {
                        $res_tag['text'] = $matches[1][$k]; 
                    };
                    $result[] = $res_tag;
                };
            };  
        }
        return $result;
    }
}