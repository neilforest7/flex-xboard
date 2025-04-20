<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\ServerRoute;
use Illuminate\Support\Facades\Log;

class ServerBatchService
{
    /**
     * 批量创建服务器节点
     *
     * @param array $nodes 节点数据数组
     * @return array 创建结果
     */
    public function batchCreateServers(array $nodes): array
    {
        $result = [
            'success' => [],
            'failed' => []
        ];

        foreach ($nodes as $index => $nodeData) {
            try {
                // 确保protocol_settings是JSON格式，如果已经是数组则转为JSON
                if (isset($nodeData['protocol_settings']) && is_array($nodeData['protocol_settings'])) {
                    $nodeData['protocol_settings'] = json_encode($nodeData['protocol_settings']);
                }

                // 设置默认值
                $nodeData['show'] = $nodeData['show'] ?? 1;
                $nodeData['sort'] = $nodeData['sort'] ?? 0;
                $nodeData['rate'] = $nodeData['rate'] ?? 1.0;
                $nodeData['port'] = $nodeData['port'] ?? $nodeData['server_port'] ?? '';
                $nodeData['parent_id'] = $nodeData['parent_id'] ?? 0;
                $nodeData['code'] = $nodeData['code'] ?? mt_rand(100000, 999999);
                $nodeData['created_at'] = time();
                $nodeData['updated_at'] = time();
                
                // 处理group_ids为JSON格式
                if (isset($nodeData['group_ids']) && is_array($nodeData['group_ids'])) {
                    // 确保每个 ID 都是字符串类型
                    $nodeData['group_ids'] = array_map('strval', $nodeData['group_ids']);
                }
                
                // 处理tags为JSON格式
                if (isset($nodeData['tags']) && is_array($nodeData['tags'])) {
                    // 直接使用数组，与 group_ids 处理方式一致
                    $nodeData['tags'] = $nodeData['tags'];
                } else {
                    $nodeData['tags'] = [];
                }
                
                // 处理route_ids为JSON格式
                if (isset($nodeData['route_ids']) && is_array($nodeData['route_ids'])) {
                    // 确保每个 ID 都是字符串类型
                    $nodeData['route_ids'] = array_map('strval', $nodeData['route_ids']);
                } else {
                    $nodeData['route_ids'] = [];
                }

                // 创建节点
                $server = new Server();
                $server->fill($nodeData);
                $server->save();

                $result['success'][] = [
                    'id' => $server->id,
                    'name' => $server->name
                ];
            } catch (\Exception $e) {
                Log::error('批量创建节点失败', [
                    'node' => $nodeData,
                    'error' => $e->getMessage()
                ]);
                
                $result['failed'][$index] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * 批量更新服务器节点
     *
     * @param array $nodes 节点数据数组
     * @return array 更新结果
     */
    public function batchUpdateServers(array $nodes): array
    {
        $result = [
            'success' => [],
            'failed' => []
        ];

        foreach ($nodes as $index => $nodeData) {
            try {
                $nodeId = $nodeData['id'];
                $server = Server::find($nodeId);
                
                if (!$server) {
                    throw new \Exception("节点 ID: {$nodeId} 不存在");
                }

                // 移除不需要的字段
                unset($nodeData['id']);
                
                // 处理JSON字段
                if (isset($nodeData['protocol_settings']) && is_array($nodeData['protocol_settings'])) {
                    $nodeData['protocol_settings'] = json_encode($nodeData['protocol_settings']);
                }
                
                if (isset($nodeData['group_ids']) && is_array($nodeData['group_ids'])) {
                    // 确保每个 ID 都是字符串类型
                    $nodeData['group_ids'] = array_map('strval', $nodeData['group_ids']);
                }
                
                if (isset($nodeData['tags']) && is_array($nodeData['tags'])) {
                    // 直接使用数组
                    $nodeData['tags'] = $nodeData['tags'];
                } else {
                    $nodeData['tags'] = [];
                }
                
                if (isset($nodeData['route_ids']) && is_array($nodeData['route_ids'])) {
                    // 确保每个 ID 都是字符串类型
                    $nodeData['route_ids'] = array_map('strval', $nodeData['route_ids']);
                }

                $nodeData['updated_at'] = time();
                
                // 更新节点
                $server->fill($nodeData);
                $server->save();

                $result['success'][] = [
                    'id' => $server->id,
                    'name' => $server->name
                ];
            } catch (\Exception $e) {
                Log::error('批量更新节点失败', [
                    'node' => $nodeData,
                    'error' => $e->getMessage()
                ]);
                
                $result['failed'][$index] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * 批量删除服务器节点
     *
     * @param array $nodeIds 节点ID或Code数组
     * @return array 删除结果
     */
    public function batchDeleteServers(array $nodeIds): array
    {
        $result = [
            'success' => [],
            'failed' => []
        ];

        foreach ($nodeIds as $index => $nodeId) {
            try {
                // 尝试通过ID查找节点
                $server = Server::find($nodeId);
                
                // 如果未找到，尝试通过code查找
                if (!$server) {
                    $server = Server::where('code', $nodeId)->first();
                }
                
                if (!$server) {
                    throw new \Exception("节点 ID/Code: {$nodeId} 不存在");
                }

                // 删除节点
                $server->delete();

                $result['success'][] = $nodeId;
            } catch (\Exception $e) {
                Log::error('批量删除节点失败', [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage()
                ]);
                
                $result['failed'][$nodeId] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * 获取节点模板
     *
     * @return array 预设节点模板
     */
    public function getServerTemplates(): array
    {
        // 预设多种协议的节点模板
        return [
            [
                'name' => 'Shadowsocks TCP模板',
                'type' => 'shadowsocks',
                'protocol_settings' => [
                    'cipher' => 'aes-256-gcm',
                    'obfs' => 'plain'
                ]
            ],
            [
                'name' => 'VMess WebSocket模板',
                'type' => 'vmess',
                'protocol_settings' => [
                    'network' => 'ws',
                    'tls' => 1,
                    'network_settings' => [
                        'path' => '/ws',
                        'headers' => [
                            'Host' => ''
                        ]
                    ],
                    'tls_settings' => [
                        'server_name' => '',
                        'allow_insecure' => 0
                    ]
                ]
            ],
            [
                'name' => 'Trojan模板',
                'type' => 'trojan',
                'protocol_settings' => [
                    'server_name' => '',
                    'network' => 'tcp'
                ]
            ],
            [
                'name' => 'VLESS Reality模板',
                'type' => 'vless',
                'protocol_settings' => [
                    'network' => 'tcp',
                    'tls' => 2,
                    'flow' => 'xtls-rprx-vision',
                    'reality_settings' => [
                        'public_key' => '',
                        'short_id' => '',
                        'server_name' => 'www.microsoft.com'
                    ]
                ]
            ],
            [
                'name' => 'Hysteria模板',
                'type' => 'hysteria',
                'protocol_settings' => [
                    'version' => 2,
                    'network' => 'udp',
                    'bandwidth' => [
                        'up' => 100,
                        'down' => 100
                    ],
                    'obfs' => [
                        'open' => false,
                        'type' => '',
                        'password' => ''
                    ],
                    'tls' => [
                        'server_name' => ''
                    ]
                ]
            ]
        ];
    }
} 