<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServerBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $method = $this->method();
        $rules = [];

        if ($method === 'POST') {
            // 批量创建验证规则
            $rules = [
                'nodes' => 'required|array|min:1|max:50',
                'nodes.*.name' => 'required|string|max:255',
                'nodes.*.type' => [
                    'required',
                    Rule::in(['shadowsocks', 'vmess', 'trojan', 'hysteria', 'vless', 'tuic', 'socks', 'http', 'naive', 'mieru'])
                ],
                'nodes.*.group_ids' => 'required|array',
                'nodes.*.group_ids.*' => 'integer|exists:v2_server_group,id',
                'nodes.*.host' => 'required|string|max:255',
                'nodes.*.port' => 'nullable|string|max:11',
                'nodes.*.server_port' => 'required|integer|min:1|max:65535',
                'nodes.*.protocol_settings' => 'required|array',
                'nodes.*.show' => 'nullable|boolean',
                'nodes.*.sort' => 'nullable|integer|min:0',
                'nodes.*.tags' => 'nullable|array',
                'nodes.*.parent_id' => 'nullable|integer',
                'nodes.*.rate' => 'nullable|numeric',
                'nodes.*.route_ids' => 'nullable|array'
            ];
        } else if ($method === 'PUT') {
            // 批量更新验证规则
            $rules = [
                'nodes' => 'required|array|min:1|max:50',
                'nodes.*.id' => 'required|integer|exists:servers,id',
                'nodes.*.name' => 'nullable|string|max:255',
                'nodes.*.type' => [
                    'nullable',
                    Rule::in(['shadowsocks', 'vmess', 'trojan', 'hysteria', 'vless', 'tuic', 'socks', 'http', 'naive', 'mieru'])
                ],
                'nodes.*.group_ids' => 'nullable|array',
                'nodes.*.group_ids.*' => 'integer|exists:v2_server_group,id',
                'nodes.*.host' => 'nullable|string|max:255',
                'nodes.*.port' => 'nullable|string|max:11',
                'nodes.*.server_port' => 'nullable|integer|min:1|max:65535',
                'nodes.*.protocol_settings' => 'nullable|array',
                'nodes.*.show' => 'nullable|boolean',
                'nodes.*.sort' => 'nullable|integer|min:0',
                'nodes.*.tags' => 'nullable|array',
                'nodes.*.parent_id' => 'nullable|integer',
                'nodes.*.rate' => 'nullable|numeric',
                'nodes.*.route_ids' => 'nullable|array'
            ];
        }

        return $rules;
    }

    /**
     * 获取验证错误的自定义消息
     *
     * @return array
     */
    public function messages()
    {
        return [
            'nodes.required' => '节点数据不能为空',
            'nodes.array' => '节点数据必须是数组格式',
            'nodes.min' => '至少需要提供一个节点',
            'nodes.max' => '一次最多处理50个节点',
            'nodes.*.id.required' => '节点ID不能为空',
            'nodes.*.id.exists' => '指定的节点不存在',
            'nodes.*.name.required' => '节点名称不能为空',
            'nodes.*.type.required' => '节点类型不能为空',
            'nodes.*.type.in' => '无效的节点类型',
            'nodes.*.group_ids.required' => '节点分组不能为空',
            'nodes.*.group_ids.array' => '节点分组必须是数组格式',
            'nodes.*.host.required' => '节点域名或IP不能为空',
            'nodes.*.server_port.required' => '节点端口不能为空',
            'nodes.*.server_port.integer' => '节点端口必须是整数',
            'nodes.*.server_port.min' => '节点端口最小值为1',
            'nodes.*.server_port.max' => '节点端口最大值为65535',
            'nodes.*.protocol_settings.required' => '协议配置不能为空',
            'nodes.*.protocol_settings.array' => '协议配置必须是数组格式'
        ];
    }
} 