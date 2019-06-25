<?php
/**
 * Class to send credit to mobile via Orange2
 *
 * MagnusBilling <info@magnusbilling.com>
 * 08/07/2018
 */

class Orange2
{

    public static function sendCredit($number, $modelSendCreditRates, $test)
    {

        if (isset($_POST['TransferToMobile']['metric'])) {
            $order = array(
                "type"        => "cashpower",
                "targetTotal" => $modelSendCreditRates->idProduct->product,
                "beneficiary" => array(
                    'mobile' => '+' . $number,
                    "name"   => "ABC",

                ),
                "meterId"     => $_POST['TransferToMobile']['metric'],
            );
        } else {
            $order = array(
                "type"        => "airtime",
                "targetTotal" => $modelSendCreditRates->idProduct->product,
                "beneficiary" => array(
                    'mobile' => '+' . $number,
                    "name"   => "ABC",

                ));
        }

        //$test = true;
        /*
        INSERT IGNORE INTO pkg_configuration  VALUES
        (NULL, 'Orange2 username', 'Orange2_username', '', 'Orange2 username', 'global', '1'),
        (NULL, 'Orange2 password', 'Orange2_password', '', 'Orange2 password', 'global', '1'),
        (NULL, 'Orange2 test', 'Orange2_test', '0', 'Orange2 teste', 'global', '1');
         */

        $config = LoadConfig::getConfig();

        $username = $config['global']['Orange2_username'];
        $password = $config['global']['Orange2_password'];
        $test     = $config['global']['Orange2_test'];

        if ($test == 1) {
            $url = 'https://qa.baluwo.com/rest/v1/external/transaction';
        } else {
            $url = 'https://app.baluwo.com/rest/v1/external/transaction';
        }

        $data = array(
            "buyer"    => array(
                "mobile" => "+39370000000",
                "name"   => "INVIONET",
            ),
            "clientId" => 12,
            "orders"   => array($order),

        );

        $data_string = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
        ));
        $output = curl_exec($ch);
        $info   = curl_getinfo($ch);
        curl_close($ch);

        $output = json_decode($output);

        // print_r($output);

        if (!isset($output->id) || !is_numeric($output->id)) {
            return 'error_txt=' . print_r($output, true);
        }

        $transaction_id = $output->id;
        if ($test == 1) {
            $url = 'https://qa.baluwo.com/rest/v1/external/transaction/pay/' . $transaction_id;
        } else {
            $url = 'https://app.baluwo.com/rest/v1/external/transaction/pay/' . $transaction_id;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: 0',
        ));
        $output = curl_exec($ch);
        $info   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($info == 204) {
            return 'error_txt=Transaction successful=Orange2=' . $transaction_id;
        } else {
            return 'error_txt=Error' . print_r($output, true);
        }

    }

    public static function checkMetric($metric = '')
    {

        $config = LoadConfig::getConfig();

        $username = $config['global']['Orange2_username'];
        $password = $config['global']['Orange2_password'];
        $test     = $config['global']['Orange2_test'];

        switch ($_POST['TransferToMobile']['country']) {
            case 'Gambia':
                $CC = 'GM';
                break;
            case 'Mali':
                $CC = 'ML';
                break;
            case 'Nigeria':
                $CC = 'NG';
                break;
            case 'Senegal':
                $CC = 'SN';
                break;

            default:
                return false;
                break;
        }

        if ($test == 1) {
            $url = 'https://qa.baluwo.com/rest/v1/external/cashpower/check/' . $CC . '/' . $metric;
        } else {
            $url = 'https://app.baluwo.com/rest/v1/external/cashpower/check/' . $CC . '/' . $metric;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $output = curl_exec($ch);

        curl_close($ch);
        if (preg_match('/Meter not found/', $output)) {
            return false;
        } else if (preg_match('/Unknown/', $output)) {
            return 'Unknown';
        } else if (preg_match('/customerName/', $output)) {
            $output = explode('{"customerName":"', $output);

            return substr($output[1], 0, -2);
        } else {
            return false;
        }

    }

}
