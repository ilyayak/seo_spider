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
    $menu['?a=start'] = 'Начало';
    if ($SITE != '') {
        $spider = new siteparser($SITE);
        $menu['?a=scan&site='.$SITE] = 'Сканирование';
        $menu['?a=test&site='.$SITE] = 'Тестирование';
        $menu['?a=report&site='.$SITE] = 'Отчеты';
    };?>
    <ul class="mainmenu">
        <?foreach($menu as $link=>$title) {
            echo
            '<li class="mainmenu--item__list">'.
            '<a class="mainmenu--item__link" href="'.$link.'">'.$title.'</a>'.
            '</li>';
        }?>
    </ul>
    <?
    $action = $_REQUEST['a'];
    if (!in_array($action, ['start', 'scan', 'test', 'report'])) {
        $action = 'start';
    };
    if ($SITE == '') {
        $action = 'start';
    };

    /********************************** */

    if ($SITE != '') {
        echo '<h2>'.$SITE .'</h2>';
    };

    if ($action == 'start') {
        echo  '
        <h3>Начало</h3>
        <form action="">
            <input type="text" name="site" placeholder="https://site.ru">
            <input type="submit" name="submit" value="Начать">
        </form>';
    };

    /********************************** */

    if ($action == 'scan') {
        echo  '<h3>Cканировать</h3>';
        if (isset($_REQUEST['start'])) {
            $start = 0 + $_REQUEST['start'];
            $limit = max(1, $_REQUEST['limit']);
            $finish = $spider->scan($start, $limit, $date);
            echo '<p>Просканировано: ' . $finish . ' / ' . $spider->count . '</p>';
            if ($finish < $spider->count) {
                $link = '?a=scan&site=' . $SITE . '&start=' . $finish . '&limit=' . $limit;
                echo '<p><a href="' . $link . '">Далее</a></p>';
                echo '
                <script>
                    setTimeout(function() {
                        location = "'.$link.'";
                    }, 2000);
                </script>
                ';
            } else {
                echo '<p>Готово</p>';
            };
        } else {

            echo '
            <form action="">
                <input type="hidden" name="a" value="scan">
                <input type="hidden" name="start" value="0">
                <input type="hidden" name="site" value="'. $SITE .'">
                <p>
                    <label title="Сколько страниц сканировать за один шаг">Шаг сканирования (страниц)</label>
                    <input type="number" name="limit" value="3">
                </p>
                <p>
                    <input type="submit" name="submit" value="Сканировать">
                </P>
            </form>
            ';

        }
    }

    /********************************** */

    if ($action == 'test') {

    }

    /********************************** */

    if ($action == 'report') {
        echo $spider->report('main');
    }
    ?>
</body>
</html>