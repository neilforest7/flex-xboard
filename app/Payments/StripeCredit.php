<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;

class StripeCredit implements PaymentInterface
{
    protected $config;
    
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'stripe_sk_live' => [
                'label' => 'Stripe Secret Key',
                'description' => 'Stripe平台的Secret Key',
                'type' => 'input',
            ],
            'stripe_pk_live' => [
                'label' => 'Stripe Public Key',
                'description' => 'Stripe平台的Public Key',
                'type' => 'input',
            ],
            'stripe_webhook_key' => [
                'label' => 'Webhook密钥',
                'description' => 'Stripe平台的Webhook密钥',
                'type' => 'input',
            ],
            'stripe_currency' => [
                'label' => '货币单位',
                'description' => '默认为CNY',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order): array
    {
        if (empty($order['stripe_token'])) {
            throw new ApiException('未获取到有效的支付token');
        }
        
        try {
            Stripe::setApiKey($this->config['stripe_sk_live']);
            $currency = $this->config['stripe_currency'] ?? 'cny';
            
            $charge = Charge::create([
                'amount' => $order['total_amount'],
                'currency' => strtolower($currency),
                'source' => $order['stripe_token'],
                'description' => '订单号: ' . $order['trade_no'],
                'metadata' => [
                    'trade_no' => $order['trade_no']
                ]
            ]);
            
            if ($charge->status === 'succeeded') {
                // 支付成功，返回成功结果
                return [
                    'type' => -1, // 直接完成支付
                    'data' => true
                ];
            } else {
                throw new ApiException('支付处理失败');
            }
        } catch (ApiErrorException $e) {
            \Log::error('Stripe支付错误: ' . $e->getMessage());
            throw new ApiException($e->getMessage());
        }
    }

    public function notify($params)
    {
        // 获取webhook内容
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        try {
            // 使用Webhook Key验证签名
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $this->config['stripe_webhook_key']
            );
            
            // 处理不同的事件类型
            if ($event->type === 'charge.succeeded') {
                $charge = $event->data->object;
                return [
                    'trade_no' => $charge->metadata->trade_no,
                    'callback_no' => $charge->id
                ];
            }
            
            return false;
        } catch (\UnexpectedValueException $e) {
            \Log::error('Invalid payload: ' . $e->getMessage());
            return false;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            \Log::error('Invalid signature: ' . $e->getMessage());
            return false;
        }
    }
} 