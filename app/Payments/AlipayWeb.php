<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;

class AlipayWeb implements PaymentInterface
{
    protected $config;
    
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'app_id' => [
                'label' => '支付宝APPID',
                'description' => '',
                'type' => 'input',
            ],
            'private_key' => [
                'label' => '支付宝应用私钥',
                'description' => '填写PKCS1格式的应用私钥',
                'type' => 'input',
            ],
            'public_key' => [
                'label' => '支付宝公钥',
                'description' => '填写支付宝公钥',
                'type' => 'input',
            ],
            'product_name' => [
                'label' => '自定义商品名称',
                'description' => '将会体现在支付宝账单中',
                'type' => 'input'
            ]
        ];
    }

    public function pay($order): array
    {
        try {
            // 构建支付参数
            $params = [
                'app_id' => $this->config['app_id'],
                'method' => 'alipay.trade.page.pay',
                'format' => 'JSON',
                'return_url' => $order['return_url'],
                'charset' => 'utf-8',
                'sign_type' => 'RSA2',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0',
                'notify_url' => $order['notify_url'],
                'biz_content' => json_encode([
                    'out_trade_no' => $order['trade_no'],
                    'product_code' => 'FAST_INSTANT_TRADE_PAY',
                    'total_amount' => sprintf('%.2f', $order['total_amount'] / 100), // 元为单位
                    'subject' => $this->config['product_name'] ?? (admin_setting('app_name', 'XBoard') . ' - 订阅')
                ])
            ];

            // 签名
            ksort($params);
            $signStr = '';
            foreach ($params as $k => $v) {
                if (empty($v) || $k == 'sign') continue;
                $signStr .= $k . '=' . $v . '&';
            }
            $signStr = rtrim($signStr, '&');
            
            // 生成签名
            $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" . 
                          wordwrap($this->config['private_key'], 64, "\n", true) . 
                          "\n-----END RSA PRIVATE KEY-----";
            
            $sign = '';
            openssl_sign($signStr, $sign, $privateKey, OPENSSL_ALGO_SHA256);
            $params['sign'] = base64_encode($sign);
            
            // 构建支付URL
            $payUrl = 'https://openapi.alipay.com/gateway.do?' . http_build_query($params);
            
            return [
                'type' => 1, // url类型
                'data' => $payUrl
            ];
        } catch (\Exception $e) {
            \Log::error($e);
            throw new ApiException($e->getMessage());
        }
    }

    public function notify($params)
    {
        // 验证签名
        $sign = $params['sign'];
        unset($params['sign']);
        unset($params['sign_type']);
        
        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            $signStr .= $k . '=' . $v . '&';
        }
        $signStr = rtrim($signStr, '&');
        
        // 支付宝公钥
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" . 
                     wordwrap($this->config['public_key'], 64, "\n", true) . 
                     "\n-----END PUBLIC KEY-----";
        
        $result = openssl_verify($signStr, base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256);
        
        if ($result !== 1) {
            return false;
        }
        
        // 检查交易状态
        if ($params['trade_status'] !== 'TRADE_SUCCESS' && $params['trade_status'] !== 'TRADE_FINISHED') {
            return false;
        }
        
        return [
            'trade_no' => $params['out_trade_no'],
            'callback_no' => $params['trade_no'] // 支付宝交易号
        ];
    }
} 