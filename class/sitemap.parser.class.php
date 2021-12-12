<?
class CSitemapParser {
    var $oXml;
    function parse($content) {
        $result = array();
        $this->oXml = simplexml_load_string($content);
        if ($this->oXml) {
            if (isset($this->oXml->sitemap)) {
                $result['sitemap'] = array();
                foreach ($this->oXml->sitemap as $sitemap) {
                    if (isset($sitemap->loc)) {
                        $result['sitemap'][] = $sitemap->loc;
                    }
                }
            }
            if (isset($this->oXml->url)) {
                $result['url'] = array();
                foreach ($this->oXml->url as $url) {
                    if (isset($url->loc)) {
                        $result['url'][] = $url->loc;
                    }
                }
            }
        } else {
            $result['ERROR'][] = 'SITEMAP WRONG STRUCTURE XML';
        }
        return $result;
    }

}