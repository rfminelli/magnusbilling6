<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 *
 */

/*
add the cron to check the BDService transaction status
echo "
 * * * * * php /var/www/html/mbilling/cron.php bdservice
" >> /var/spool/cron/root
 */

class TransferToMobileController extends Controller
{
    private $url;
    private $amounts    = 0;
    private $user_cost  = 0;
    private $agent_cost = 0;
    private $login;
    private $token;
    private $currency;
    private $send_credit_id;
    private $user_profit;
    private $test = false;
    private $cost;

    public function init()
    {

        if (isset($_POST['TransferToMobile']['number']) && $_POST['TransferToMobile']['number'] == '5551982464731') {
            $this->test = true;
            echo 'teste';
        }
        $this->instanceModel = new User;
        $this->abstractModel = User::model();
        parent::init();

        if (isset($_POST['TransferToMobile']['method'])) {

            if ($_POST['TransferToMobile']['method'] == 'international') {
                $this->login    = $this->config['global']['fm_transfer_to_username'];
                $this->token    = $this->config['global']['fm_transfer_to_ token'];
                $this->currency = $this->config['global']['fm_transfer_currency'];
                $this->url      = 'https://fm.transfer-to.com/cgi-bin/shop/topup?';
            } else {
                $this->login    = $this->config['global']['BDService_username'];
                $this->token    = $this->config['global']['BDService_token'];
                $this->currency = $this->config['global']['BDService_cambio'];
                $this->url      = $this->config['global']['BDService_url'];
            }
        }
    }

    public function actionRead($asJson = true, $condition = null)
    {

        $modelTransferToMobile = TransferToMobile::model()->findByPk((int) Yii::app()->session['id_user']);
        //if we already request the number info, check if select a valid amount
        if (isset($_POST['TransferToMobile']['amountValues'])) {

            $modelTransferToMobile->method = $_POST['TransferToMobile']['method'];
            $modelTransferToMobile->number = $_POST['TransferToMobile']['number'];
            if ($modelTransferToMobile->method == 'international') {
                $modelTransferToMobile->country  = $_POST['TransferToMobile']['country'];
                $modelTransferToMobile->operator = $_POST['TransferToMobile']['operator'];
            }
            $modelTransferToMobile->amountValues = $_POST['TransferToMobile']['amountValues'];

            if ($modelTransferToMobile->method != 'international') {
                //check min max
                $min = Yii::app()->session['allowedAmount'][0];
                $max = Yii::app()->session['allowedAmount'][1];

                if ($_POST['TransferToMobile']['amountValues'] < $min) {
                    $modelTransferToMobile->addError('amountValues', Yii::t('yii', 'Amount is < then minimal allowed'));

                } else if ($_POST['TransferToMobile']['amountValues'] > $max) {
                    $modelTransferToMobile->addError('amountValues', Yii::t('yii', 'Amount is > then maximum allowed'));
                }

            }

            if (!is_numeric($_POST['TransferToMobile']['amountValues'])) {

                $modelTransferToMobile->addError('amountValues', Yii::t('yii', 'Invalid amount'));

            } elseif (!count($modelTransferToMobile->getErrors())) {

                $this->confirmRefill($modelTransferToMobile);

            }

        }
        //check the number and methods.
        elseif (isset($_POST['TransferToMobile']['method'])) {
            if ($_POST['TransferToMobile']['method'] == '') {
                $modelTransferToMobile->addError('method', Yii::t('yii', 'Please select a method'));
            }

            if ($_POST['TransferToMobile']['number'] == '' || !is_numeric($_POST['TransferToMobile']['number'])
                || strlen($_POST['TransferToMobile']['number']) < 8
                || preg_match('/ /', $_POST['TransferToMobile']['number'])) {
                $modelTransferToMobile->addError('number', Yii::t('yii', 'Number invalid, try again'));
            }

            $modelTransferToMobile->method = $_POST['TransferToMobile']['method'];
            $modelTransferToMobile->number = $_POST['TransferToMobile']['number'];

            if ($_POST['TransferToMobile']['method'] == 'international') {
                //if ok, request number information
                if (!count($modelTransferToMobile->getErrors())) {
                    $this->actionMsisdn_info($modelTransferToMobile);
                }
            }
        }

        $methods = [];
        if ($modelTransferToMobile->transfer_international) {
            $methods["international"] = "International";
        }
        if ($modelTransferToMobile->transfer_flexiload) {
            $methods["flexiload"] = "Flexiload";
        }
        if ($modelTransferToMobile->transfer_bkash) {
            $methods["bkash"] = "Bkash";
        }
        if ($modelTransferToMobile->transfer_dbbl_rocket) {
            $methods["dbbl_rocket"] = "DBBL/Rocket";
        }

        $view = !isset($_POST['TransferToMobile']['method']) || $_POST['TransferToMobile']['method'] == 'international' ? 'transferToMobile' : 'bDService';

        $amountDetails = null;

        if (isset($_POST['TransferToMobile']['method']) && strlen($_POST['TransferToMobile']['method']) && $_POST['TransferToMobile']['method'] != 'international') {

            if ($_POST['TransferToMobile']['method'] == 'flexiload') {
                $values = explode("-", $this->config['global']['BDService_flexiload']);
            } elseif ($_POST['TransferToMobile']['method'] == 'dbbl_rocket') {
                $values = explode("-", $this->config['global']['BDService_dbbl_rocket']);
            } elseif ($_POST['TransferToMobile']['method'] == 'bkash') {
                $values = explode("-", $this->config['global']['BDService_bkash']);
            }
            Yii::app()->session['allowedAmount'] = $values;
            $amountDetails                       = 'Amount (Min: ' . $values[0] . ' BDT, Max: ' . $values[1] . ' BDT)';

        }
        if (count($methods)) {
            $this->render($view, array(
                'modelTransferToMobile' => $modelTransferToMobile,
                'methods'               => $methods,
                'amountDetails'         => $amountDetails,
            ));
        } else {
            echo '<div align=center id="container">';
            echo '<font color=red>Not available any refill method for you</font>';
            echo '</div>';
            exit;
        }

    }

    public function addInDataBase()
    {
        $modelSendCreditSummary            = new SendCreditSummary();
        $modelSendCreditSummary->id_user   = Yii::app()->session['id_user'];
        $modelSendCreditSummary->service   = $_POST['TransferToMobile']['method'];
        $modelSendCreditSummary->number    = $_POST['TransferToMobile']['number'];
        $modelSendCreditSummary->confirmed = $_POST['TransferToMobile']['method'] == 'internacional' ? 1 : 0;
        $modelSendCreditSummary->save();
        $this->send_credit_id = $modelSendCreditSummary->id;
    }

    public function updateDataBase($modelTransferToMobile)
    {

        $profit = 'transfer_' . $_POST['TransferToMobile']['method'] . '_profit';
        SendCreditSummary::model()->updateByPk($this->send_credit_id, array(
            'earned' => $this->user_profit,
            'profit' => $modelTransferToMobile->{$profit},
            'amount' => $this->cost,
        ));
    }

    public function sendActionTransferToMobile($modelTransferToMobile, $action, $product = null)
    {
        $number = $modelTransferToMobile->number;
        $key    = time();
        $md5    = md5($this->login . $this->token . $key);

        if ($action == 'topup') {
            $action .= '&msisdn=$number&delivered_amount_info=1&product=' . $product;
        }

        $url               = $this->url . "login=" . $this->login . "&key=$key&md5=$md5&destination_msisdn=$number&action=" . $action;
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer"      => false,
                "verify_peer_name" => false,
            ),
        );

        if ($this->test == true && preg_match('/topup/', $action)) {
            return $result = 'transactionid=629609814 msisdn=number destination_msisdn=5551981768534 country=Brazil countryid=691 operator=TIM Brazil operatorid=610 reference_operator= originating_currency=EUR destination_currency=BRL product_requested=10 actual_product_sent=10 wholesale_price=3.50 retail_price=3.50 balance=764.12 sms_sent=yes sms= cid1= cid2= cid3= local_info_value=10 local_info_amount=10 local_info_currency=BRL authentication_key=1519755909 error_code=0 error_txt=Transaction successful';

        } else {
            if (!$result = @file_get_contents($url, false, stream_context_create($arrContextOptions))) {
                $result = '';
            }
        }

        return $result;
    }
    public function sendActionBDService($modelTransferToMobile)
    {

        $type = $modelTransferToMobile->method == 'dbbl_rocket' ? 'DBBL' : $modelTransferToMobile->method;

        $url = $this->url . "/ezzeapi/request/" . $type . "?number=" . $modelTransferToMobile->number . "&amount=" . $modelTransferToMobile->amountValues . "&type=1&id=" . $this->send_credit_id . "&user=" . $this->login . "&key=" . $this->token;

        if ($this->test == true) {
            $result = 'SUCCESS';
        } else {
            if (!$result = @file_get_contents($url, false, stream_context_create($arrContextOptions))) {
                $result = '';
            }
        }

        return $result;
    }

    public function calculateCost($modelTransferToMobile, $product = 0)
    {
        if ($this->test == true) {
            echo 'cost=' . $this->cost . ' - prodict=' . $product . "<br>";
        }

        $methosProfit = 'transfer_' . $_POST['TransferToMobile']['method'] . '_profit';

        if ($modelTransferToMobile->credit + $modelTransferToMobile->creditlimit < $this->cost) {

            echo '<div id="container"><div id="form"> <div id="box-4"><div class="control-group">';
            echo '<form action="" id="form1" method="POST">';
            echo '<font color=red>ERROR:You no have enough credit to transfer</font>';
            echo '</form>';
            echo '</div></div></div></div>';
            exit;
        }

        $user_profit = $modelTransferToMobile->{$methosProfit};

        $this->user_cost = $this->cost - ($this->cost * ($user_profit / 100));

        $this->user_profit = $this->cost * ($user_profit / 100);

        if ($this->test == true) {
            echo 'cost=' . $this->cost . ', user_profit= ' . $this->cost * ($this->user_profit / 100) . ' user_profit=' . $this->user_profit . "<BR>";
        }

        if ($modelTransferToMobile->id_user > 1) {

            //check if agent have credit

            $modelAgent = User::model()->findByPk($modelTransferToMobile->id_user);

            if ($modelAgent->credit + $modelAgent->creditlimit < $this->cost) {

                echo '<div id="container"><div id="form"> <div id="box-4"><div class="control-group">';
                echo '<form action="" id="form1" method="POST">';
                echo '<font color=red>ERROR:Your Agent no have enough credit to transfer</font>';
                echo '</form>';
                echo '</div></div></div></div>';
                exit;

            }

            $modelAgent  = User::model()->findByPk($modelTransferToMobile->id_user);
            $agentProfit = $modelAgent->{$methosProfit};

            if ($_POST['TransferToMobile']['method'] == 'international') {
                //get the admin sell price.
                $sellAdmin = Yii::app()->session['sellAdmin'][$product];

                if ($this->test == true) {
                    echo '$sellAdmin = ' . $sellAdmin . "<br>";
                    echo '$agentProfit = ' . $agentProfit . "<br>";
                }

                //remove the agent comission
                $this->agent_cost = $sellAdmin - ($sellAdmin * ($agentProfit / 100));

                if ($this->test == true) {
                    echo '$this->agent_cost = ' . $this->agent_cost . "<br>";
                }

            } else {
                $this->agent_cost = $this->cost - ($this->cost * ($agentProfit / 100));
            }

        }
    }

    public function confirmRefill($modelTransferToMobile)
    {

        if ($_POST['TransferToMobile']['method'] == 'international') {
            $product    = $_POST['TransferToMobile']['amountValues']; //is the amout to refill
            $this->cost = Yii::app()->session['amounts'][$product];
            $this->cost = explode($this->currency, $this->cost);
            $this->cost = $this->cost[1];

        } else {

            $rateinitial = $modelTransferToMobile->transfer_bdservice_rate / 100 + 1;
            //cost to send to provider selected value + admin rate * exchange
            $this->cost = $_POST['TransferToMobile']['amountValues'] * $rateinitial * $this->config['global']['BDService_cambio'];
            $product    = 0;

        }

        $this->calculateCost($modelTransferToMobile, $product);

        if ($this->test == true) {
            echo "REMOVE " . $this->user_cost . " from user " . $modelTransferToMobile->username;
        }

        $this->addInDataBase();
        if ($modelTransferToMobile->method == 'international') {
            $result = $this->sendActionTransferToMobile($modelTransferToMobile, 'topup', $product);
        } else {
            $result = $this->sendActionBDService($modelTransferToMobile);
        }

        $this->updateDataBase($modelTransferToMobile);

        $this->checkResult($modelTransferToMobile, $result);

    }

    public function checkResult($modelTransferToMobile, $result)
    {

        if ($modelTransferToMobile->method == 'international') {
            $result = explode("error_txt=", $result);

            if (preg_match("/Transaction successful/", $result[1])) {
                $this->releaseCredit($modelTransferToMobile, $result);
                exit;
            } else {
                echo '<div align=center id="container">';
                echo '<font color=red>ERROR: ' . $result[1] . '</font>';
                echo '</div>';
            }

        } else {

            if (strlen($result) < 1) {
                echo '<div align=center id="container">';
                echo "<font color=red>INVALID REQUEST, CONTACT ADMIN</font>";
                echo '</div>';
            } else if (preg_match("/ERROR|error/", $result)) {
                echo '<div align=center id="container">';
                echo "<font color=red>" . $result . "</font>";
                echo '</div>';
            } elseif (preg_match("/SUCCESS/", strtoupper($result))) {
                $this->releaseCredit($modelTransferToMobile, $result);
                exit;
            }

        }
    }

    public function releaseCredit($modelTransferToMobile, $result)
    {

        $msg = $modelTransferToMobile->method == 'international' ? $result[1] : $result;
        echo '<div align=center id="container">';
        echo '<font color=green>Success: ' . $msg . '</font>';
        echo '</div>';

        if ($modelTransferToMobile->method == 'international') {
            User::model()->updateByPk(Yii::app()->session['id_user'],
                array(
                    'credit' => new CDbExpression('credit - ' . $this->user_cost),
                )
            );
        }
        $description = 'Send Credit to ' . $modelTransferToMobile->number . ' via ' . $modelTransferToMobile->method;

        $payment = $modelTransferToMobile->method == 'international' ? 1 : 0;
        $values  = ":id_user, :costUser, :description, $payment";
        $field   = 'id_user,credit,description,payment';

        if ($modelTransferToMobile->method != 'international') {
            $values .= "," . $this->send_credit_id;
            $field .= ',invoice_number';
        }

        $sql     = "INSERT INTO pkg_refill ($field) VALUES ($values)";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":id_user", Yii::app()->session['id_user'], PDO::PARAM_INT);
        $command->bindValue(":costUser", $this->user_cost * -1, PDO::PARAM_STR);
        $command->bindValue(":description", $description, PDO::PARAM_STR);
        $command->execute();

        if ($this->test == true) {
            echo $sql . "<br>";
        }

        if ($modelTransferToMobile->id_user > 1) {
            if ($modelTransferToMobile->method == 'international') {
                $sql     = "UPDATE  pkg_user SET credit = credit - :costAgent WHERE id = :id";
                $command = Yii::app()->db->createCommand($sql);
                $command->bindValue(":id", $modelTransferToMobile->id_user, PDO::PARAM_INT);
                $command->bindValue(":costAgent", $this->agent_cost, PDO::PARAM_STR);
                $command->execute();
            }

            $payment = $modelTransferToMobile->method == 'international' ? 1 : 0;
            $values  = ":id_user, :costAgent, :description, $payment";
            $field   = 'id_user,credit,description,payment';

            if ($modelTransferToMobile->method != 'international') {
                $values .= ",$this->send_credit_id";
                $field .= ',invoice_number';
            }

            if ($this->test == true) {
                echo $sql . "<br>";
            }

            $sql     = "INSERT INTO pkg_refill ($field) VALUES ($values)";
            $command = Yii::app()->db->createCommand($sql);
            $command->bindValue(":id_user", $modelTransferToMobile->id_user, PDO::PARAM_INT);
            $command->bindValue(":costAgent", $this->agent_cost * -1, PDO::PARAM_STR);
            $command->bindValue(":description", $description, PDO::PARAM_STR);
            $command->execute();

            if ($this->test == true) {
                echo $sql . "<br>";
            }

        }
    }

    public function actionMsisdn_info($modelTransferToMobile)
    {

        if (isset($_POST['TransferToMobile']['number'])) {

            $number = $_POST['TransferToMobile']['number'];

            $number = preg_match("/\+/", $number) ? $number : '+' . $number;

            if (isset($this->config['global']['fm_transfer_to_username'])) {

                $timeToCall = date('Y-m-d H:i', mktime(date('H'), date('i') - 10, date('s'), date('m'), date('d'), date('Y')));

                $sql     = "SELECT * FROM pkg_refill WHERE description LIKE :description AND date BETWEEN '$timeToCall' AND  NOW() AND payment = 1 AND id_user =" . Yii::app()->session['id_user'];
                $command = Yii::app()->db->createCommand($sql);
                $command->bindValue(":description", "%" . $number . "%", PDO::PARAM_STR);
                $result = $command->queryAll();

                if (count($result) > 0) {
                    echo '<div align=center id="container">';
                    echo "<font color=red>You already send credit to this number. Wait minimal 10 minutes to new recharge</font>";
                    echo '</div>';
                    exit;
                }

                $result = $this->sendActionTransferToMobile($modelTransferToMobile, 'msisdn_info');

                if (preg_match("/Transaction successful/", $result)) {

                    $result = explode("\n", $result);

                    /*
                    Array
                    (
                    [0] => country=Brazil
                    [1] => countryid=691
                    [2] => operator=TIM Brazil
                    [3] => operatorid=610
                    [4] => connection_status=100
                    [5] => destination_msisdn=5551982464731
                    [6] => destination_currency=BRL
                    [7] => product_list=10,15,20,30,35,40,50,100
                    [8] => retail_price_list=3.50,5.20,6.90,10.40,12.10,12.90,17.30,34.50
                    [9] => wholesale_price_list=2.77,4.15,5.48,8.28,12.72,10.93,13.81,27.32
                    [10] => authentication_key=1519327737
                    [11] => error_code=0
                    [12] => error_txt=Transaction successful
                    [13] =>
                    )
                     */

                    $operatorId       = explode("=", $result[3]);
                    $operatorId       = trim($operatorId[1]);
                    $rateinitialAdmin = 1;
                    if ($modelTransferToMobile->id_user > 1) {
                        $modelRate = RateAgent::model()->find(array(
                            'with'   => array('idPrefix' => array('condition' => "idPrefix.prefix = :key")),
                            'params' => array(':key' => '888' . $operatorId),
                        ));

                        $modelRateAdmin = Rate::model()->find(array(
                            'with'   => array('idPrefix' => array('condition' => "idPrefix.prefix = :key")),
                            'params' => array(':key' => '888' . $operatorId),
                        ));

                        $rateinitialAdmin = isset($modelRate->modelRateAdmin) ? $modelRate->modelRateAdmin / 100 + 1 : 1;

                    } else {
                        $modelRate = Rate::model()->find(array(
                            'with'   => array('idPrefix' => array('condition' => "idPrefix.prefix = :key")),
                            'params' => array(':key' => '888' . $operatorId),
                        ));
                    }
                    if ($this->test == true) {
                        echo '$operatorId = ' . $operatorId . "<br>";
                    }

                    $rateinitial = isset($modelRate->rateinitial) ? $modelRate->rateinitial / 100 + 1 : 1;
                    //echo "admin profit " . $modelRate->rateinitial . "% <br>";
                    $product_list      = explode(",", substr($result[7], 13));
                    $retail_price_list = explode(",", substr($result[8], 18));

                    $local_currency = explode("=", $result[6]);
                    $local_currency = trim($local_currency[1]);

                    $country = explode("=", $result[0]);
                    $country = trim($country[1]);

                    $operator = explode("=", $result[2]);
                    $operator = trim($operator[1]);

                    $values = $sellAdmin = array();
                    $i      = 0;

                    foreach ($product_list as $key => $product) {
                        if ($this->test == true) {
                            echo $local_currency . ' ' . trim($product) . ' = ' . $this->currency . ' ' . trim($retail_price_list[$i] * $rateinitial) . " -> admin price = " . trim($retail_price_list[$i] * $rateinitialAdmin) . "<BR>";

                        }
                        $values[trim($product)]    = $local_currency . ' ' . trim($product) . ' = ' . $this->currency . ' ' . trim($retail_price_list[$i] * $rateinitial);
                        $sellAdmin[trim($product)] = trim($retail_price_list[$i] * $rateinitialAdmin);
                        $i++;
                    }

                    Yii::app()->session['amounts']   = $values;
                    Yii::app()->session['sellAdmin'] = $sellAdmin;
                    $modelTransferToMobile->country  = $country;
                    $modelTransferToMobile->operator = $operator;

                    return $modelTransferToMobile;

                } else {
                    $result = explode("error_txt=", $result);
                    echo '<div align=center id="container">';
                    echo "<font color=red>" . $result[1] . "</font>";
                    echo '</div>';
                    exit;
                }

            } else {
                echo 'Service inactive';
            }
        }
    }

    public function actionPrintRefill()
    {

        if (isset($_GET['id'])) {
            $config    = LoadConfig::getConfig();
            $id_refill = $_GET['id'];

            $modelRefill = Refill::model()->findByPk((int) $id_refill, 'id_user = :key', array(':key' => Yii::app()->session['id_user']));

            echo $config['global']['fm_transfer_print_header'] . "<br>";

            echo $modelRefill->idUser->company_name . "<br>";

            echo "Trx ID: " . $modelRefill->id . "<br>";

            echo $modelRefill->date . "<br>";

            $number = explode(" ", $modelRefill->description);
            echo "Mobile No.: " . $number[3] . "<br>";
            $amount = number_format($modelRefill->credit, 2) * -1;
            echo "Amount: <input type=text' style='text-align: right;' size='5' value='$amount'> <br><br>";

            echo $config['global']['fm_transfer_print_footer'] . "<br><br>";

            echo '<td><a href="javascript:window.print()">Print</a></td>';
        } else {
            echo ' Invalid reffil';
        }
    }
}
