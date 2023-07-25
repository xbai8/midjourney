<?php

include 'vendor/autoload.php';

use YcOpen\Midjourney\Service;

try {
    $discord_channel_id = '1108410963478196297';
    $discord_user_token = 'ODQ2NDU5NjU4Mjg5NTQ1MjE3.G1PEKS.hbY-0q-y2u7MEHzHHFWQG4iR4Ju6qyxM_MxWsQ';
    $config = [
        'channel_id' => $discord_channel_id,
        'oauth_token' => $discord_user_token,
    ];

    $midjourney = new Service($config);
    if(empty($_GET['task_id'])){
        $task_id = $midjourney->imagine("aircraft");
        echo $task_id;
        echo "<a href='/demo.php?task_id={$task_id}' target='_blank'>打开</a>";
        $response   = $midjourney->getImagine($task_id);
        echo '结果集：';
        print_r($response);
    } else {
        $task_id= $_GET['task_id'];
        echo 'Task：'.$task_id;
        $response   = $midjourney->getImagine($task_id);
        echo '结果集：';
        echo '<pre>';
        print_r($response);
        echo '</pre>';
    }
} catch (\Throwable $e) {
    print_r("错误：".$e->getMessage());
}
