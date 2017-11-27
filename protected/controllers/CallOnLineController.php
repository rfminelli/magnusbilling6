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

        // if($_SERVER['HTTP_HOST'] != 'localhost')
        $this->asteriskCommand();
        return parent::actionRead($asJson = true, $condition = null);
    }

    public function actionGetChannelDetails()
    {
        //$_POST['channel'] = 'SIP/77825-00000019';
        $channel = AsteriskAccess::getCoreShowChannel($_POST['channel']);

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
            'description' => print_r($channel, true),
            'codec'       => $channel['WriteFormat'],
            'from_ip'     => $from_ip,
            'reinvite'    => preg_match("/local/", $reinvite) ? 'no' : 'yes',
            'ndiscado'    => $channel['dnid'],
        ));
    }

    public function actionCheck()
    {
        $this->asteriskCommand();
    }

    public function actionDestroy()
    {
        if (strlen($_POST['channel']) < 30 && preg_match('/SIP\//', $_POST['channel'])) {

            AsteriskAccess::instance()->hangupRequest($_POST['channel']);
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

    public function asteriskCommand()
    {

        $modelClear = $this->instanceModel;
        $success    = $modelClear->deleteAll();

        $calls = AsteriskAccess::getCoreShowChannels();

        if (count($calls) > 0) {

            if (isset($_GET['log'])) {

                echo '<pre>';
                print_r($calls);
            }

            $sql = array();
            foreach ($calls as $key => $call) {
                if (isset($_GET['log'])) {
                    echo "<br><br>|" . $call[5] . "|<br>";
                }

                $userType = '';
                $channel  = $call[0];

                $status = $call[4];
                if (preg_match("/Congestion/", $status) || preg_match("/Busy/", $status)) {
                    AsteriskAccess::instance()->hangupRequest($channel);
                    if (isset($_GET['log'])) {
                        echo '145';
                    }

                    continue;
                }
                $trunk         = null;
                $bridgeChannel = $channel[12];
                $ndiscado      = $call[2];
                $cdr           = $call[11];
                $originate     = explode("/", substr($channel, 0, strrpos($channel, "-")));
                $originate     = $originate[1];
                if ($call[5] == 'Dial') {
                    if (isset($_GET['log'])) {
                        echo '156 ' . $call[5];
                    }

                    //is the caller leg

                    //verifico quem iniciou a chamada user ou tronco

                    //se é autenticado por techprefix
                    if (strlen($ndiscado) > 16) {
                        $modelUser = User::model()->find('callingcard_pin = :key', array(':key' => substr($ndiscado, 0, 6)));

                        if (isset($modelUser->id_user)) {
                            $ndiscado = substr($ndiscado, 6);
                        }
                    }
                    $modelSip = Sip::model()->find('name = :key', array(':key' => $originate));

                    if (count($modelSip)) {
                        $userType = 'User';
                    } else {
                        $resultTrunk = Trunk::model()->find('trunkcode = :key', array(':key' => $originate));
                        if (count($resultTrunk)) {
                            $userType = 'Trunk';
                        }
                    }

                    if (!count($userType)) {
                        //not fount the type call
                        continue;
                    } elseif ($userType == 'User') {
                        $trunk = isset($call[6]) ? $call[6] : 0;
                        if (preg_match("/\&/", $trunk)) {
                            $trunk = preg_split("/\&/", $trunk);
                            $trunk = explode("/", $trunk[0]);

                        } else if (preg_match("/\-/", $trunk)) {
                            $trunk = explode("/", substr($trunk, 0, strrpos($trunk, "-")));
                        } else {
                            $trunk = explode("/", $trunk);
                        }

                        $trunk   = isset($trunk[1]) ? $trunk[1] : 0;
                        $id_user = $modelSip->id_user;

                    } elseif ($userType == 'Trunk') {
                        $trunk = $originate . ' DID Call';
                        //a chamada nao foi atendida ainda
                        if ($call[12] == '(None)' && $status == 'Ring') {
                            $id_user = 'NULL';
                        } elseif (strlen($call[12]) > 5 || $status == 'Up') {
                            //chamada DID foi atendida
                            $usernameReceive = explode("/", substr($call[12], 0, strrpos($call[12], "-")));
                            $resultUser      = Sip::model()->findAll(array(
                                'select'    => 'pkg_user.id, username',
                                'join'      => 'LEFT JOIN pkg_user ON t.id_user = pkg_user.id',
                                'condition' => "t.name = '" . $usernameReceive[1] . "'",
                            ));
                            $id_user = isset($resultUser[0]['id']) ? $resultUser[0]['id'] : 'NULL';
                        }

                    }
                } elseif ($call[5] == 'AGI') {

                    if ($call[8] == 'MC') {
                        //torpedo
                        $cdr           = $call[12];
                        $ndiscado      = $call['2'];
                        $modelCampaing = Campaign::model()->find('name = :key', array(':key' => $call[9]));

                        $id_user = isset($modelCampaing->id_user) ? $modelCampaing->id_user : 'NULL';
                        $trunk   = "Campaign " . $call[9];
                    } else {
                        //check if is a DID number
                        $resultDid = $this->isDid($call[2]);
                        if (isset($resultDid[0]['id'])) {
                            $ndiscado = $call['2'];
                            $id_user  = $resultDid[0]['id_user'];

                            switch ($resultDid[0]['voip_call']) {
                                case 2:
                                    $trunk = $originate . ' IVR';
                                    break;
                                case 3:
                                    $trunk = $originate . ' CallingCard';
                                    break;
                                case 4:
                                    $trunk = $originate . ' portalDeVoz';
                                    break;
                                case 4:
                                    $trunk = $originate . ' CID Callback';
                                    break;
                                case 5:
                                    $trunk = $originate . ' CID Callback';
                                    break;
                                case 6:
                                    $trunk = $originate . ' 0800 Callback';
                                    break;
                                default:
                                    $trunk = $originate . ' DID Call';
                                    break;
                            }

                        } else {
                            $ndiscado = $call['2'];
                            $id_user  = 'NULL';
                        }
                    }
                } elseif ($call[5] == 'Queue') {
                    //check if is a DID number
                    $resultDid = $this->isDid($call[2]);
                    if (isset($resultDid[0]['id'])) {
                        $ndiscado = $call['2'];
                        $id_user  = $resultDid[0]['id_user'];
                        $trunk    = $originate . ' Queue ' . substr($call[6], 0, strpos($call[6], ','));

                    } else {
                        $ndiscado = $call['2'];
                        $id_user  = 'NULL';
                    }
                } else {
                    if (isset($_GET['log'])) {
                        echo '295 ' . $call[5];
                    }

                    continue;
                }
                $sql[] = "(NULL, $id_user, '$channel', '$trunk', '$ndiscado', 'NULL', '$status', '$cdr', 'no','no', '" . $call['server'] . "')";

            }

            if (count($sql) > 0) {
                $this->abstractModel->insertCalls($sql);
            }
        }

    }

    public function isDid($id_did)
    {
        return Diddestination::model()->findAll(array(
            'select'    => 't.id, t.id_user, voip_call',
            'join'      => 'JOIN pkg_did AS d ON t.id_did = d.id',
            'condition' => "did = '" . $id_did . "'",
        ));
    }

    public function actionSpyCall()
    {

        if (count($this->config['global']['channel_spy']) == 0) {
            echo json_encode(array(
                'success' => false,
                'msg'     => 'Invalid SIP for spy call',
            ));
            exit;
        }
        $dialstr = 'SIP/' . $this->config['global']['channel_spy'];
        $call    = "Action: Originate\n";
        $call .= "Channel: " . $dialstr . "\n";
        $call .= "Callerid: " . Yii::app()->session['username'] . "\n";
        $call .= "Context: billing\n";
        $call .= "Extension: 5555\n";
        $call .= "Priority: 1\n";
        $call .= "Set:USERNAME=" . Yii::app()->session['username'] . "\n";
        $call .= "Set:SPY=1\n";
        $call .= "Set:CHANNELSPY=" . $_POST['channel'] . "\n";

        AsteriskAccess::generateCallFile($call);

        echo json_encode(array(
            'success' => true,
            'msg'     => 'Start Spy',
        ));
    }
}
