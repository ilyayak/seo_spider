<?
class CHtmlParser {
    function parse($content) {

        /* title */
        $result['title'] = $this->get_tags('title', $content);

        /* meta tags */
        $result['meta']  = $this->get_tags('meta', $content);

        /* link в head */
        $result['link']  = $this->get_tags('link', $content);

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
        $result['images'] = $this->get_tags('img', $content);

        /* forms */
        $result['forms'] = $this->get_tags('form', $content);

        return $result;
    }

    function get_tags($tag, $content) {
        $arTags['meta'] = array(
            'tag' => '/<meta[^>]*>/i',
            'attr'=> '/(name|property|http-equiv|charset|content)\s*=\s*("[^"]*")/i'
        );
        $arTags['link'] = array(
            'tag' => '/<link[^>]*>/i',
            'attr'=> '/(rel|href|type|media|sizes)\s*=\s*("[^"]*")/i'
        );
        $arTags['title'] = array(
            'tag' => '/<title[^>]*>([^<]+)<\/title>/i',
        );
        $arTags['h1'] = array(
            'tag' => '/<h1[^>]*>([^<]+)<\/h1>/i',
        );
        $arTags['h2'] = array(
            'tag' => '/<h2[^>]*>([^<]+)<\/h2>/i',
        );
        $arTags['h3'] = array(
            'tag' => '/<h3[^>]*>([^<]+)<\/h3>/i',
        );
        $arTags['h4'] = array(
            'tag' => '/<h4[^>]*>([^<]+)<\/h4>/i',
        );
        $arTags['h5'] = array(
            'tag' => '/<h5[^>]*>([^<]+)<\/h5>/i',
        );
        $arTags['h6'] = array(
            'tag' => '/<h6[^>]*>([^<]+)<\/h6>/i',
        );
        $arTags['a'] = array(
            'tag' => '/<a[^>]*>([^<]+)<\/a>/i',
            'attr'=> '/(href|rel|title|target)\s*=\s*("[^"]*")/i'
        );
        $arTags['img'] = array(
            'tag' => '/<img[^>]*>/i',
            'attr'=> '/(alt|title|src|data-src|srcset|data-srcset)\s*=\s*("[^"]*")/i'
        );
        $arTags['form'] = array(
            'tag' => '/<form[^>]*>/i',
            'attr'=> '/(action|method)\s*=\s*("[^"]*")/i'
        );
        $arTags['script'] = array(
            'tag' => '/<script[^>]*>/i',
            'attr'=> '/(src|type|async|defer|language)\s*=\s*("[^"]*")*/i'
        );
        $result = array();
        if (isset($arTags[$tag])) {
            $arTag = $arTags[$tag];
            if (preg_match_all($arTag['tag'], $content, $matches)) {
                foreach ($matches[0] as $k=>$match) {
                    $res_tag = array();
                    $res_tag['tag'] = $match;
                    if (isset($arTag['attr'])) {
                        preg_match_all($arTag['attr'], $match, $attr_matches);
                        if (is_array($attr_matches[1])) {
                            foreach ($attr_matches[1] as $key=>$val) {
                                $res_tag[$val] = trim($attr_matches[2][$key], '"');
                            };
                        }
                        $arTag['attr2'] = str_replace('"', "'", $arTag['attr']);
                        preg_match_all($arTag['attr2'], $match, $attr_matches2);
                        if (is_array($attr_matches2[1])) {
                            foreach ($attr_matches2[1] as $key=>$val) {
                                $res_tag[$val] = trim($attr_matches[2][$key], '"');
                            };
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