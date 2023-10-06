<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CENTRALISERA extends PaymentModule
{
    const FLAG_DISPLAY_PAYMENT_INVITE = 'BANK_WIRE_PAYMENT_INVITE';
    protected $_html = '';
    protected $_postErrors = array();

    public $mode;
    public $title;
    public $storeid;
    public $password;
    public $details;

	public function __construct()
    {
        $this->name = 'CENTRALISERA';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Centralisera Ltd.';
        $this->controllers = array('payment', 'validation', 'request', 'ipn');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        //$config = Configuration::getMultiple(array('MODE', 'CENTRALISERA_TITLE', 'CENTRALISERA_ACCOUNT_ID', 'CENTRALISERA_APP_KEY', 'CENTRALISERA_DETAILS'));
        $config = Configuration::getMultiple(array('MODE', 'CENTRALISERA_TITLE', 'CENTRALISERA_ACCOUNT_ID','CENTRALISERA_APP_KEY', 'CENTRALISERA_SECRET_KEY', 'CENTRALISERA_DETAILS'));
        if (!empty($config['MODE'])) {
            $this->mode = $config['MODE'];
        }
        if (!empty($config['CENTRALISERA_TITLE'])) {
            $this->title = $config['CENTRALISERA_TITLE'];
        }
        if (!empty($config['CENTRALISERA_ACCOUNT_ID'])) {
            $this->storeid = $config['CENTRALISERA_ACCOUNT_ID'];
        }
        if (!empty($config['CENTRALISERA_APP_KEY'])) {
            $this->password = $config['CENTRALISERA_APP_KEY'];
        }
        if (!empty($config['CENTRALISERA_SECRET_KEY'])) {
            $this->password = $config['CENTRALISERA_SECRET_KEY'];
        }
        if (!empty($config['CENTRALISERA_DETAILS'])) {
            $this->details = $config['CENTRALISERA_DETAILS'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('CENTRALISERA', array(), 'Modules.CENTRALISERA.Admin');
        $this->description = $this->trans('Online Payment Gateway (Local or International Debit/Credit/VISA/Master Card, bKash, DBBL etc)', array(), 'Modules.CENTRALISERA.Admin');
        $this->confirmUninstall = $this->trans('Are you sure about removing these details?', array(), 'Modules.CENTRALISERA.Admin');
    }

    public function install()
    {
        Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE, true);
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions') || !$this->registerHook('actionFrontControllerSetMedia')) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('MODE')
                || !Configuration::deleteByName('CENTRALISERA_TITLE')
                || !Configuration::deleteByName('CENTRALISERA_ACCOUNT_ID')
                || !Configuration::deleteByName('CENTRALISERA_APP_KEY')
                || !Configuration::deleteByName('CENTRALISERA_SECRET_KEY')
                || !Configuration::deleteByName('CENTRALISERA_DETAILS')
                || !Configuration::deleteByName(self::FLAG_DISPLAY_PAYMENT_INVITE)
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE));

            if (!Tools::getValue('CENTRALISERA_ACCOUNT_ID')) {
                $this->_postErrors[] = $this->trans('Please Enter Your Merchant ACCOUNT ID!', array(), 'Modules.CENTRALISERA.Admin');
            } elseif (!Tools::getValue('CENTRALISERA_APP_KEY')) {
                $this->_postErrors[] = $this->trans('Please Enter Store APP_KEY!', array(), "Modules.CENTRALISERA.Admin");
            }elseif (!Tools::getValue('CENTRALISERA_SECRET_KEY')) {
                $this->_postErrors[] = $this->trans('Please Enter Store SECRET_KEY!', array(), "Modules.CENTRALISERA.Admin");
            }elseif (!Tools::getValue('CENTRALISERA_TITLE')) {
                $this->_postErrors[] = $this->trans('Please Enter a Title!', array(), "Modules.CENTRALISERA.Admin");
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('MODE', Tools::getValue('MODE'));
            Configuration::updateValue('CENTRALISERA_TITLE', Tools::getValue('CENTRALISERA_TITLE'));
            Configuration::updateValue('CENTRALISERA_ACCOUNT_ID', Tools::getValue('CENTRALISERA_ACCOUNT_ID'));
            Configuration::updateValue('CENTRALISERA_APP_KEY', Tools::getValue('CENTRALISERA_APP_KEY'));
            Configuration::updateValue('CENTRALISERA_SECRET_KEY', Tools::getValue('CENTRALISERA_SECRET_KEY'));
            Configuration::updateValue('CENTRALISERA_DETAILS', Tools::getValue('CENTRALISERA_DETAILS'));
        }

        $this->_html .= $this->displayConfirmation($this->trans('Settings Updated.', array(), 'Admin.Global'));
    }

    protected function _displayCentralisera()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        // On every pages
        $this->context->controller->registerJavascript('centralisera','modules/'.$this->name.'/js/lib/centralisera.js',['position' => 'bottom','priority' => 10,]);
    }
       
    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayCentralisera();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        if(Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE, Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE)) != 1) {
            return;
        }

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->trans(Configuration::get('CENTRALISERA_TITLE'), array(), 'Modules.CENTRALISERA.Shop'))
                ->setAction($this->context->link->getModuleLink($this->name, 'request', array(), true))
                ->setAdditionalInformation($this->fetch('module:CENTRALISERA/views/templates/hook/easy_checkout.tpl'));
        $newOption->setBinary(true);
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('CENTRALISERA Configuration', array(), 'Modules.CENTRALISERA.Admin'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Active Module', array(), 'Modules.CENTRALISERA.Admin'),
                        'name' => self::FLAG_DISPLAY_PAYMENT_INVITE,
                        'is_bool' => true,
                        'hint' => $this->trans('Enable Or Disable Module', array(), 'Modules.CENTRALISERA.Admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enable', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disable', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Live Mode', array(), 'Modules.CENTRALISERA.Admin'),
                        'name' => 'MODE',
                        'is_bool' => true,
                        'hint' => $this->trans('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.', array(), 'Modules.CENTRALISERA.Admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Test', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Live', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Title', array(), 'Modules.CENTRALISERA.Admin'),
                        'name' => 'CENTRALISERA_TITLE',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Account ID', array(), 'Modules.CENTRALISERA.Admin'),
                        'name' => 'CENTRALISERA_ACCOUNT_ID',
                        'desc' => $this->trans('Your CENTRALISERA Merchant Account ID is the integration credential which can be collected through our managers.', array(), 'Modules.CENTRALISERA.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('App Key', array(), 'Modules.CENTRALISERA.Admin'),
                        'name' => 'CENTRALISERA_APP_KEY',
                        'desc' => $this->trans('Your CENTRALISERA App Key needed to validate transection.', array(), 'Modules.CENTRALISERA.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Secret Key', array(), 'Modules.CENTRALISERA.Admin'),
                        'name' => 'CENTRALISERA_SECRET_KEY',
                        'desc' => $this->trans('Your CENTRALISERA Secret Key needed to validate transection.', array(), 'Modules.CENTRALISERA.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->trans('Details', array(), 'Modules.CENTRALISERA.Admin'),
                        'name' => 'CENTRALISERA_DETAILS'
                    ),
                    array(
                        'label' => $this->trans('IPN URL', array(), 'Modules.CENTRALISERA.Admin'),
                        'hint' => $this->trans('Use this IPN URL to your merchant panel', array(), 'Modules.CENTRALISERA.Admin'),
                        'desc' => $this->trans($this->context->link->getModuleLink('CENTRALISERA', 'ipn', array(), true), array(), 'Modules.CENTRALISERA.Admin')
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure=' .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form, $fields_form_customization));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'MODE' => Tools::getValue('MODE', Configuration::get('MODE')),
            'CENTRALISERA_TITLE' => Tools::getValue('CENTRALISERA_TITLE', Configuration::get('CENTRALISERA_TITLE')),
            'CENTRALISERA_ACCOUNT_ID' => Tools::getValue('CENTRALISERA_ACCOUNT_ID', Configuration::get('CENTRALISERA_ACCOUNT_ID')),
            'CENTRALISERA_APP_KEY' => Tools::getValue('CENTRALISERA_APP_KEY', Configuration::get('CENTRALISERA_APP_KEY')),
            'CENTRALISERA_SECRET_KEY' => Tools::getValue('CENTRALISERA_SECRET_KEY', Configuration::get('CENTRALISERA_SECRET_KEY')),
            'CENTRALISERA_DETAILS' => Tools::getValue('CENTRALISERA_DETAILS', Configuration::get('CENTRALISERA_DETAILS')),
            self::FLAG_DISPLAY_PAYMENT_INVITE => Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE))

        );
    }

    public function getTemplateVars()
    {
        global $cookie, $cart; 

        $cart = new Cart(intval($cookie->id_cart));
        $tran_id = $cart->id;

        $api_mode = Configuration::get('MODE');
        $api_type = "";
        if($api_mode == 1) {
            $api_type = "production";
        }
        else {
            $api_type = "staging";
        }

        return [
            'tran_id' => $tran_id,
            'payment_options' => $this->name,
            'details' => Tools::getValue('CENTRALISERA_DETAILS', Configuration::get('CENTRALISERA_DETAILS')),
            'endpoint' => $this->context->link->getModuleLink($this->name, 'request', array(), true),
            'api_type' => $api_type,
        ];
    }

}




