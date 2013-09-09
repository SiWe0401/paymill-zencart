<?php
require_once('paymill/paymill_abstract.php');

class paymillElv extends paymill_abstract
{

    function paymillElv()
    {
        parent::paymill_abstract();
        global $order;

        $this->code = 'paymillElv';
        $this->version = '1.2.1';
        $this->api_version = '2';
        $this->title = MODULE_PAYMENT_PAYMILL_ELV_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_PAYMILL_ELV_TEXT_PUBLIC_TITLE;

        if (defined('MODULE_PAYMENT_PAYMILL_ELV_STATUS')) {
            $this->enabled = ((MODULE_PAYMENT_PAYMILL_ELV_STATUS == 'True') ? true : false);
            $this->sort_order = MODULE_PAYMENT_PAYMILL_ELV_SORT_ORDER;
            $this->privateKey = trim(MODULE_PAYMENT_PAYMILL_ELV_PRIVATEKEY);
            $this->logging = ((MODULE_PAYMENT_PAYMILL_ELV_LOGGING == 'True') ? true : false);
            $this->label = ((MODULE_PAYMENT_PAYMILL_ELV_LABEL == 'True') ? true : false);
            $this->publicKey = MODULE_PAYMENT_PAYMILL_ELV_PUBLICKEY;
            $this->fastCheckoutFlag = ((MODULE_PAYMENT_PAYMILL_ELV_FASTCHECKOUT == 'True') ? true : false);
            $this->payments = new Services_Paymill_Payments($this->privateKey, $this->apiUrl);
            $this->clients = new Services_Paymill_Clients(trim($this->privateKey), $this->apiUrl);
            if ((int) MODULE_PAYMENT_PAYMILL_ELV_ORDER_STATUS_ID > 0) {
                $this->order_status = MODULE_PAYMENT_PAYMILL_ELV_ORDER_STATUS_ID;
            }
                        
            if ($this->logging) {
                $this->description = '<a href="' . zen_href_link('paymill_logging.php') . '">PAYMILL Log</a>';
            }
        }

        if (is_object($order)) $this->update_status();
    }    
    
    function selection()
    {
        $selection = parent::selection();
        
        if ($this->label) {
            $label = '<div class="form-row">'
                      . '<div class="paymill_powered">'
                           . '<div class="paymill_credits">'
                               . MODULE_PAYMENT_PAYMILL_ELV_TEXT_SAVED
                              . ' <a href="http://www.paymill.de" target="_blank">PAYMILL</a>'
                           . '</div>'
                       . '</div>'
                   . '</div>';
            $formArray = array();

            $formArray[] = array(
                'field' => '<link rel="stylesheet" type="text/css" href="ext/modules/payment/paymill/public/css/paymill.css" />'
            );

            $formArray[] = array(
                'field' => $label      
            );

            $selection['fields'] = $formArray;
        }
        
        return $selection;
    }
        
    function getPayment($userId)
    {
        $payment = array(
            'code' => '',
            'holder' => '',
            'account' => ''
        );
        
        if ($this->fastCheckout->hasElvPaymentId($userId)) {
            $data = $this->fastCheckout->loadFastCheckoutData($userId);
            $payment = $this->payments->getOne($data['paymentID_ELV']);
        }
        
        return $payment;
    }
    
    function confirmation()
    {
       global $order;
        
        $confirmation = parent::confirmation();
        
        $payment = $this->getPayment($_SESSION['customer_id']);
        
        $this->fastCheckout->setFastCheckoutFlag($this->fastCheckoutFlag);
        
        $script = '<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>'
                . '<script type="text/javascript">'
                . 'var elvlogging = "' . MODULE_PAYMENT_PAYMILL_ELV_LOGGING . '";'
                . 'var elv_account_number_invalid = "' . utf8_decode(MODULE_PAYMENT_PAYMILL_ELV_TEXT_ACCOUNT_INVALID) . '";'
                . 'var elv_bank_code_invalid = "' . utf8_decode(MODULE_PAYMENT_PAYMILL_ELV_TEXT_BANKCODE_INVALID) . '";'
                . 'var elv_bank_owner_invalid = "' . utf8_decode(MODULE_PAYMENT_PAYMILL_ELV_TEXT_ACCOUNT_HOLDER_INVALID) . '";'
                . 'var paymill_account_name = ' . json_encode($order->billing['firstname'] . ' ' . $order->billing['lastname']) . ';'
                . 'var paymill_elv_code = "' . $payment['code'] . '";'
                . 'var paymill_elv_holder = "' . utf8_decode($payment['holder']) . '";'
                . 'var paymill_elv_account = "' . $payment['account'] . '";'
                . 'var paymill_elv_fastcheckout = ' . $this->fastCheckout->canCustomerFastCheckoutElvTemplate($_SESSION['customer_id']) . ';'
                . 'var checkout_payment_link = "' . zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'step=step2', 'SSL', true, false). '&payment_error=' . $this->code . '&error=300' . '";'
                . '</script>'
                . '<script type="text/javascript" src="ext/modules/payment/paymill/public/javascript/elv.js"></script>';
        
        array_push($confirmation['fields'], 
            array(
                'field' => $script
            )
        );
        
        array_push($confirmation['fields'], 
            array(
                'title' => '<div class="paymill-label-field">' . MODULE_PAYMENT_PAYMILL_ELV_TEXT_ACCOUNT_HOLDER . '</div>',
                'field' => '<span id="account-name-field"></span><span id="elv-holder-error" class="paymill-error"></span>'
            )
        );
        
        array_push($confirmation['fields'], 
            array(
                'title' => '<div class="paymill-label-field">' . MODULE_PAYMENT_PAYMILL_ELV_TEXT_ACCOUNT . '</div>',
                'field' => '<span id="account-number-field"></span><span id="elv-account-error" class="paymill-error"></span>'
            )
        );
        
        array_push($confirmation['fields'], 
            array(
                'title' => '<div class="paymill-label-field">' . MODULE_PAYMENT_PAYMILL_ELV_TEXT_BANKCODE . '</div>',
                'field' => '<span id="bank-code-field"></span><span id="elv-bankcode-error" class="paymill-error"></span>'
            )
        );       
        
        array_push($confirmation['fields'], 
            array(
                'field' => '<form id="paymill_form" action="' . zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL') . '" method="post" style="display: none;"></form>'
            )
        );

        return $confirmation;
    }

    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYMILL_ELV_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    function install()
    {
        global $db;

        parent::install();
        
        @include(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paymillElv.php');

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . MODULE_PAYMENT_PAYMILL_ELV_STATUS_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_STATUS_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_STATUS', 'True', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . MODULE_PAYMENT_PAYMILL_ELV_FASTCHECKOUT_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_FASTCHECKOUT_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_FASTCHECKOUT', 'False', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . MODULE_PAYMENT_PAYMILL_ELV_LABEL_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_LABEL_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_LABEL', 'False', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, date_added) VALUES ('" . MODULE_PAYMENT_PAYMILL_ELV_SORT_ORDER_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_SORT_ORDER_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_SORT_ORDER', '0', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, date_added) VALUES ('" . MODULE_PAYMENT_PAYMILL_ELV_PRIVATEKEY_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_PRIVATEKEY_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_PRIVATEKEY', '0', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, date_added) VALUES ('" . MODULE_PAYMENT_PAYMILL_ELV_PUBLICKEY_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_PUBLICKEY_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_PUBLICKEY', '0', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, set_function, use_function, date_added) values ('" . MODULE_PAYMENT_PAYMILL_ELV_ORDER_STATUS_ID_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_ORDER_STATUS_ID_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_ORDER_STATUS_ID', '0',  '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . MODULE_PAYMENT_PAYMILL_ELV_LOGGING_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_LOGGING_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_LOGGING', 'False', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, set_function, use_function, date_added) values ('" . MODULE_PAYMENT_PAYMILL_ELV_TRANS_ORDER_STATUS_ID_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_TRANS_ORDER_STATUS_ID_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_TRANSACTION_ORDER_STATUS_ID', '" . $this->getOrderStatusTransactionID() . "', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_description, configuration_key, configuration_value, configuration_group_id, sort_order, use_function, set_function, date_added) values ('" . MODULE_PAYMENT_PAYMILL_ELV_ZONE_TITLE . "', '" . MODULE_PAYMENT_PAYMILL_ELV_ZONE_DESC . "', 'MODULE_PAYMENT_PAYMILL_ELV_ZONE', '0', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    }

    function keys()
    {
        return array(
            'MODULE_PAYMENT_PAYMILL_ELV_STATUS',
            'MODULE_PAYMENT_PAYMILL_ELV_FASTCHECKOUT',
            'MODULE_PAYMENT_PAYMILL_ELV_LABEL',
            'MODULE_PAYMENT_PAYMILL_ELV_PRIVATEKEY',
            'MODULE_PAYMENT_PAYMILL_ELV_PUBLICKEY',
            'MODULE_PAYMENT_PAYMILL_ELV_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYMILL_ELV_TRANSACTION_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYMILL_ELV_ZONE',
            'MODULE_PAYMENT_PAYMILL_ELV_LOGGING',
            'MODULE_PAYMENT_PAYMILL_ELV_SORT_ORDER'
        );
    }
}
?>
