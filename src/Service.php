<?php

namespace YcOpen\Midjourney;

use Exception;
use GuzzleHttp\Client;

/**
 * 服务操作类
 * @author 贵州猿创科技有限公司
 * @copyright 贵州猿创科技有限公司
 * @email 416716328@qq.com
 * 后续增加
 * redis队列处理，异步任务，回调通知，日志系统
 */
class Service
{

    # 应用ID
    private $application_id = '936929561302675456';

    # 数据ID
    private $data_id = '938956540159881230';

    # 数据版本
    private $data_version = '1077969938624553050';

    # 用户会话ID
    private $session_id = '2fb980f65e5c9a77c96ca01f2c242cf6';

    # 请求地址
    private $api_url = 'https://discord.com/api/v9';

    /**
     * @var Client
     */
    protected $client;

    # 频道ID
    protected $channel_id;

    # 请求令牌
    protected $oauth_token;

    # 服务器ID
    protected $guild_id;

    # 用户ID
    protected $user_id;

    # 回调地址（后续使用）
    protected $notify = '';

    # 构造函数
    public function __construct(array $config)
    {
        if (!isset($config['channel_id']) && !$config['channel_id']) {
            throw new Exception('请提供频道ID');
        }
        if (!isset($config['oauth_token']) && !$config['oauth_token']) {
            throw new Exception('请提供用户授权token');
        }
        if (isset($config['api_url']) && $config['api_url']) {
            $this->api_url = $config['api_url'];
        }
        # 频道ID
        $this->channel_id = $config['channel_id'];
        # 授权TOKEN
        $this->oauth_token = $config['oauth_token'];

        # 实例请求类
        $this->client = new Client([
            'base_uri' => $this->api_url,
            'headers' => [
                'Authorization' => $this->oauth_token
            ]
        ]);

        # 获取服务器ID
        $request = $this->client->get("channels/{$this->channel_id}");
        $response = json_decode((string) $request->getBody(), true);
        $this->guild_id = $response['guild_id'];

        # 获取用户ID
        $request = $this->client->get('users/@me');
        $response = json_decode((string) $request->getBody(), true);
        $this->user_id = $response['id'];
    }

    /**
     * 投递关键词图片
     * @param mixed $prompt
     * @throws \Exception
     * @return string
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    public function imagine(string $prompt): string
    {
        $task_id = $this->generateIDNumber();
        # 准备参数
        $params = [
            'type' => 2,
            'application_id' => $this->application_id,
            'guild_id' => $this->guild_id,
            'channel_id' => $this->channel_id,
            'session_id' => $this->session_id,
            'data' => [
                'version' => $this->data_version,
                'id' => $this->data_id,
                'name' => 'imagine',
                'type' => 1,
                'options' => [
                    [
                        'type' => 3,
                        'name' => 'prompt',
                        'value' => "[{$task_id}] {$prompt}"
                    ]
                ],
                'application_command' => [
                    'id' => $this->data_id,
                    'application_id' => $this->application_id,
                    'version' => $this->data_version,
                    'default_permission' => true,
                    'default_member_permissions' => '',
                    'type' => 1,
                    'nsfw' => false,
                    'name' => 'imagine',
                    'description' => 'Create images with Midjourney',
                    'dm_permission' => true,
                    'options' => [
                        [
                            'type' => 3,
                            'name' => 'prompt',
                            'description' => 'The prompt to imagine',
                            'required' => true
                        ]
                    ]
                ],
                'attachments' => []
            ]
        ];
        $data = [
            'json' => $params
        ];
        $response = $this->client->post('interactions', $data);
        if ($response->getStatusCode() !== 204) {
            throw new Exception('投递图片失败');
        }
        return $task_id;
    }

    /**
     * 获取图片
     * @param mixed $task_id
     * @return array|null
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    public function getImagine(string $task_id)
    {
        $data = $this->getChannelsMsg();
        $raw_message = $this->firstWhere($data, function ($item) use ($task_id) {
            return (
                strpos($item['content'], $task_id)
                &&
                !preg_match("/(\d%)/", $item['content'])
                &&
                !str_contains($item['content'], '(Waiting to start)')
                &&
                !str_contains($item['content'], '(Pause)')
            );
        });
        if (!$raw_message)
            return null;

        return [
            'id' => $raw_message['id'],
            'task_id' => $task_id,
            'raw_message' => $raw_message
        ];
    }

    /**
     * 图片操作
     * @param mixed $message
     * @param mixed $index
     * @param mixed $action
     * @throws \Exception
     * @return mixed
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    public function imageAction(array $message, int $index, int $time, string $action = 'u')
    {
        # 验证函数
        if (!isset($message['raw_message'])) {
            throw new Exception('Upscale需要从imagine/getImagine方法获得一个消息数据');
        }
        if (!isset($message['task_id'])) {
            throw new Exception('任务ID错误');
        }
        if ($index < 0 or $index > 3) {
            throw new Exception('上限索引必须是0和4之间');
        }
        # 根据操作类型获取到自定义ID
        $actionArr = ['u' => 0, 'v' => 1];
        if (!isset($actionArr[$action])) {
            throw new Exception('操作类型错误');
        }
        $actionType = $actionArr[$action];
        $custom_id = null;
        $raw_message = $message['raw_message'];
        if (isset($raw_message['components'][$actionType]) && is_array($raw_message['components'][$actionType])) {
            $components = $raw_message['components'][$actionType]['components'];
            if (!isset($components[$index]['custom_id'])) {
                throw new Exception('无法找到操作类型自定义ID');
            }
            $custom_id = $components[$index]['custom_id'];
        }
        if (!isset($raw_message['id'])) {
            throw new Exception('官方消息ID错误');
        }

        # 组装参数
        $params = [
            'type' => 3,
            'guild_id' => $this->guild_id,
            'application_id' => $this->application_id,
            'channel_id' => $this->channel_id,
            'message_flags' => 0,
            'message_id' => $raw_message['id'],
            'session_id' => $this->session_id,
            'data' => [
                'component_type' => 2,
                'custom_id' => $custom_id
            ]
        ];
        # 投递操作给机器人
        $this->client->post('interactions', [
            'json' => $params
        ]);
        # 匹配新投递的
        $photoMessage = null;
        while (!$photoMessage) {
            if ($action === 'u') {
                # U操作
                $photoMessage = $this->getUpscale($message, $index);
            } else {
                # V操作
                $photoMessage = $this->getGenerate($message, $index);
            }
            #判断超时 如果超时退出循环
            if (time() >= $time) {
                break;
            }
            sleep(1);
        }

        if (time() >= $time) {
            throw new Exception('请求超时');
        }

        $rawMessage = [
            'id' => $photoMessage['id'],
            'task_id' => $message['task_id'],
            'url' => '',
            'raw_message' => $photoMessage,
        ];
        if (isset($photoMessage['attachments']) && is_array($photoMessage['attachments'])) {
            $attachment = $photoMessage['attachments'][0];
            $rawMessage['url'] = $attachment['url'];
        }
        return $rawMessage;
    }
    /**
     * 匹配V操作
     * @param mixed $message
     * @param mixed $index
     * @return mixed
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    private function getGenerate(array $message, int $index)
    {
        $data = $this->getChannelsMsg();
        $task_id = $message['task_id'];
        $response = $this->firstWhere($data, function ($item) use ($task_id) {
            return (
                strpos($item['content'], $task_id)
                &&
                preg_match("/\[{$task_id}\].+Variations by/", $item['content'])
                &&
                !str_contains($item['content'], '(Waiting to start)')
                &&
                !str_contains($item['content'], '(Pause)')
            );
        });
        if (!$response) {
            return null;
        }
        return $response;
    }

    /**
     * 匹配U操作
     * @param mixed $message
     * @param mixed $index
     * @return mixed
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    private function getUpscale(array $message, int $index)
    {
        $data = $this->getChannelsMsg();
        $task_id = $message['task_id'];
        $message_index = $index + 1;
        $response = $this->firstWhere($data, function ($item) use ($task_id, $message_index) {
            return (
                strpos($item['content'], $task_id)
                &&
                preg_match("/\[{$task_id}\].+ - Image #{$message_index}/", $item['content'])
                &&
                !str_contains($item['content'], '(Waiting to start)')
                &&
                !str_contains($item['content'], '(Pause)')
            );
        });
        if (!$response) {
            return null;
        }
        return $response;
    }

    /**
     * 获取频道消息列表
     * @return mixed
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    private function getChannelsMsg(): array
    {
        $response = $this->client->get('channels/' . $this->channel_id . '/messages');
        $data = json_decode((string) $response->getBody(), true);
        return $data;
    }

    /**
     * 获取查询
     * @param mixed $array
     * @param mixed $key
     * @param mixed $value
     * @return mixed
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    protected function firstWhere(array $array, mixed $key, $value = null)
    {
        foreach ($array as $item) {
            if (
                (is_callable($key) and $key($item)) or
                (is_string($key) and str_starts_with($item[$key], $value))
            ) {
                return $item;
            }
        }
        return null;
    }

    /**
     * 生成指定长度的数字
     * @param $length
     * @return string
     */
    private function generateIDNumber($length = 16): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $IDNumber = '';
        for ($i = 0; $i < $length; $i++) {
            $IDNumber .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $IDNumber;
    }
}