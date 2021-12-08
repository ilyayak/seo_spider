<?include("print_r_tree.php");
include("../class/robots.parser.class.php");
if ($_GET['site'] != '') {
    $test_html = file_get_contents($_GET['site'].'/robots.txt');
} else {
    $test_html = file_get_contents('robots.txt');
};
$htmlParser = new CRobotsParser();
$result = $htmlParser->parse($test_html);
print_r_tree($result);
if ($_GET['url'] != '') {
    $checkurl = $htmlParser->urlIsAllow($_GET['url'], $result);
    echo '<h3>Результат url '.$_GET['url'].'</h3>';
    var_dump($checkurl);
}