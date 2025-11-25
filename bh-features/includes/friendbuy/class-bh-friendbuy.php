<?php 
require_once 'class-friendbuy-webhook-handler.php';
require_once 'class-friendbuy-referral-manager.php';
require_once 'class-friendbuy-coupon-manager.php';

class Bh_FriendBuy {

    public function __construct() {
        $this->init_webhook_handler();
    }
    private function init_webhook_handler() {
        new FriendBuy_Webhook_Handler();
    }

}

// Inicializar el plugin
new Bh_FriendBuy();