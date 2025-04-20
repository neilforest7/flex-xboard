<?php

namespace Tests\Feature\Admin;

use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerBatchTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $serverGroup;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建管理员用户
        $this->admin = User::factory()->create([
            'is_admin' => 1
        ]);
        
        // 创建服务器分组
        $this->serverGroup = ServerGroup::create([
            'name' => '测试分组',
            'created_at' => time(),
            'updated_at' => time()
        ]);
    }

    public function testBatchCreateServers()
    {
        $nodes = [
            [
                'name' => '测试节点1',
                'type' => 'vmess',
                'group_ids' => [$this->serverGroup->id],
                'host' => 'test1.example.com',
                'server_port' => 10086,
                'protocol_settings' => [
                    'network' => 'tcp'
                ]
            ],
            [
                'name' => '测试节点2',
                'type' => 'trojan',
                'group_ids' => [$this->serverGroup->id],
                'host' => 'test2.example.com',
                'server_port' => 443,
                'protocol_settings' => [
                    'network' => 'tcp',
                    'server_name' => 'test2.example.com'
                ]
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/'.admin_setting('secure_path', 'admin').'/server/batch/create', [
                'nodes' => $nodes
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'success',
                    'failed'
                ]
            ]);
        
        $this->assertDatabaseHas('servers', [
            'name' => '测试节点1',
            'type' => 'vmess'
        ]);
        
        $this->assertDatabaseHas('servers', [
            'name' => '测试节点2',
            'type' => 'trojan'
        ]);
    }

    public function testBatchUpdateServers()
    {
        // 创建用于测试的节点
        $server1 = Server::create([
            'name' => '更新测试节点1',
            'type' => 'vmess',
            'group_ids' => json_encode([$this->serverGroup->id]),
            'host' => 'update1.example.com',
            'server_port' => 10010,
            'protocol_settings' => json_encode(['network' => 'tcp']),
            'created_at' => time(),
            'updated_at' => time()
        ]);
        
        $server2 = Server::create([
            'name' => '更新测试节点2',
            'type' => 'trojan',
            'group_ids' => json_encode([$this->serverGroup->id]),
            'host' => 'update2.example.com',
            'server_port' => 443,
            'protocol_settings' => json_encode(['network' => 'tcp', 'server_name' => 'update2.example.com']),
            'created_at' => time(),
            'updated_at' => time()
        ]);

        $updatedNodes = [
            [
                'id' => $server1->id,
                'name' => '更新测试节点1-已更新',
                'host' => 'updated1.example.com'
            ],
            [
                'id' => $server2->id,
                'name' => '更新测试节点2-已更新',
                'host' => 'updated2.example.com'
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/'.admin_setting('secure_path', 'admin').'/server/batch/update', [
                'nodes' => $updatedNodes
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'success',
                    'failed'
                ]
            ]);
        
        $this->assertDatabaseHas('servers', [
            'id' => $server1->id,
            'name' => '更新测试节点1-已更新',
            'host' => 'updated1.example.com'
        ]);
        
        $this->assertDatabaseHas('servers', [
            'id' => $server2->id,
            'name' => '更新测试节点2-已更新',
            'host' => 'updated2.example.com'
        ]);
    }

    public function testBatchDeleteServers()
    {
        // 创建用于测试的节点
        $server1 = Server::create([
            'name' => '删除测试节点1',
            'type' => 'vmess',
            'group_ids' => json_encode([$this->serverGroup->id]),
            'host' => 'delete1.example.com',
            'server_port' => 10086,
            'protocol_settings' => json_encode(['network' => 'tcp']),
            'created_at' => time(),
            'updated_at' => time()
        ]);
        
        $server2 = Server::create([
            'name' => '删除测试节点2',
            'type' => 'trojan',
            'group_ids' => json_encode([$this->serverGroup->id]),
            'host' => 'delete2.example.com',
            'server_port' => 443,
            'protocol_settings' => json_encode(['network' => 'tcp', 'server_name' => 'delete2.example.com']),
            'created_at' => time(),
            'updated_at' => time()
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson('/'.admin_setting('secure_path', 'admin').'/server/batch/delete', [
                'node_ids' => [$server1->id, $server2->id]
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'success',
                    'failed'
                ]
            ]);
        
        $this->assertDatabaseMissing('servers', [
            'id' => $server1->id
        ]);
        
        $this->assertDatabaseMissing('servers', [
            'id' => $server2->id
        ]);
    }

    public function testGetServerTemplates()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/'.admin_setting('secure_path', 'admin').'/server/batch/templates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'name',
                        'type',
                        'protocol_settings'
                    ]
                ]
            ]);
    }
} 