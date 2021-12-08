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

        /* images */
        $result['pictures'] = $this->get_tags('picture', $content, false);

        /* forms */
        $result['forms'] = $this->get_tags('form', $content, false);

        return $result;
    }


    function get_tags($tag, $content, $haveclosedtag = true) {
        if ($haveclosedtag) {
            $arTag['tag'] = '/(<'.$tag.'[^>]*>)(.*)<\/'.$tag.'>/i';;
        } else {
            $arTag['tag'] = '/(<'.$tag.'[^>]*>)/i';
        }
        $arTag['attr'] = '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i';
        /*
        $arTags['meta'] = array(
            'tag' => '/(<meta[^>]*>)/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['link'] = array(
            'tag' => '/(<link[^>]*>)/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['title'] = array(
            'tag' => '/(<title[^>]*>)([^<]+)<\/title>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['h1'] = array(
            'tag' => '/(<h1[^>]*>)([^<]+)<\/h1>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['h2'] = array(
            'tag' => '/(<h2[^>]*>)([^<]+)<\/h2>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['h3'] = array(
            'tag' => '/(<h3[^>]*>)([^<]+)<\/h3>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['h4'] = array(
            'tag' => '/(<h4[^>]*>)([^<]+)<\/h4>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['h5'] = array(
            'tag' => '/(<h5[^>]*>)([^<]+)<\/h5>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['h6'] = array(
            'tag' => '/(<h6[^>]*>)([^<]+)<\/h6>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['a'] = array(
            'tag' => '/(<a[^>]*>)([^<]+)<\/a>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );

        $arTags['img'] = array(
            'tag' => '/(<img[^>]*>)/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['form'] = array(
            'tag' => '/<form[^>]*>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        $arTags['script'] = array(
            'tag' => '/<script[^>]*>/i',
            'attr'=> '/\s+([a-zA-Z-]+)\s*=\s*("[^"]*")/i'
        );
        */
        $result = array();
            if (preg_match_all($arTag['tag'], $content, $matches)) {
                foreach ($matches[0] as $k=>$match) {
                    $res_tag = array();
                    $res_tag['tag'] = $match;
                    if (isset($matches[1][$k]))  {
                        preg_match_all($arTag['attr'], $matches[1][$k], $attr_matches);
                        if (is_array($attr_matches[1])) {
                            foreach ($attr_matches[1] as $key=>$val) {
                                $res_tag[$val] = trim($attr_matches[2][$key], '"');
                            };
                        }
                        $arTag['attr2'] = str_replace('"', "'", $arTag['attr']);
                        preg_match_all($arTag['attr2'], $matches[1][$k], $attr_matches2);
                        if (is_array($attr_matches2[1])) {
                            foreach ($attr_matches2[1] as $key=>$val) {
                                $res_tag[$val] = trim($attr_matches[2][$key], '"');
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