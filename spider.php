<?
include('siteparser.class.php');

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Spider</title>
</head>

<body>
    <h1>SEO Сканер</h1>
    <?
    $SITE = '';
    if ($_REQUEST['site'] != '') {
        if (filter_var($_REQUEST['site'], FILTER_VALIDATE_URL) !== false) {
            $SITE = $_REQUEST['site'];
        };
    };
    if ($SITE != '') {
        $spider = new siteparser($SITE);
    };
    if (($SITE != '') && ($_REQUEST['a'] != '')) {
        $date = $_REQUEST['date'];
        /******************** */
        /* Сканирование сайта */
        if ($_REQUEST['a'] == 'scan') {

            $start = 0 + $_REQUEST['start'];
            $limit = max(1, $_REQUEST['limit']);
            $finish = $spider->scan($start, $limit, $date);
            echo '<p>Проанализировано: ' . $finish . ' / ' . $spider->count . '</p>';
            if ($finish < $spider->count) {
                $link = '?a=scan&site=' . $SITE . '&date=' . $date . '&start=' . $finish . '&limit=' . $limit;
                echo '<p><a href="' . $link . '">Далее</a></p>';
                echo '
                <script>
                    setTimeout(function() {
                        location = "'.$link.'";
                    }, 2000);
                </script>
                ';
            } else {
                $link = '?a=report&site=' . $SITE . '';
                echo '<p><a href="' . $link . '">Отчеты</a></p>';
            };
        };
        /******************** */
        /* Сканирование сайта */
        if ($_REQUEST['a'] == 'report') {
            ?>

            <h3>Отчеты</h3>

            <form action="" target="_blank">
                <input type="hidden" name="a" value="report">
                <input type="hidden" name="site" value="<?= $_REQUEST['site'] ?>">
                <select name="type">
                <option value="main">Основной</option>
                </select>
                <input type="submit" name="submit" value="Отчет">
            </form>
            <?

            echo $spider->report($_REQUEST['type']);
        };
    } else  if ($SITE != '') {
        echo '<h2>' . $SITE . '</h2>';
        echo '<p>Известно страниц: ' . $spider->count . '</p>';

        ?>
        <h3>Cканировать</h3>
        <form action="">
            <input type="hidden" name="a" value="scan">
            <input type="hidden" name="site" value="<?= $_REQUEST['site'] ?>">
            <p>
                <label title="Сколько страниц сканировать за один шаг">Шаг сканирования (страниц)</label>
                <input type="number" name="limit" value="3">
            </p>
            <p>
                <input type="submit" name="submit" value="Сканировать">
            </P>
        </form>

        <?
        $link = '?a=report&site=' . $SITE . '';
        echo '<p><a href="' . $link . '">Отчеты</a></p>';
        ?>

    <? } else { ?>
        <h3>Начало</h3>
        <form action="">
            <input type="text" name="site" placeholder="https://site.ru">
            <input type="submit" name="submit" value="Начать">
        </form>


    <? } ?>
</body>

</html>