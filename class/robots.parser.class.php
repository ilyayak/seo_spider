<?
class CRobotsParser
{
    function parse($content)
    {

        $result = array();
        $curAgent = '*';
        $directives = [
            'user-agent',
            'disallow',
            'allow',
            'sitemap',
            'crawl-delay',
            'clean-param',
        ];
        $arLines = explode("\n", $content);
        if (is_array($arLines)) {
            foreach ($arLines as $line) {
                $line = trim($line);
                if (strpos($line, ':') !== false) {
                    $list = explode(':', $line);
                    $directive = array_shift($list);
                    $rule = implode(':', $list);
                    $directive = mb_strtolower(trim($directive));
                    $rule = trim($rule);
                    $rule_sort =  10 * mb_strlen($rule);
                    if ($rule_sort  > 0) {
                        if (in_array($directive, $directives)) {
                            if ($directive == 'user-agent') {
                                $curAgent = $rule;
                            } else if (in_array($directive, array('disallow', 'allow'))) {
                                while (isset($result[$curAgent][$directive][$rule_sort])) {
                                    $rule_sort++;
                                };
                                $result[$curAgent][$directive][$rule_sort] = $rule;
                                ksort($result[$curAgent][$directive]);

                            } else if (in_array($directive, array('sitemap'))) {
                                $result[$directive][] = $rule;
                            } else {
                                $result[$curAgent][$directive][] = $rule;
                            };
                        };
                    };
                };
            };
        };
        return $result;
    }


    function urlIsAllow($url, $parsedRules, $agent = '*')
    {
        $result = true;
        if (is_array($parsedRules[$agent])) {
            $rules = array();
            $maxrules = max(max(array_keys($parsedRules[$agent]['allow'])), max(array_keys($parsedRules[$agent]['disallow'])));;
            $i = 0;
            while ($i <= $maxrules) {
                if (isset($parsedRules[$agent]['disallow'][$i])) {
                    $rules[$parsedRules[$agent]['disallow'][$i]] = false;
                };
                if (isset($parsedRules[$agent]['allow'][$i])) {
                    $rules[$parsedRules[$agent]['allow'][$i]] = true;
                }
                $i++;
            };
            if (is_array($rules)) {
                foreach ($rules as $rule => $res) {
                    $reg = '|.*' . str_replace(array('*', '?'), array('.*', '\?'), $rule) . '|';
                    if (preg_match($reg, $url)) {
                        $result = $res;
                    }
                }
            }
        }
        return $result;
    }
}
