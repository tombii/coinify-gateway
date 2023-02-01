<?php

require_once 'modules/billing/models/class.gateway.plugin.php';

class PluginCoinify extends GatewayPlugin
{
    public function getVariables()
    {
        $variables = array (
            lang("Plugin Name") => array (
                "type"          => "hidden",
                "description"   => lang("How CE sees this plugin (not to be confused with the Signup Name)"),
                "value"         => lang("Coinify")
            ),
            lang("API Key") => array (
                "type"          => "text",
                "description"   => lang("Enter your API Key from your coinify merchant account"),
                "value"         => ""
            ),
            lang("API Secret") => array (
                "type"          => "text",
                "description"   => lang("Enter your API Secret from your coinify merchant account"),
                "value"         => ""
            ),
            lang("IPN Secret") => array (
                "type"          => "text",
                "description"   => lang("Enter your IPN Secret from your coinify merchant account"),
                "value"         => ""
            ),
            lang("Use Testing Environment?") => array(
                "type"          => "yesno",
                "description"   => lang("Select YES if you wish to use the testing environment instead of the live environment"),
                "value"         => "0"
            ),
            lang("Signup Name") => array (
                "type"          => "text",
                "description"   => lang("Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card."),
                "value"         => "Bitcoin (BTC)"
            )

        );
        return $variables;
    }

    public function credit($params)
    {
    }

    public function singlepayment($params, $test = false)
    {
        $data = [];
        $data['amount'] = $params['invoiceTotal'];
        $data['currency'] = 'EUR';
        $data['plugin_name'] = "ClientExec";
        $data['plugin_version'] = "1.0";
        $data['description'] = $params['invoiceDescription'];
        $data['callback_url'] = $params['clientExecURL'] . '/plugins/gateways/coinify/callback.php';
        $data['return_url'] = $params['invoiceviewURLSuccess'];
        $data['cancel_url'] = $params['invoiceviewURLCancel'];
        $data['custom'] = array('invoice_id' => $params['invoiceNumber']);

        CE_Lib::log(4, 'Coinify Params: ' . print_r($data, true));
        $data = json_encode($data);
        $return = $this->makeRequest($params, $data, true);

        if (isset($return['error'])) {
            $cPlugin = new Plugin($params['invoiceNumber'], "coinify", $this->user);
            $cPlugin->setAmount($params['invoiceTotal']);
            $cPlugin->setAction('charge');
            $cPlugin->PaymentRejected($this->user->lang("There was an error performing this operation.") . ' ' . $return['error']['message']);
            return $this->user->lang("There was an error performing this operation.") . ' ' . $return['error']['message'];
        }
        header('Location: ' . $return['data']['payment_url']);
        exit;
    }

    private function makeRequest($params, $data, $post = false)
    {
        $url = 'https://api.coinify.com/v3/invoices';
        if ($params['plugin_coinify_Use Testing Environment?'] == '1') {
            $url = 'https://api.sandbox.coinify.com/v3/invoices';
        }

        CE_Lib::log(4, 'Making request to: ' . $url);
        $ch = curl_init($url);
        if ($post === true) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $apikey = $params['plugin_coinify_API Key'];
        $apisecret = $params['plugin_coinify_API Secret'];
        $mt = explode(' ', microtime());
        $nonce = $mt[1] . substr($mt[0], 2, 6);

        // Concatenate the nonce and the API key
        $message = $nonce . $apikey;
        // Compute the signature and convert it to lowercase
        $signature = strtolower( hash_hmac('sha256', $message, $apisecret, false ) );

        // Construct the HTTP Authorization header.
        $auth_header = "Authorization: Coinify apikey=\"$apikey\", nonce=\"$nonce\", signature=\"$signature\"";

        curl_setopt($ch, CURLOPT_HTTPHEADER, array($auth_header));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        if (!$response) {
            throw new CE_Exception('cURL Coinify Error: ' . curl_error($ch) . '( ' .curl_errno($ch) . ')');
        }
        curl_close($ch);
        $response = json_decode($response, true);
        CE_Lib::log(4, 'Coinify Response: ' . print_r($response, true));

        return $response;
    }
}