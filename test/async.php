<?php
$link1 = mysqli_connect('localhost', 'root', 'cookPan001', 'test', 6379);
$link1->query("SELECT * from user;select * from goods_00", MYSQLI_ASYNC);
$all_links = array($link1);
$processed = 0;
do {
    $links = $errors = $reject = array();
    foreach ($all_links as $link) {
        $links[] = $errors[] = $reject[] = $link;
    }
    if (!mysqli_poll($links, $errors, $reject, 1)) {
        continue;
    }
    foreach ($links as $link) {
        $result = $link->reap_async_query();
        if ($result) {
            print_r($result->fetch_assoc());
            if (is_object($result)){
                mysqli_free_result($result);
            }
        } else {
            die(sprintf("MySQLi Error: %s", mysqli_error($link)));
        }
        $processed++;
    }
} while ($processed < count($all_links));
