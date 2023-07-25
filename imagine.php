<?php

include 'vendor/autoload.php';

use YcOpen\Midjourney\Service;


function http_request($url, $data, $method = "POST", $header = [])
{
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => $method == "POST" ? true : false,
        CURLOPT_HTTPHEADER => $header,
    ];
    $data && $options[CURLOPT_POSTFIELDS] = $data;
    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function generateIDNumber($length = 16)
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $IDNumber   = '';
    for ($i = 0; $i < $length; $i++) {
        $IDNumber .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $IDNumber;
}

function imagine($prompt = "")
{
    $fileUrl = 'https://midjourney-1251511393.cos.ap-shanghai.myqcloud.com/json/imagine.json';
    $param   = file_get_contents($fileUrl);
    ## 替换参数
    $param       = str_replace("\$guild_id", '1108410963478196294', $param);
    $param       = str_replace("\$channel_id", '1108410963478196297', $param);
    $task_id     = generateIDNumber();
    $finalPrompt = "[" . $task_id . "] " . $prompt;
    $param       = str_replace("\$prompt", $finalPrompt, $param);
    $userToken   = 'ODQ2NDU5NjU4Mjg5NTQ1MjE3.G1PEKS.hbY-0q-y2u7MEHzHHFWQG4iR4Ju6qyxM_MxWsQ';
    $url         = "https://discord.com/api/v9/interactions";
    $response    = http_request($url, $param, "POST", [
        'Content-Type: application/json', 'Authorization: ' . $userToken
    ]);
    if (!$response) {
        ## 提交成功
        return json_encode(['code' => 200, 'msg=>' => "Prompt已提交", 'data' => ['prompt_id' => $task_id]]);
    }
    ## 提交失败
    return json_encode(['code' => 400, 'msg=>' => "Prompt提交失败"]);
}

try {
    $discord_channel_id = '1108410963478196297';
    $discord_user_token = 'ODQ2NDU5NjU4Mjg5NTQ1MjE3.G1PEKS.hbY-0q-y2u7MEHzHHFWQG4iR4Ju6qyxM_MxWsQ';
    $config             = [
        'channel_id' => $discord_channel_id,
        'oauth_token' => $discord_user_token,
        'timeout' => 30,
    ];

    $response = imagine('');
    echo '结果集：';
    print_r($response);
} catch (\Throwable $e) {
    print_r("错误：" . $e->getMessage());
}