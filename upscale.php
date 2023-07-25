<?php

include 'vendor/autoload.php';

use YcOpen\Midjourney\Service;

try {
    $discord_channel_id = '1108382924216209581';
    $discord_user_token = 'MTA5OTIxODMzMDM0MDA0NDg1OQ.GCgZVF.SJgu9JIO2ijxZSGEoDCtiZGXvnBXpI-u12hegU';
    $config = [
        'channel_id' => $discord_channel_id,
        'oauth_token' => $discord_user_token,
    ];

    $midjourney = new Service($config);
    # 生成图片
    $task_id    = $midjourney->imagine('League of Legends Glory');
    $imagine    = null;
    while (is_null($imagine)) {
        # 获取图片
        $imagine = $midjourney->getImagine($task_id);
        sleep(1);
    }
    # 获取U操作
    $upscale = null;
    while ($upscale) {
        $upscale = $midjourney->upscale($imagine);
        sleep(1);
    }
    echo 'U操作：'.PHP_EOL;
    print_r($upscale);
} catch (\Throwable $e) {
    print_r("错误：".$e->getMessage());
}
