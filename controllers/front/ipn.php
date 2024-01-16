<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CENTRALISERAIpnModuleFrontController extends ModuleFrontController
	{
		/**
		 * @see FrontController::postProcess()
		**/
		public function postProcess()
		{
            $log_filename = "logfile";
            if (!file_exists($log_filename)) {
                mkdir($log_filename, 0777, true);
            }

            //$log_file_data = $log_filename.'/log_' . date('d-M-Y') . '.log';
            //file_put_contents($log_file_data, 'log message calling.....' . "\n", FILE_APPEND);

            if($_SERVER['REQUEST_METHOD'] == 'GET'){

                //$cart = null;

                if(isset($_COOKIE['cart_id'])) {
                    $cart = $_COOKIE['cart_id'];
                    setcookie("cart_id", "", time() - (60*2));
                }else{
                    Tools::redirect('en/order-history');
                }

                $order_id = Order::getOrderByCartId((int)($cart));
                $objOrder = new Order($order_id);
                $history = new OrderHistory();
                $history->id_order = (int)$objOrder->id;


                if ($history->id_order){
                    $cart = null;
                    Tools::redirect('index.php?controller=order-detail&id_order='.$history->id_order);
                }else{
                    $cart = null;
                    Tools::redirect('index.php?controller=order&step=1');
                }
            }

            if(isset($_POST))
            {
                //file_put_contents($log_file_data, 'here i am posting.....' ."\n", FILE_APPEND);

                $data = json_decode(file_get_contents("php://input"), true);

                $orderDetailsByRef = Order::getByReference($data['reference']);

                $orderDetails = [];

                if ($orderDetailsByRef) {
                    foreach ($orderDetailsByRef as $order) {
                        $orderDetails = $order;
                        break;
                    }
                }

                $objOrder = new Order((int)$orderDetails->id);
                $history = new OrderHistory();
                $history->id_order = $objOrder->id;

                // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
                $authorized = false;

                $moduleInstance = Module::getInstanceByName('CENTRALISERA');

                if ($moduleInstance && $moduleInstance->active == 1 ) {
                    $authorized = true;
                }

                if (!$authorized) {
                    $history->changeIdOrderState(8, (int)($history->id_order));
                    $update_order_history_status_id =" UPDATE `ps_order_history` SET `id_order_state`=8 WHERE `id_order`='$history->id_order' ";
                    Db::getInstance()->execute($update_order_history_status_id);
                    exit($this->module->getTranslator()->trans('This payment method is not available.', [], 'Modules.CENTRALISERA.Shop'));
                }

                if($data['status'] == 'success')
                {
                    $history->changeIdOrderState(2, (int)($history->id_order));

                    $trx_id = $data['transaction_id'];
                    $order_ref = $data['reference'];

                    //update payment transaction_id into prestashop order_paymnet table
                    $update_transaction_id=" UPDATE `ps_order_payment` SET `transaction_id`='$trx_id' WHERE `order_reference`='$order_ref' ";
                    Db::getInstance()->execute($update_transaction_id);

                    //Update Order history current status
                    $update_order_history_status_id =" UPDATE `ps_order_history` SET `id_order_state`=2 WHERE `id_order`='$history->id_order' ";
                    Db::getInstance()->execute($update_order_history_status_id);

                    Tools::redirect('index.php?controller=order-detail&id_order='.$history->id_order);

                }

                else {

                    $history->changeIdOrderState(8, (int)($history->id_order));
                    $update_order_history_status_id =" UPDATE `ps_order_history` SET `id_order_state`=8 WHERE `id_order`='$history->id_order' ";
                    Db::getInstance()->execute($update_order_history_status_id);

                    Tools::redirect('index.php?controller=order-detail&id_order='.$history->id_order);
                }
            }
            Tools::redirect('index.php?controller=order-detail&id_order='.$history->id_order);
		}
		
		
		//------------------------------ END ---------------------------------
	}
?>