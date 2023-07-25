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
    public function imagine(string $prompt):string
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
        $data        = $this->getChannelsMsg();
        var_dump($data);
        exit;
        $raw_message = $this->firstWhere($data, function ($item) use ($task_id) {
            return (
                strpos($item['content'],$task_id)
                &&
                !preg_match("/(\d%)/",$item['content'])
                &&
                !str_contains($item['content'], '(Waiting to start)')
                &&
                !str_contains($item['content'], '(Pause)')
            );
        });
        if (!$raw_message) return null;

        return [
            'id'            => $raw_message['id'],
            'task_id'       => $task_id,
            'raw_message'   => $raw_message
        ];
    }

    /**
     * 图片操作
     * @param mixed $message
     * @param mixed $index
     * @param mixed $action
     * @throws \Exception
     * @return void
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    public function getImageAction(array $message,int $index,string $action = 'upscale')
    {
        # 验证函数
        if (!isset($message['raw_message'])) {
            throw new Exception('Upscale需要从imagine/getImagine方法获得一个消息数据');
        }
        if ($index < 0 or $index > 3) {
            throw new Exception('上限索引必须是0和4之间');
        }
        # 根据操作类型获取到自定义ID
        $actionType = ['upscale','generate'];
        $custom_id = null;
        $raw_message = $message['raw_message'];
        if (isset($raw_message['components'][$actionType]) && is_array($raw_message['components'][$actionType])) {
            $generate = $raw_message['components'][1]['components'];
            if (!isset($upscales[$index]['custom_id'])) {
                throw new Exception('无法找到操作类型自定义ID');
            }
            $custom_id = $generate[$index]['custom_id'];
        }
        
        # 组装参数
        $params = [
            'type'           => 3,
            'guild_id'       => $this->guild_id,
            'application_id' => $this->application_id,
            'channel_id'     => $this->channel_id,
            'message_flags'  => 0,
            'message_id'     => $message['id'],
            'session_id'     => $this->session_id,
            'data'           => [
                'component_type' => 2,
                'custom_id'      => $custom_id
            ]
        ];
        # 投递操作给机器人
        $this->client->post('interactions', [
            'json' => $params
        ]);
        # 匹配新投递的
        $photo_url = null;
        while (is_null($generate_photo_url)) {
            if ($action === 'upscale') {
                $photo_url = $this->getUpscale($message, $index);
            } else {
                $photo_url = $this->getGenerate($message, $index);            
            }
            sleep(1);
        }
    }
    /**
     * 匹配V操作
     * @param mixed $message
     * @param mixed $index
     * @return void
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    private function getGenerate(array $message,int $index)
    {
        $data          = $this->getChannelsMsg();
        $task_id = $message['task_id'];
        $message_index = $index + 1;
        $message = $this->firstWhere($data, function ($item) use ($task_id) {
            return (
                strpos($item['content'],$task_id)
                &&
                preg_match("/\[{$task_id}\].+Variations by/", $item['content'])
                &&
                !str_contains($item['content'], '(Waiting to start)')
                &&
                !str_contains($item['content'], '(Pause)')
            );
        });
        if (!$message) return null;
    }
    private function getUpscale(array $message,int $index)
    {
        $data          = $this->getChannelsMsg();
        $task_id = $message['task_id'];
        $message_index = $index + 1;
        $message = $this->firstWhere($data, function ($item) use ($task_id,$message_index) {
            return (
                strpos($item['content'],$task_id)
                &&
                preg_match("/\[{$task_id}\].+ - Image #{$message_index}/", $item['content'])
                &&
                !str_contains($item['content'], '(Waiting to start)')
                &&
                !str_contains($item['content'], '(Pause)')
            );
        });
        if (!$message) return null;
    }

    /**
     * 获取频道消息列表
     * @return mixed
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    private function getChannelsMsg():array
    {
        $response = $this->client->get('channels/' . $this->channel_id . '/messages');
        $data = json_decode((string) $response->getBody(),true);
        return $data;
    }

    /**
     * 获取V操作
     * @param string $prompt
     * @param int $upscale_index
     * @return array
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    public function generate(array $message, int $upscale_index = 0)
    {
        if (!isset($message['raw_message'])) {
            throw new Exception('Upscale需要从imagine/getImagine方法获得一个消息数据');
        }
        if ($upscale_index < 0 or $upscale_index > 3) {
            throw new Exception('上限索引必须是0和4之间');
        }

        $upscale_hash = null;
        $raw_message = $message['raw_message'];
        if (isset($raw_message['components'][1]) && is_array($raw_message['components'][1])) {
            $generate = $raw_message['components'][1]['components'];
            if (!isset($upscales[$upscale_index]['custom_id'])) {
                throw new Exception('无法找到自定义ID');
            }
            $upscale_hash = $generate[$upscale_index]['custom_id'];
        }

        $params = [
            'type'           => 3,
            'guild_id'       => $this->guild_id,
            'application_id' => $this->application_id,
            'channel_id'     => $this->channel_id,
            'message_flags'  => 0,
            'message_id'     => $message['id'],
            'session_id'     => $this->session_id,
            'data'           => [
                'component_type' => 2,
                'custom_id'      => $upscale_hash
            ]
        ];

        # 投递U操作给机器人
        $this->client->post('interactions', [
            'json' => $params
        ]);
        # 匹配新投递的
        $generate_photo_url = null;
        while (is_null($generate_photo_url)) {
            $generate_photo_url = $this->getUpscale($message, $upscale_index);
            sleep(1);
        }
        print_r($generate_photo_url);
        exit;
        return [];
    }

    /**
     * U操作
     * @param array $message
     * @param int $upscale_index
     * @throws \Exception
     * @return mixed
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    public function upscale(array $message, int $upscale_index = 0)
    {
        if (!isset($message['raw_message'])) {
            throw new Exception('Upscale需要从imagine/getImagine方法获得一个消息数据');
        }
        if ($upscale_index < 0 or $upscale_index > 3) {
            throw new Exception('上限索引必须是0和4之间');
        }

        $upscale_hash = null;
        $raw_message = $message['raw_message'];
        if (isset($raw_message['components']) && is_array($raw_message['components'])) {
            $upscales = $raw_message['components'][0]['components'];
            if (!isset($upscales[$upscale_index]['custom_id'])) {
                throw new Exception('无法找到自定义ID');
            }
            $upscale_hash = $upscales[$upscale_index]['custom_id'];
        }

        $params = [
            'type'           => 3,
            'guild_id'       => $this->guild_id,
            'channel_id'     => $this->channel_id,
            'message_flags'  => 0,
            'message_id'     => $message['id'],
            'application_id' => $this->application_id,
            'session_id'     => $this->session_id,
            'data'           => [
                'component_type' => 2,
                'custom_id'      => $upscale_hash
            ]
        ];

        # 投递U操作给机器人
        $this->client->post('interactions', [
            'json' => $params
        ]);

        # 匹配新投递的
        $upscaled_photo_url = null;
        while (is_null($upscaled_photo_url)) {
            $upscaled_photo_url = $this->getUpscale($message, $upscale_index);
            sleep(1);
        }

        return $upscaled_photo_url;
    }

    /**
     * 获取U操作请求数据
     * @param mixed $message
     * @param mixed $upscale_index
     * @throws \Exception
     * @return mixed
     * @author 贵州猿创科技有限公司
     * @copyright 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    private function getUpscale1($message, $upscale_index = 0)
    {
        if (!isset($message['raw_message'])) {
            throw new Exception('Upscale需要从imagine/getImagine方法获得一个消息数据');
        }

        if ($upscale_index < 0 or $upscale_index > 3) {
            throw new Exception('上限索引必须是0和4之间');
        }

        $task_id = $message['task_id'];

        $response = $this->client->get('channels/' . $this->channel_id . '/messages');
        $response = json_decode((string) $response->getBody(),true);

        $message_index = $upscale_index + 1;
        $message = $this->firstWhere($response, function ($item) use ($task_id,$message_index) {
            return (
                strpos($item['content'],$task_id)
                &&
                preg_match("/\[{$task_id}\].+ - Image #{$message_index}/", $item['content'])
                &&
                !str_contains($item['content'], '(Waiting to start)')
                &&
                !str_contains($item['content'], '(Pause)')
            );
        });

        if (is_null($message)) {
            $message = $this->firstWhere($response, function ($item) use ($task_id) {
                return (
                    strpos($item['content'],$task_id)
                    &&
                    preg_match("/\[{$task_id}\].+Variations by/", $item['content'])
                    &&
                    !str_contains($item['content'], '(Waiting to start)')
                    &&
                    !str_contains($item['content'], '(Pause)')
                );
            });
        }

        if (is_null($message)) return null;

        if (isset($message['attachments']) && is_array($message['attachments'])) {
            $attachment = $message['attachments'][0];
            return $attachment['url'];
        }
        return null;
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
    private function generateIDNumber($length = 16):string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $IDNumber = '';
        for ($i = 0; $i < $length; $i++) {
            $IDNumber .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $IDNumber;
    }
}
