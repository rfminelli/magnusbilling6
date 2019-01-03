<?php
/**
 * Acoes do modulo "Sms".
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2018 MagnusSolution. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 17/08/2017
 */

class SmsController extends Controller
{
    public $attributeOrder = 'date DESC';
    public $extraValues    = array('idUser' => 'username');

    public function init()
    {
        $this->instanceModel = new Sms;
        $this->abstractModel = Sms::model();
        $this->titleReport   = 'Sms';
        parent::init();
    }

    /*
    Url for Send SMSr http://ip/mbilling/index.php/sms/send?username=user&password=MD5(pass)&number=55dddn&text=sms-text.
     */
    public function actionSend()
    {
        SqlInject::sanitize($_GET);
        if (!isset($_GET['number']) || !isset($_GET['text']) || !isset($_GET['username']) || !isset($_GET['password'])) {
            exit('invalid data');
        } elseif (!is_numeric($_GET['number'])) {
            exit('invalid non-numeric number');
        } else if (strlen($_GET['number']) > 15) {
            exit('invalid number');
        } else if (strlen($_GET['text']) > 140) {
            exit('invalid number');
        } else if (strlen($_GET['username']) < 4) {
            exit('invalid user');
        } else if (strlen($_GET['password']) < 5) {
            exit('invalid user');
        }

        $modelSip = $this->remoteLogin($_GET['username'], $_GET['password']);

        if (!count($modelSip)) {
            exit('invalid user');
        }

        $result = SmsSend::send($modelSip->idUser, $_GET['number'], $_GET['text']);

        echo $result['success'] ? 'Sent' : 'Error' . ' ' . $result['msg'];

    }

    public function actionSendPost()
    {
        /*
        <?php
        $url  = 'http://your_server_ip/mbilling/index.php/sms/sendPost';
        $data = array(
        'username'   => 'username', //username
        'api'        => MD5('your_password'), //MD5 password
        'number'     => '573112370000', //numbers to send sms, ',' separated
        'text'       => 'SMS API test', //SMS text
        );

        $options = array(
        'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
        ),
        );
        $context = stream_context_create($options);
        $result  = json_decode(file_get_contents($url, false, $context));
        if ($result->success == 1) {
        print 'Sent ' . $result->totalSmsSent . ' SMS successfully';
        } else {
        print 'error!!';
        print_r($result);
        }

         */
        SqlInject::sanitize($_POST);
        if (!isset($_POST['number']) || !isset($_POST['text']) || !isset($_POST['username']) || !isset($_POST['api'])) {
            exit('invalid data');
        } else if (strlen($_POST['text']) > 140) {
            exit('invalid number');
        } else if (strlen($_POST['username']) < 4) {
            exit('invalid user');
        } else if (strlen($_POST['api']) < 5) {
            exit('invalid user');
        }

        $modelSip = $this->remoteLogin($_POST['username'], $_POST['api']);

        if (!count($modelSip)) {
            exit('invalid user');
        }

        $numbers = explode(',', $_POST['number']);
        $i       = 0;
        foreach ($numbers as $key => $number) {
            $result = SmsSend::send($modelSip->idUser, $number, $_POST['text']);
            if ($result['success'] != 'Sent') {
                $result['errornumber'] = $number;
                break;
            } else {
                $i++;
            }
        }

        $result['totalSmsSent'] = $i;
        echo json_encode($result);
    }

    public function actionSave()
    {
        $values = $this->getAttributesRequest();

        if (Yii::app()->session['isClient']) {
            $values['id_user'] = Yii::app()->session['id_user'];
        }

        $modelUser = User::model()->findByPk((int) $values['id_user']);

        $res = SmsSend::send($modelUser, $values['telephone'], $values['sms']);

        echo json_encode($res);
    }
}
