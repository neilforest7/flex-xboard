<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;

class WechatPay implements PaymentInterface
{
    protected $config;
    
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'appid' => [
                'label' => '公众号AppID',
                'description' => '微信公众号的AppID',
                'type' => 'input',
            ],
            'mch_id' => [
                'label' => '商户号',
                'description' => '微信支付商户号',
                'type' => 'input',
            ],
            'key' => [
                'label' => '商户密钥',
                'description' => '微信支付API密钥',
                'type' => 'input',
            ],
            'cert_path' => [
                'label' => '证书路径',
                'description' => '微信支付API证书路径（可选）',
                'type' => 'input',
            ],
            'key_path' => [
                'label' => '证书密钥路径',
                'description' => '微信支付API证书密钥路径（可选）',
                'type' => 'input',
            ],
            'product_name' => [
                'label' => '商品名称',
                'description' => '将会体现在微信支付账单中',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order): array
    {
        try {
            // 构建支付参数
            $params = [
                'appid' => $this->config['appid'],
                'mch_id' => $this->config['mch_id'],
                'nonce_str' => $this->genRandomString(),
                'body' => $this->config['product_name'] ?? (admin_setting('app_name', 'XBoard') . ' - 订阅'),
                'out_trade_no' => $order['trade_no'],
                'total_fee' => $order['total_amount'], // 微信支付金额单位为分
                'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
                'notify_url' => $order['notify_url'],
                'trade_type' => 'NATIVE', // 原生扫码支付
            ];
            
            // 生成签名
            $params['sign'] = $this->sign($params);
            
            // 构建XML数据
            $xmlData = $this->arrayToXml($params);
            
            // 发送请求到微信支付接口
            $response = $this->postXmlCurl('https://api.mch.weixin.qq.com/pay/unifiedorder', $xmlData);
            
            // 解析XML响应
            $result = $this->xmlToArray($response);
            
            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                // 返回二维码链接
                return [
                    'type' => 0, // 二维码类型
                    'data' => $result['code_url']
                ];
            } else {
                $errorMsg = isset($result['err_code_des']) ? $result['err_code_des'] : '未知错误';
                throw new ApiException($errorMsg);
            }
        } catch (\Exception $e) {
            \Log::error($e);
            throw new ApiException($e->getMessage());
        }
    }

    public function notify($params)
    {
        // 获取通知数据
        $xmlData = file_get_contents('php://input');
        $data = $this->xmlToArray($xmlData);
        
        // 验证签名
        $sign = $data['sign'];
        unset($data['sign']);
        if ($sign !== $this->sign($data)) {
            return false;
        }
        
        if ($data['return_code'] !== 'SUCCESS' || $data['result_code'] !== 'SUCCESS') {
            return false;
        }
        
        return [
            'trade_no' => $data['out_trade_no'],
            'callback_no' => $data['transaction_id']
        ];
    }
    
    // 生成随机字符串
    private function genRandomString($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }
    
    // 生成签名
    private function sign($params)
    {
        ksort($params);
        $stringA = '';
        foreach ($params as $k => $v) {
            if ($v && $k != 'sign') {
                $stringA .= "{$k}={$v}&";
            }
        }
        $stringSignTemp = $stringA . 'key=' . $this->config['key'];
        return strtoupper(md5($stringSignTemp));
    }
    
    // 数组转XML
    private function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            $xml .= "<{$key}>{$val}</{$key}>";
        }
        $xml .= "</xml>";
        return $xml;
    }
    
    // XML转数组
    private function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);
        $xml_parser = xml_parser_create();
        if (!\is_resource($xml_parser)) {
            throw new ApiException('XML解析失败');
        }
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
    
    // 发送XML请求
    private function postXmlCurl($url, $xml, $useCert = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        
        if ($useCert) {
            // 设置证书
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $this->config['cert_path']);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $this->config['key_path']);
        }
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new ApiException(curl_error($ch));
        }
        
        curl_close($ch);
        return $response;
    }
} 