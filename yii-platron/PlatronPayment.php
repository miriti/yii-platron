<?php

class PlatronPayment extends CApplicationComponent
{
    const PAYMENT_URL = 'https://www.platron.ru/payment.php';
    
    public $merchant_id;
    
    public $secret_key;
    
    public $site_url;
    
    public $test_mode = true;
    
    public $result_url;
    
    public $success_url;
    
    public $failure_url;
    
    public $request_method;
    
    public function getUrlForPayment($order_id, $amount, $description, $currency='RUR', $language='ru')
    {
        $result = $this->getParams($order_id, $amount, $description, $currency, $language);

        return self::PAYMENT_URL . "?" . $result;
    }

    /**
     * Generates XML from array
     */
    private static function generateXML($arr) {
        $result = "";

        foreach ($arr as $key => $value) {
            $result .= '<' . $key . '>' . $value . '</' . $key . '>';
        }

        return $result;
    }
    
    public function checkPayment($oOrder, $scriptName = null)
    {
        header("Content-Type: text/xml");
        echo '<?xml version="1.0" encoding="utf-8"?>
        <response>';

        $params = $_REQUEST;
        
        if($scriptName === null)
        {
            $scriptName = $this->extractScriptName($_SERVER['REQUEST_URI']);
        }
        
        $sent_sig = $params['pg_sig'];
        unset($params['pg_sig']);
    
        $response_params = array();

        if ($sent_sig != $this->getSig($params, $scriptName)) 
        {
            $response_params['pg_status'] = 'error';
            $response_params['pg_description'] = 'Bad signature';
            echo self::generateXML($response_params) . '</response>';

            return false;
        }
    
        $response_params['pg_status'] = 'ok';
        $response_params['pg_salt'] = $this->getSalt();

        $response_sig = $this->getSig($response_params, $scriptName);

        $response_params['pg_sig'] = $response_sig;

        echo self::generateXML($response_params) . '</response>';

        return true;
    }
    
    protected function getParams($order_id, $amount, $description, $currency='RUR', $language='ru')
    {

        $result = array(
            'pg_merchant_id' => $this->merchant_id, // Идентификатор продавца в Platron
            'pg_order_id' => $order_id,             // Идентификатор платежа в системе продавца. Рекомендуется поддерживать уникальность этого поля.
            'pg_amount' => $amount,                 // Сумма платежа в валюте pg_currency
            'pg_currency' => $currency,                 // Валюта, в которой указана сумма. RUR, USD, EUR.
            'pg_description' => $description,
            'pg_user_ip' => $_SERVER['REMOTE_ADDR'],
            'pg_language' => $language,
            'pg_testing_mode' => intval($this->test_mode),
            'pg_salt' => $this->getSalt() // Случайная строка
        );
        
        if ($this->site_url)
        {
            $result['pg_site_url'] = $this->site_url;
        }
        
        if ($this->result_url)
        {
            $result['pg_result_url'] = $this->result_url;
        }
        
        if ($this->success_url)
        {
            $result['pg_success_url'] = $this->success_url;
        }
        
        if ($this->failure_url)
        {
            $result['pg_failure_url'] = $this->failure_url;
        }
        
        if ($this->request_method)
        {
            $result['pg_request_method'] = $this->request_method;
        }
        
        $sig = $this->getSig($result);
        
        $result['pg_sig'] = $sig;
        
        $res = "";
        
        foreach ($result as $k => $val)
        {
            $res .= "&" . $k . "=" . $val;
        }
        
        return substr($res, 1);
    }
    
    protected function getSalt($length = 6) 
    {
        $validCharacters = "abcdefghijklmnopqrstuxyvwzABCDEFGHIJKLMNOPQRSTUXYVWZ+-*#&@!?";
        $validCharNumber = strlen($validCharacters);

        $result = "";

        for ($i = 0; $i < $length; $i++) 
        {
            $index = mt_rand(0, $validCharNumber - 1);
            $result .= $validCharacters[$index];
        }

        return $result;

    }
    
    protected function getSig($aParams, $script = false)
    {
        if (!$script)
            $script = $this->extractScriptName(self::PAYMENT_URL);
        
        ksort($aParams);
        $result = implode(";", $aParams);

        $string_to_hash = $script . ";" . $result . ";" . $this->secret_key;
                
        return md5($string_to_hash);
    }

    protected function extractScriptName($url)
    {
        return substr($url, strrpos($url, "/") + 1);
    }
}