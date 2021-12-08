<?
class CHtmlParser {
    function parse($content) {

        /* title */
        $result['title'] = $this->get_tags('title', $content);

        /* meta tags */
        $result['meta']  = $this->get_tags('meta', $content, false);

        /* link в head */
        $result['link']  = $this->get_tags('link', $content, false);

        /* h1 - h6 */
        $result['h1'] = $this->get_tags('h1', $content);
        $result['h2'] = $this->get_tags('h2', $content);
        $result['h3'] = $this->get_tags('h3', $content);
        $result['h4'] = $this->get_tags('h4', $content);
        $result['h5'] = $this->get_tags('h5', $content);
        $result['h6'] = $this->get_tags('h6', $content);

        /* links tag a */
        $result['a'] = $this->get_tags('a', $content);

        /* images */
        $result['images'] = $this->get_tags('img', $content, false);

        /* pictures */
        $result['pictures'] = $this->get_tags('picture', $content);
        if (is_array($result['pictures'])) {
            foreach ($result['pictures'] as $k=>$pic) {
                $result['pictures'][$k]['source'] = $this->get_tags('source', $pic['text'], false);
                $result['pictures'][$k]['img'] = $this->get_tags('img', $pic['text'], false);
            }
        }

        /* video */
        $result['video'] = $this->get_tags('video', $content);
        if (is_array($result['video'])) {
            foreach ($result['video'] as $k=>$video) {
                $result['video'][$k]['source'] = $this->get_tags('source', $video['text'], false);
            }
        }

        /* scripts */
        $result['scripts'] = $this->get_tags('script', $content);

        /* forms */
        $result['forms'] = $this->get_tags('form', $content);

        return $result;
    }


    function get_tags($tag, $content, $haveClosedTag = true) {
        if ($haveClosedTag) {
            $arTag['tag'] = '/(<'.$tag.'[^>]*>)(.*)<\/'.$tag.'>/ismuU';;
        } else {
            $arTag['tag'] = '/(<'.$tag.'[^>]*>)/ismuU';
        };
        $arTag['attr'] = '/\s+([a-zA-Z-]+)\s*=\s*"([^"]*)"/ismuU';
        $arTag['attr2'] = str_replace('"', "'", $arTag['attr']);
        $result = array();
        if (preg_match_all($arTag['tag'], $content, $matches)) {
            foreach ($matches[0] as $k=>$match) {
                $res_tag = array();
                $res_tag['tag'] = $match;
                if (isset($matches[1][$k]))  {
                    preg_match_all($arTag['attr'], $matches[1][$k], $attr_matches);
                    if (is_array($attr_matches[1])) {
                        foreach ($attr_matches[1] as $key=>$val) {
                            $res_tag[$val] = $attr_matches[2][$key];
                        };
                    }
                    preg_match_all($arTag['attr2'], $matches[1][$k], $attr_matches2);
                    if (is_array($attr_matches2[1])) {
                        foreach ($attr_matches2[1] as $key=>$val) {
                            $res_tag[$val] = $attr_matches[2][$key];
                        };
                    };
                };
                if (isset($matches[2][$k])) {
                    $res_tag['text'] = $matches[2][$k];
                };
                $result[] = $res_tag;
            };
        };
        return $result;
    }
}