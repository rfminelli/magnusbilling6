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
    private $showprice;
    private $local_currency;
    public $modelTransferToMobile = array();

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

        $this->modelTransferToMobile = TransferToMobile::model()->findByPk((int) Yii::app()->session['id_user']);
    }

    public function actionRead($asJson = true, $condition = null)
    {

        //if we already request the number info, check if select a valid amount
        if (isset($_POST['TransferToMobile']['amountValues'])) {

            $this->modelTransferToMobile->method = $_POST['TransferToMobile']['method'];
            $this->modelTransferToMobile->number = $_POST['TransferToMobile']['number'];
            if ($this->modelTransferToMobile->method == 'international') {
                $this->modelTransferToMobile->country  = $_POST['TransferToMobile']['country'];
                $this->modelTransferToMobile->operator = $_POST['TransferToMobile']['operator'];
            }
            $this->modelTransferToMobile->amountValues = $_POST['TransferToMobile']['amountValues'];

            if ($this->modelTransferToMobile->method != 'international') {
                //check min max
                $min = Yii::app()->session['allowedAmount'][0];
                $max = Yii::app()->session['allowedAmount'][1];

                if ($_POST['TransferToMobile']['amountValues'] < $min) {
                    $this->modelTransferToMobile->addError('amountValues', Yii::t('yii', 'Amount is < then minimal allowed'));

                } else if ($_POST['TransferToMobile']['amountValues'] > $max) {
                    $this->modelTransferToMobile->addError('amountValues', Yii::t('yii', 'Amount is > then maximum allowed'));
                }

            }

            if (!is_numeric($_POST['TransferToMobile']['amountValues'])) {

                $this->modelTransferToMobile->addError('amountValues', Yii::t('yii', 'Invalid amount'));

            } elseif (!count($this->modelTransferToMobile->getErrors())) {

                $this->confirmRefill();

            }

        }
        //check the number and methods.
        elseif (isset($_POST['TransferToMobile']['method'])) {
            if ($_POST['TransferToMobile']['method'] == '') {
                $this->modelTransferToMobile->addError('method', Yii::t('yii', 'Please select a method'));
            }

            if ($_POST['TransferToMobile']['number'] == '' || !is_numeric($_POST['TransferToMobile']['number'])
                || strlen($_POST['TransferToMobile']['number']) < 8
                || preg_match('/ /', $_POST['TransferToMobile']['number'])) {
                $this->modelTransferToMobile->addError('number', Yii::t('yii', 'Number invalid, try again'));
            }

            $this->modelTransferToMobile->method = $_POST['TransferToMobile']['method'];
            $this->modelTransferToMobile->number = $_POST['TransferToMobile']['number'];

            if ($_POST['TransferToMobile']['method'] == 'international') {
                //if ok, request number information
                if (!count($this->modelTransferToMobile->getErrors())) {
                    $this->actionMsisdn_info();
                }
            }
        }

        $methods = [];
        if ($this->modelTransferToMobile->transfer_international) {
            $methods["international"] = "International";
        }
        if ($this->modelTransferToMobile->transfer_flexiload) {
            $methods["flexiload"] = "Flexiload";
        }
        if ($this->modelTransferToMobile->transfer_bkash) {
            $methods["bkash"] = "Bkash";
        }
        if ($this->modelTransferToMobile->transfer_dbbl_rocket) {
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
                'modelTransferToMobile' => $this->modelTransferToMobile,
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
        $modelSendCreditSummary->confirmed = $_POST['TransferToMobile']['method'] == 'international' ? 1 : 0;
        $modelSendCreditSummary->cost      = $this->user_cost;
        $modelSendCreditSummary->save();
        $this->send_credit_id = $modelSendCreditSummary->id;
    }

    public function updateDataBase()
    {

        $profit = 'transfer_' . $_POST['TransferToMobile']['method'] . '_profit';
        SendCreditSummary::model()->updateByPk($this->send_credit_id, array(
            'profit' => $this->modelTransferToMobile->{$profit},
            'amount' => $this->cost,
            'sell'   => $this->showprice,
            'earned' => $this->showprice - $this->user_cost,
        ));
    }

    public function sendActionTransferToMobile($action, $product = null)
    {

        $number = $this->modelTransferToMobile->number;
        $key    = time();
        $md5    = md5($this->login . $this->token . $key);

        if ($action == 'topup') {
            $modelSendCreditProducts = SendCreditProducts::model()->find(array(
                'condition' => 'operator_name = :key',
                'params'    => array(':key' => $this->modelTransferToMobile->operator),
            ));
            $this->url = "https://airtime.transferto.com/cgi-bin/shop/topup?";
            $action .= '&msisdn=number&delivered_amount_info=1&product=' . $product . '&operatorid=' . $modelSendCreditProducts->operator_id . '&sms_sent=yes';
        }

        $url               = $this->url . "login=" . $this->login . "&key=$key&md5=$md5&destination_msisdn=$number&action=" . $action;
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer"      => false,
                "verify_peer_name" => false,
            ),
        );

        if ($this->test == true && preg_match('/topup/', $action)) {
            return $result = 'transactionid=662680426
msisdn=number
destination_msisdn=5551982464731
country=Brazil
countryid=691
operator=Vivo Brazil
operatorid=708
reference_operator=55674
originating_currency=EUR
destination_currency=BRL
product_requested=7
actual_product_sent=7
wholesale_price=2.40
retail_price=2.40
balance=1320.90
sms_sent=yes
sms=
cid1=
cid2=
cid3=
local_info_value=7
local_info_amount=7
local_info_currency=BRL
authentication_key=1529417078
error_code=0
error_txt=Transaction successful';
        } else {

            if (!$result = @file_get_contents($url, false, stream_context_create($arrContextOptions))) {
                $result = '';
            }
        }

        if ($this->test == true) {
            echo $url . "<br>";
        }

        return $result;
    }
    public function sendActionBDService()
    {

        $type = $this->modelTransferToMobile->method == 'dbbl_rocket' ? 'DBBL' : $this->modelTransferToMobile->method;

        $url = $this->url . "/ezzeapi/request/" . $type . "?number=" . $this->modelTransferToMobile->number . "&amount=" . $this->modelTransferToMobile->amountValues . "&type=1&id=" . $this->send_credit_id . "&user=" . $this->login . "&key=" . $this->token;

        if ($this->test == true) {
            $result = 'SUCCESS';
        } else {
            if (!$result = @file_get_contents($url, false, stream_context_create($arrContextOptions))) {
                $result = '';
            }
        }

        return $result;
    }

    public function calculateCost($product = 0)
    {
        if ($this->test == true) {
            echo 'cost=' . $this->cost . ' - prodict=' . $product . "<br>";
        }

        $methosProfit = 'transfer_' . $_POST['TransferToMobile']['method'] . '_profit';

        if ($this->modelTransferToMobile->credit + $this->modelTransferToMobile->creditlimit < $this->cost) {

            echo '<div id="container"><div id="form"> <div id="box-4"><div class="control-group">';
            echo '<form action="" id="form1" method="POST">';
            echo '<font color=red>ERROR:You no have enough credit to transfer</font>';
            echo '</form>';
            echo '</div></div></div></div>';
            exit;
        }

        $user_profit = $this->modelTransferToMobile->{$methosProfit};

        $this->user_cost = $this->cost - ($this->cost * ($user_profit / 100));

        $this->user_profit = $this->cost * ($user_profit / 100);

        if ($this->test == true) {
            echo 'cost=' . $this->cost . ', user_profit= ' . $this->cost * ($this->user_profit / 100) . ' user_profit=' . $this->user_profit . "<BR>";
        }

        if ($this->modelTransferToMobile->id_user > 1) {

            //check if agent have credit

            $modelAgent = User::model()->findByPk($this->modelTransferToMobile->id_user);

            if ($modelAgent->credit + $modelAgent->creditlimit < $this->cost) {

                echo '<div id="container"><div id="form"> <div id="box-4"><div class="control-group">';
                echo '<form action="" id="form1" method="POST">';
                echo '<font color=red>ERROR:Your Agent no have enough credit to transfer</font>';
                echo '</form>';
                echo '</div></div></div></div>';
                exit;

            }

            $modelAgent  = User::model()->findByPk($this->modelTransferToMobile->id_user);
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

    public function actionGetBuyingPrice()
    {

        if ($_GET['method'] == 'international') {
            $currency = $this->config['global']['fm_transfer_currency'];
        } else {
            $currency = $this->config['global']['BDService_cambio'];
        }

        if ($_GET['method'] == 'international') {
            Yii::app()->session['operatorId'] = $_GET['operatorid'];
            $modelSendCreditProducts          = SendCreditProducts::model()->find(array(
                'condition' => 'operator_id = :key AND product = :key1',
                'params'    => array(
                    ':key'  => Yii::app()->session['operatorId'],
                    ':key1' => $_GET['amountValues'],
                ),
            ));
            $cost = $modelSendCreditProducts->retail_price;
        } else {

            $rateinitial = $this->modelTransferToMobile->transfer_bdservice_rate / 100 + 1;
            //cost to send to provider selected value + admin rate * exchange
            $cost     = $_GET['amountValues'] * $rateinitial * $this->config['global']['BDService_cambio'];
            $product  = 0;
            $currency = '€';
        }

        $methosProfit = 'transfer_' . $_GET['method'] . '_profit';
        $user_profit  = $this->modelTransferToMobile->{$methosProfit};

        $user_cost = $cost - ($cost * ($user_profit / 100));
        echo $currency . ' ' . number_format($user_cost, 2);

    }

    public function confirmRefill()
    {
        /*print_r($_POST['TransferToMobile']);
        exit;*/

        if ($_POST['TransferToMobile']['method'] == 'international') {
            $product = $_POST['TransferToMobile']['amountValues']; //is the amout to refill

            $modelSendCreditProducts = SendCreditProducts::model()->find(array(
                'condition' => 'operator_id = :key AND product = :key1',
                'params'    => array(
                    ':key'  => Yii::app()->session['operatorId'],
                    ':key1' => $product,
                ),
            ));
            $this->cost           = $modelSendCreditProducts->retail_price;
            $this->local_currency = $modelSendCreditProducts->currency_dest;
        } else {

            $rateinitial = $this->modelTransferToMobile->transfer_bdservice_rate / 100 + 1;
            //cost to send to provider selected value + admin rate * exchange
            $this->cost = $_POST['TransferToMobile']['amountValues'] * $rateinitial * $this->config['global']['BDService_cambio'];
            $product    = 0;

        }

        $this->calculateCost($product);

        if ($this->test == true) {
            echo "REMOVE " . $this->user_cost . " from user " . $this->modelTransferToMobile->username;
        }

        $this->addInDataBase();
        if ($this->modelTransferToMobile->method == 'international') {
            $result = $this->sendActionTransferToMobile('topup', $product);
        } else {
            $result = $this->sendActionBDService($this->modelTransferToMobile);
        }

        $this->checkResult($result);

        $this->updateDataBase();
        exit;

    }

    public function checkResult($result)
    {

        if ($this->modelTransferToMobile->method == 'international') {

            $result = explode("error_txt=", $result);

            if (preg_match("/Transaction successful/", $result[1])) {
                $this->releaseCredit($result);

            } else {
                echo '<div align=center id="container">';
                echo '<font color=red>ERROR: ' . $result[1] . '</font>';
                echo '</div>';
                exit;
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
                $this->releaseCredit($result);

            }

        }
    }

    public function releaseCredit($result)
    {

        $msg = $this->modelTransferToMobile->method == 'international' ? $result[1] : $result;
        echo '<div align=center id="container">';
        echo '<font color=green>Success: ' . $msg . '</font>';
        echo '</div>';

        if ($this->modelTransferToMobile->method == 'international') {
            User::model()->updateByPk(Yii::app()->session['id_user'],
                array(
                    'credit' => new CDbExpression('credit - ' . $this->user_cost),
                )
            );
        }

        if ($this->test == true) {
            echo 'cost=' . $this->cost . '. user_cost=' . $this->user_cost . "<br>";
            echo $this->modelTransferToMobile->transfer_show_selling_price . "<br>";
        }

        $argument = $this->modelTransferToMobile->transfer_show_selling_price;
        if ($argument < 10) {
            $fee = '1.0' . $argument;
        } else {
            $fee = '1.' . $argument;
        }

        if ($this->test == true) {
            echo $fee . "<br>";
            echo $this->modelTransferToMobile->transfer_show_selling_price;
        }

        $this->showprice = number_format($this->cost * $fee, 2);

        $result = explode("reference_operator=", $result[0]);
        $result = explode("\n", $result[1]);

        $description = 'Send Credit ' . $this->local_currency . ' ' . $_POST['TransferToMobile']['amountValues'] . ' to ' . $this->modelTransferToMobile->number . ' via ' . $this->modelTransferToMobile->method . ' at ' . $this->showprice . '. ref: ' . $result[0];

        if ($this->test == true) {
            echo $description;
        }

        $payment = $this->modelTransferToMobile->method == 'international' ? 1 : 0;
        $values  = ":id_user, :costUser, :description, $payment";
        $field   = 'id_user,credit,description,payment';

        // if ($this->modelTransferToMobile->method != 'international') {
        $values .= "," . $this->send_credit_id;
        $field .= ',invoice_number';
        // }

        $sql     = "INSERT INTO pkg_refill ($field) VALUES ($values)";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":id_user", Yii::app()->session['id_user'], PDO::PARAM_INT);
        $command->bindValue(":costUser", $this->user_cost * -1, PDO::PARAM_STR);
        $command->bindValue(":description", $description, PDO::PARAM_STR);
        $command->execute();

        if ($this->test == true) {
            echo $sql . "<br>";
        }

        if ($this->modelTransferToMobile->id_user > 1) {
            if ($this->modelTransferToMobile->method == 'international') {
                $sql     = "UPDATE  pkg_user SET credit = credit - :costAgent WHERE id = :id";
                $command = Yii::app()->db->createCommand($sql);
                $command->bindValue(":id", $this->modelTransferToMobile->id_user, PDO::PARAM_INT);
                $command->bindValue(":costAgent", $this->agent_cost, PDO::PARAM_STR);
                $command->execute();
            }

            $payment = $this->modelTransferToMobile->method == 'international' ? 1 : 0;
            $values  = ":id_user, :costAgent, :description, $payment";
            $field   = 'id_user,credit,description,payment';

            //if ($modelTransferToMobile->method != 'international') {
            $values .= ",$this->send_credit_id";
            $field .= ',invoice_number';
            //}

            if ($this->test == true) {
                echo $sql . "<br>";
            }

            $sql     = "INSERT INTO pkg_refill ($field) VALUES ($values)";
            $command = Yii::app()->db->createCommand($sql);
            $command->bindValue(":id_user", $this->modelTransferToMobile->id_user, PDO::PARAM_INT);
            $command->bindValue(":costAgent", $this->agent_cost * -1, PDO::PARAM_STR);
            $command->bindValue(":description", $description, PDO::PARAM_STR);
            $command->execute();

            if ($this->test == true) {
                echo $sql . "<br>";
            }

        }
    }

    public function actionMsisdn_info()
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

                $result = $this->sendActionTransferToMobile('msisdn_info');

                if (preg_match("/Transaction successful/", $result)) {

                    $result = explode("\n", $result);

                    $operatorId = explode("=", $result[3]);
                    $operatorId = trim($operatorId[1]);

                    $modelSendCreditProducts = SendCreditProducts::model()->findAll(' operator_id = ' . $operatorId,
                        array(
                            ':key1' => $operatorId,
                        ));

                    $sql     = "SELECT sell_price FROM  pkg_send_credit_rates WHERE id_user = :key AND operator_id = :key1";
                    $command = Yii::app()->db->createCommand($sql);
                    $command->bindValue(":key", Yii::app()->session['id_user'], PDO::PARAM_INT);
                    $command->bindValue(":key1", $operatorId, PDO::PARAM_INT);
                    $resultRates = $command->queryAll();

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

                    $values = array();
                    $i      = 0;

                    foreach ($modelSendCreditProducts as $key => $product) {

                        if ($this->test == true) {
                            echo $product->currency_dest . ' ' . $product->product . ' = ' . $product->currency_orig . ' ' . trim($resultRates[$i]['sell_price']) . "<BR>";

                        }
                        $values[trim($product->product)] = $product->currency_dest . ' ' . trim($product->product) . ' = ' . $product->currency_orig . ' ' . trim($resultRates[$i]['sell_price']);
                        $i++;
                    }

                    Yii::app()->session['amounts']    = $values;
                    Yii::app()->session['operatorId'] = $operatorId;

                    $this->modelTransferToMobile->country  = $modelSendCreditProducts[0]->country;
                    $this->modelTransferToMobile->operator = $modelSendCreditProducts[0]->operator_name;

                    return $this->modelTransferToMobile;

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

    public function actionGetProducts()
    {

        $modelSendCreditProducts = SendCreditProducts::model()->findAll(array(
            'condition' => 'operator_name = :key',
            'params'    => array(':key' => $_GET['operator']),
        ));

        $operatorId = $modelSendCreditProducts[0]->operator_id;

        $sql     = "SELECT sell_price FROM  pkg_send_credit_rates WHERE id_user = :key AND operator_id = :key1";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":key", Yii::app()->session['id_user'], PDO::PARAM_INT);
        $command->bindValue(":key1", $operatorId, PDO::PARAM_INT);
        $resultRates = $command->queryAll();

        $values = array();
        $i      = 0;
        echo '<select onchange="showPrice(' . $this->modelTransferToMobile->transfer_show_selling_price . ')" id="amountfiel" name="TransferToMobile[amountValues]">';
        echo '<option value="">Select the amount</option>';
        foreach ($modelSendCreditProducts as $key => $product) {

            echo '<option value="' . $product->product . '">' . $product->currency_dest . ' ' . $product->product . ' = ' . $product->currency_orig . ' ' . $resultRates[$i]['sell_price'] . '</option>';

            $i++;
        }

        echo '</select>';

        Yii::app()->session['amounts']    = $values;
        Yii::app()->session['operatorId'] = $operatorId;
    }
}
