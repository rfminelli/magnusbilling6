<?php
/**
 * Acoes do modulo "CallOnLine".
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author  Adilson Leffa Magnus.
 * @copyright   Todos os direitos reservados.
 * ###################################
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 19/09/2012
 */

class CallOnLineController extends Controller
{
    public $attributeOrder = 't.duration DESC, status ASC';
    public $extraValues    = array('idUser' => 'username,credit');

    public $fieldsInvisibleClient = array(
        'canal',
        'tronco',
    );

    public $fieldsInvisibleAgent = array(
        'canal',
        'tronco',
    );

    public function init()
    {
        $this->instanceModel = new CallOnLine;
        $this->abstractModel = CallOnLine::model();
        $this->titleReport   = Yii::t('yii', 'CallOnLine');

        parent::init();

        if (Yii::app()->getSession()->get('isAgent')) {
            $this->filterByUser        = true;
            $this->defaultFilterByUser = 'b.id_user';
            $this->join                = 'JOIN pkg_user b ON t.id_user = b.id';
        }
    }

    public function actionRead($asJson = true, $condition = null)
    {

        //altera o sort se for a coluna idUsercredit.
        if (isset($_GET['sort']) && $_GET['sort'] === 'idUsercredit') {
            $_GET['sort'] = '';
        }
        return parent::actionRead($asJson = true, $condition = null);
    }

    public function actionGetChannelDetails()
    {
        $model   = $this->abstractModel->find('uniqueid = :key', array('key' => $_POST['id']));
        $channel = AsteriskAccess::getCoreShowChannel($model->canal);

        $sipcallid = explode("\n", $channel['SIPCALLID']['data']);

        foreach ($sipcallid as $key => $line) {
            if (preg_match("/Received Address/", $line)) {
                $from_ip = explode(" ", $line);
                $from_ip = end($from_ip);
            }
            if (preg_match("/Audio IP/", $line)) {

                $reinvite = explode(" ", $line);
                $reinvite = end($reinvite);
            }
        }
        echo json_encode(array(
            'success'     => true,
            'msg'         => 'success',
            'description' => Yii::app()->session['isAdmin'] ? print_r($channel, true) : '',
            'codec'       => $channel['WriteFormat'],
            'billsec'     => $channel['billsec'],
            'callerid'    => $channel['Caller ID'],
            'from_ip'     => $from_ip,
            'reinvite'    => preg_match("/local/", $reinvite) ? 'no' : 'yes',
            'ndiscado'    => $channel['dnid'],
        ));
    }

    public function actionDestroy()
    {
        $model = $this->abstractModel->find('uniqueid = :key', array('key' => $_POST['id']));

        if (strlen($model->canal) < 30 && preg_match('/SIP\//', $model->canal)) {

            AsteriskAccess::instance()->hangupRequest($model->canal);
            $success = true;
            $msn     = Yii::t('yii', 'Operation was successful.') . Yii::app()->language;
        } else {
            $success = false;
            $msn     = 'error';
        }
        echo json_encode(array(
            'success' => $success,
            'msg'     => $msn,
        ));
        exit();
    }

    public function actionSpyCall()
    {
        if (!isset($_POST['id_sip'])) {
            $dialstr = 'SIP/' . $this->config['global']['channel_spy'];
        } else {
            $modelSip = Sip::model()->findByPk((int) $_POST['id_sip']);
            $dialstr  = 'SIP/' . $modelSip->name;
        }

        $call = "Action: Originate\n";
        $call .= "Channel: " . $dialstr . "\n";
        $call .= "Callerid: " . Yii::app()->session['username'] . "\n";
        $call .= "Context: billing\n";
        $call .= "Extension: 5555\n";
        $call .= "Priority: 1\n";
        $call .= "Set:USERNAME=" . Yii::app()->session['username'] . "\n";
        $call .= "Set:SPY=1\n";
        $call .= "Set:SPYTYPE=" . $_POST['type'] . "\n";
        $call .= "Set:CHANNELSPY=" . $_POST['channel'] . "\n";

        AsteriskAccess::generateCallFile($call);

        echo json_encode(array(
            'success' => true,
            'msg'     => 'Start Spy',
        ));
    }
}
