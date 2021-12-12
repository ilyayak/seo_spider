<?include("print_r_tree.php");
include("../class/sitemap.parser.class.php");
if ($_GET['site'] != '') {
    $test_html = file_get_contents($_GET['site'].'/sitemap.xml');
} else {
    $test_html = file_get_contents('sitemap.xml');
};
$parser = new CSitemapParser();
$result = $parser->parse($test_html);
print_r_tree($result);