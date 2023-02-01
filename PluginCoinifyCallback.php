<?php
require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/admin/models/StatusAliasGateway.php' ;
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'modules/billing/models/Invoice_EventLog.php';
require_once 'modules/admin/models/Error_EventLog.php';

class PluginCoinifyCallback extends PluginCallback
{

    function processCallback()
    {
        $ipnsecret = $this->settings->get('plugin_coinify_IPN Secret');
        $data = file_get_contents("php://input");
        if (getallheaders()['x-coinify-callback-signature']) {
            $signature = getallheaders()['x-coinify-callback-signature'];
        }
        if (getallheaders()['X-Coinify-Callback-Signature']) {
            $signature = getallheaders()['X-Coinify-Callback-Signature'];
        }
        $expected_signature = strtolower( hash_hmac('sha256', $data, $ipnsecret, false) );
        if ($signature !== $expected_signature) {
            CE_Lib::log(1, "Coinify Someone called us with an invalid callback signature: ". print_r($data, true));
            exit();
        }
        $json = json_decode($data, true);
        CE_Lib::log(4, "Callback: ". print_r($json, true));
        $bpInvoiceId = $json['data']['custom']['invoice_id'];

        $cPlugin = new Plugin($invoiceData['data']['custom']['invoice_id'], "Coinify", $this->user);
        $cPlugin->setAmount($invoiceData['data']['native']['amount']);
        $cPlugin->setAction('charge');

        switch ($invoiceData['data']['state']) {
            case 'paid':
                $transaction = "Coinify payment of {$invoiceData['data']['native']['amount']} has been received.";
                $cPlugin->PaymentPending($transaction, $bpInvoiceId);
                break;

            case 'complete':
                $transaction = "Coinify payment of {$invoiceData['data']['native']['amount']} has been completed.";
                $cPlugin->PaymentAccepted($invoiceData['data']['native']['amount'], $transaction, $bpInvoiceId);
                break;

            case 'expired':
                $transaction = 'Invalid Transaction';
                $cPlugin->PaymentRejected($transaction);
                break;
        }
    }

}