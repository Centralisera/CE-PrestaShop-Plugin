<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}


    class CENTRALISERARequestModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	**/

	

	public function postProcess()
	{
		$cart = $this->context->cart;
		$cookie = $this->context->cookie;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $address = new Address(intval($cart->id_address_invoice));
        $address_ship = new Address(intval($cart->id_address_delivery));
        $currency = new Currency(intval($cart->id_currency));
        $currency_iso_code = $currency->iso_code;

        $postCode = $address->postcode ?? "N/A";
        
        $data = array();


        if ($cart->id_currency != $currency->id)
        {
            // If centralisera currency differs from local currency
            $cart->id_currency = (int)$currency->id;
            $cookie->id_currency = (int)$cart->id_currency;
            $cart->update();
        }

		$data['total_amount'] = number_format( sprintf( "%01.2f", $total ), 2, '.', '' );

        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'CENTRALISERA') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            exit($this->module->getTranslator()->trans('This payment method is not available.', [], 'Modules.CENTRALISERA.Shop'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_PREPARATION'),
            $total,
            $this->module->displayName,
            null,
            array(),
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $orderDetails =  Order::getByCartId($cart->id);
        $history = new OrderHistory();
        $history->id_order = (int)$orderDetails->id;

        if (!Validate::isLoadedObject($orderDetails)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $account_id = Configuration::get('CENTRALISERA_ACCOUNT_ID');
        $app_key = Configuration::get('CENTRALISERA_APP_KEY');
        $secret_key = Configuration::get('CENTRALISERA_SECRET_KEY');

        $requestData = '{
            "firstName" : "'.$customer->firstname.'",
                "middleName" : "",
                "lastName" : "'.$customer->lastname.'",
                "reference" : "'.$orderDetails->reference.'" ,
                "email" : "'.$customer->email.'",
                "dob" : "'.$customer->birthday.'",
                "contactNumber" : "'.$address->phone.'",
                "merchantAaccountId" : "'.$account_id.'",
                "address" : "'.$address->address1.' '.$address_ship->address2.'",
                "country" : "'.$address->country.'",
                "state" : "N/A",
                "city" : "'.$address->city.'",
                "zipCode" : "'.$postCode.'",
                "currency" : "'.$currency_iso_code.'",
                "amount" : "'.$data['total_amount'].'",
                "ttl" : "10",
                "tagName" : "", 
                "webhookUrl" : "'.$this->trans($this->context->link->getModuleLink('CENTRALISERA', 'ipn', array(), true)).'"
        }';

        $api_mode = Configuration::get('MODE');

        if($api_mode == 1) {
            $payment_url = 'https://staging.centralisera.com/api/v1/payment/request';
        }
        else {
            $payment_url = 'https://dev.centralisera.com/api/v1/payment/request';
        }

        $username = $app_key;
        $password = $secret_key;

        $log_filename = "logfile";
        if (!file_exists($log_filename)) {
            mkdir($log_filename, 0777, true);
        }

        $log_file_data = $log_filename.'/log_' . date('d-M-Y') . '.log';
        //file_put_contents($log_file_data, 'log message calling.....' . "\n", FILE_APPEND);
        //file_put_contents($log_file_data, 'request Data.....' . $requestData."\n", FILE_APPEND);
        //file_put_contents($log_file_data, 'request credentials user.....' . $username."\n", FILE_APPEND);
        //file_put_contents($log_file_data, 'request credentials pass.....' . $password."\n", FILE_APPEND);
        //file_put_contents($log_file_data, 'request url.....' . $payment_url."\n", FILE_APPEND);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $payment_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$requestData,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic '.base64_encode("$username:$password")
            ),
        ));

        $content = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        //file_put_contents($log_file_data, 'response Data.....' . $content."\n", FILE_APPEND);
        //file_put_contents($log_file_data, 'code Data.....' . $code."\n", FILE_APPEND);


        if($code == 200 && !( curl_errno($curl)))
        {
            curl_close( $curl);

            $apiResponse = $content;

            $response = json_decode($apiResponse, true );

            if ($response['success']){

                setcookie('cart_id', $cart->id, time() + (60*2), "/");

                if ($response['data']['payment_url']){
                    header('Location: '.$response['data']['payment_url']);
                    exit;
                }
            }
        }
        else{

            $history->changeIdOrderState(8, (int)($orderDetails->id));

            $update_order_history_status_id =" UPDATE `ps_order_history` SET `id_order_state`=8 WHERE `id_order`='$history->id_order' ";
            Db::getInstance()->execute($update_order_history_status_id);

            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }


	}
}
?>