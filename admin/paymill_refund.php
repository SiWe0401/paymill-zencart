<?php
require_once('includes/application_top.php');
require_once(DIR_WS_CLASSES . 'order.php');
require_once(dirname(__FILE__) . '/../ext/modules/payment/paymill/lib/Services/Paymill/Refunds.php');
if (isset($_GET['oID']) && !empty($_GET['oID'])) {
    
    global $db;
    
    $order = new order($_GET['oID']);

    $transaction = $db->Execute("SELECT * FROM pi_paymill_transaction WHERE order_id = '" . $_GET['oID'] . "'");
    require_once(dirname(__FILE__) . '/../includes/modules/payment/' . $transaction->fields['payment_code'] . '.php');
    include(dirname(__FILE__) . '/../includes/languages/' . $_SESSION['language'] . '/modules/payment/' . $transaction->fields['payment_code'] . '.php');

    $payment = new $transaction->fields['payment_code']();
    
    //Create Refund
    $params = array(
        'transactionId' => $transaction->fields['transaction_id'],
        'source' => $payment->version . '_' . str_replace(' ', '_', PROJECT_VERSION),
        'params' => array('amount' => $transaction->fields['amount'])
    );
    
    $refundsObject = new Services_Paymill_Refunds($payment->privateKey, $payment->apiUrl);
    
    $error = '';

    try {
        $refund = $refundsObject->create($params);
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
    
    if (isset($refund['response_code']) && $refund['response_code'] == 20000) {
        $statusArray = $db->Execute("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Paymill [Refund]' limit 1");
        $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status='" . $statusArray['orders_status_id'] . "' WHERE orders_id='" . $_GET['oID'] . "'");

        $messageStack->add_session(PAYMILL_REFUND_SUCCESS, 'success');
    } else {
        $messageStack->add_session(PAYMILL_REFUND_ERROR, 'error');
    }
}

zen_redirect(zen_href_link(FILENAME_ORDERS, 'oID=' . $_GET['oID'] . '&action=edit', true, false));