<?
include("print_r_tree.php");
include("../class/html.parser.class.php");
if ($_GET['site'] != '') {
    $test_html = file_get_contents($_GET['site']);
} else {
    $test_html = file_get_contents('test.html');
}
$htmlParser = new CHtmlParser();
$result = $htmlParser->parse($test_html);
print_r_tree($result);
