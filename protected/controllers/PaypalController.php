<?php

/**
 * Url for paypal ruturn http://ip/billing/index.php/paypal .
 */
class PaypalController extends Controller
{

    public function actionIndex()
    {

        /*$_POST = array(
        'mc_gross' => '50.00',
        'protection_eligibility' => 'Ineligible',
        'payer_id' => 'WVJ3YK6545HDVC',
        'tax' => '0.00',
        'payment_date' => '15:01:54 Jan 18, 2013 PST',
        'payment_status' => 'Completed',
        'charset' => 'windows-1252',
        'first_name' => 'Anibal',
        'mc_fee' => '4.00',
        'notify_version' => '3.7',
        'custom' => '',
        'payer_status' => 'verified',
        'business' => 'financiero@magnussolution.com.com',
        'quantity' => '1',
        'verify_sign' => 'A9LC3Qajo-H2V8mPq4eIktRPDNMDNt.Rmhgxk0LTN6wGo2lI1cLs',
        'payer_email' => 'amenezes@hotmail.com',
        'txn_id' => '4Y190387AG109562T',
        'payment_type' => 'instant',
        'payer_business_name' => 'eCampus',
        'last_name' => 'de Neto',
        'receiver_email' => 'magnusadilsom@gmail.com',
        'payment_fee' => '4.00',
        'receiver_id' => 'HVUEC4FXXDVDB',
        'txn_type' => 'web_accept',
        'item_name' => 'user, 44767',
        'mc_currency' => 'USD',
        'item_number' => '1458545545-user-110',
        'residence_country' => 'AR',
        'handling_amount' => '0.00',
        'transaction_subject' => 'user, 44767',
        'payment_gross' => '50.00',
        'shipping' => '0.00',
        'ipn_track_id' => 'ed832d58e566b'
        );*/

        Yii::log(print_r($_POST, true), 'error');
        // read the post from PayPal system and add 'cmd'
        $req = 'cmd=_notify-validate';
        foreach ($_POST as $key => $value) {
            $value = urlencode(stripslashes($value));
            $req .= "&$key=$value";
        }
        // post back to PayPal system to validate
        $header = '';
        $header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
        $fp = fsockopen('ssl://www.paypal.com', 443, $errno, $errstr, 30);

        if (!isset($_POST['item_name'])) {
            Yii::log('No POST', 'info');
            exit();
        }

        $modelMethodpay = Methodpay::model()->find("payment_method LIKE 'Paypal'");

        if (!count($modelMethodpay)) {
            exit;
        }

        if (count($modelMethodpay->username) == 0 || $_POST['business'] != $modelMethodpay->username) {
            Yii::log('not allow', 'info');
            exit;
        }

        // assign posted variables to local variables
        $item_name        = $_POST['item_name'];
        $payment_status   = $_POST['payment_status'];
        $amount           = $modelMethodpay->fee == 1 ? $_POST['mc_gross'] - $_POST['mc_fee'] : $_POST['mc_gross'];
        $payment_currency = $_POST['mc_currency'];
        $txn_id           = $_POST['txn_id'];
        $receiver_email   = $_POST['receiver_email'];
        $payer_email      = $_POST['payer_email'];
        $item_number      = $_POST['item_number'];
        $description      = 'Paypal, Nro. de transaccion ' . $txn_id;
        $date             = date('Ymd');

        $identification = Util::getDataFromMethodPay($item_number);
        if (!is_array($identification)) {
            exit;
        }

        $username = $identification['username'];
        $id_user  = $identification['id_user'];

        if ($txn_id == "") {
            exit();
        }

        if (!$fp) {
            Yii::log("EPAYMENT PAYPAL: ERROR,  PAYMENT STARTD BUT NO COMPLETE TRANSACTION ID $txn_id !fp ", 'info');
            write_log(LOGFILE_EPAYMENT, basename(__file__) . ' line:' . __line__ . " EPAYMENT PAYPAL: ERROR,  PAYMENT STARTD BUT NO COMPLETE TRANSACTION ID $txn_id !fp ");
            fclose($fp);
        } else {
            Yii::log('EPAYMENT PAYPAL: ERROR, OK CONTINUA TO ADD CREDIT', 'info');
            fputs($fp, $header . $req);
            while (!feof($fp)) {
                $res = fgets($fp, 1024);
                if (strcmp($res, "VERIFIED") == 0) {
                    if ($_POST['payment_status'] == 'Completed') {
                        Yii::log('PAYMENT VERIFIED', 'info');
                        $modelUser = User::model()->findByPk((int) $id_user);

                        if (count($modelUser) && Refill::model()->countRefill($txn_id, $modelUser->id) == 0) {

                            //checa se o usaurio ja fez pagamentos
                            if ($this->config['global']['paypal_new_user'] == 0) {
                                $modelRefillCount = Refill::model()->count('id_user = :key', array(':key' => $modelUser->id));

                                if ($modelRefillCount == 0) {
                                    $mail_subject = "RECURRING SERVICES : PAYPAL";
                                    $mail_content = "SERVICE NAME = PAYPAL";
                                    $mail_content .= "\n\nCARDID = " . $modelUser->id;
                                    $mail_content .= "\nTotal transation = " . $amount;
                                    $mail_content .= "\nERROR,  PAYMENT STARTD BUT NO COMPLETE, FISRT PAYMENT FOR USER " . $modelUser->username . ",   TRANSACTION ID " . $txn_id . "";

                                    $mail = new Mail(null, $modelUser->id, null, $mail_content, $mail_subject);
                                    $mail->send($this->config['global']['admin_email']);
                                    fclose($fp);
                                    header("HTTP/1.1 200 OK");
                                    exit;
                                } else {
                                    Yii::log($modelUser->id . ' ' . $amount . ' ' . $description . ' ' . $txn_id, 'error');
                                    UserCreditManager::releaseUserCredit($modelUser->id, $amount, $description, 1, $txn_id);
                                }

                            } else {
                                Yii::log($modelUser->id . ' ' . $amount . ' ' . $description . ' ' . $txn_id, 'error');
                                UserCreditManager::releaseUserCredit($modelUser->id, $amount, $description, 1, $txn_id);
                            }
                        } else {
                            Yii::log('USERNAE NOT FOUND', 'info');
                        }

                    }
                } else {
                    Yii::log('NOT VERIFIED', 'info');
                }
            }
            fclose($fp);
            header("HTTP/1.1 200 OK");
        }
    }
}
