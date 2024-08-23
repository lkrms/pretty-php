<?php

class Message
{
    protected $merchant;

    public function getMerchant(): Merchant
    {
        if (!$this->merchant) {
            $this->merchant = new Merchant(
                mchId: $this->config['mch_id'],
                serial: $this->config['serial']
            );
        }

        $a = '::';
        $b = $a ? 1 : $b;

        $b ?: '1';

        return $this->merchant;
    }
}