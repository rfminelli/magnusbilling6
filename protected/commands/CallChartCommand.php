<?php
/**
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
 *
 */
class CallChartCommand extends ConsoleCommand
{
    public function run($args)
    {

        $modelUserCallShop = User::model()->count('callshop = 1');

        if ($modelUserCallShop > 0) {
            $modelUserCallShop = User::model()->findAll('callshop = 1');
            foreach ($modelUserCallShop as $key => $value) {
                $callShopIds[] = $value->id;
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $success = CallOnLine::model()->deleteAll();

            try {
                $calls = AsteriskAccess::getCoreShowChannels();
            } catch (Exception $e) {
                continue;
            }

            $total = 0;
            if (count($calls) > 0) {

                if ($this->debug > 1) {

                    echo '<pre>';
                    print_r($calls);
                }

                $sql = array();
                foreach ($calls as $key => $call) {

                    if (preg_match("/Up/", $call[4])) {
                        $total++;
                    }

                    if (isset($_GET['log'])) {
                        echo "<br><br>|" . $call[5] . "|<br>";
                    }

                    $userType = '';
                    $channel  = $call[0];

                    $status = $call[4];
                    if ((preg_match("/Congestion/", $status) || preg_match("/Busy/", $status)) ||
                        (preg_match('/Ring/', $status) && $call[11] > 60)
                    ) {
                        AsteriskAccess::instance()->hangupRequest($channel);
                        continue;
                    }
                    $trunk         = null;
                    $uniqueid      = $call[13];
                    $bridgeChannel = $channel[12];
                    $ndiscado      = $call[2];
                    $cdr           = $call[11];
                    $originate     = explode("/", substr($channel, 0, strrpos($channel, "-")));
                    $originate     = $originate[1];
                    if ($call[5] == 'Dial' || $call[5] == 'Mbilling') {
                        if (isset($_GET['log'])) {
                            echo '156 ' . $call[5];
                        }

                        //is the caller leg

                        //verifico quem iniciou a chamada user ou tronco

                        //se é autenticado por techprefix
                        if (strlen($ndiscado) > 16) {
                            $modelUser = User::model()->find('callingcard_pin = :key', array(':key' => substr($ndiscado, 0, 6)));

                            if (isset($modelUser->id_user)) {
                                $modelSip = Sip::model()->find('id_user = :key', array(':key' => $modelUser->id));
                                //$ndiscado = substr($ndiscado, 6);
                            }
                        } else {
                            $modelSip = Sip::model()->find('name = :key', array(':key' => $originate));
                        }

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

                            } else if (preg_match("/@/", $trunk)) {
                                $trunk    = explode("@", $trunk);
                                $trunk    = explode(",", $trunk[1]);
                                $trunk[1] = $trunk[0];
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
                                if (isset($resultUser[0]['id'])) {
                                    $id_user = $resultUser[0]['id'];
                                } else {
                                    $modelDid = Did::model()->find('did = :key', array(':key' => $call[2]));
                                    $id_user  = count($modelDid) ? $modelDid->id_user : null;
                                }
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

                    if (!is_numeric($id_user)) {
                        continue;
                    }

                    $sql[] = "(NULL, '$uniqueid', '$originate', $id_user, '$channel', '$trunk', '$ndiscado', 'NULL', '$status', '$cdr', 'no','no', '" . $call['server'] . "')";

                    if ($modelUserCallShop > 0) {
                        if (in_array($modelSip->id_user, $callShopIds)) {
                            $modelSip->status         = 3;
                            $modelSip->callshopnumber = $ndiscado;
                            $modelSip->callshoptime   = $cdr;
                            $modelSip->save();
                            $modelSip = null;
                        }
                    }
                }

                $total = intval($total / 2);
                if ($i == 0 || !isset($total1)) {
                    $modelCallOnlineChart         = new CallOnlineChart();
                    $modelCallOnlineChart->date   = date('Y-m-d H:i:s');
                    $modelCallOnlineChart->answer = $total;
                    $modelCallOnlineChart->total  = 0;
                    $modelCallOnlineChart->save();

                    $id     = $modelCallOnlineChart->id;
                    $total1 = $total;
                } else {
                    if ($total > $total1) {
                        CallOnlineChart::model()->updateByPk($id, array('answer' => $total));
                    }
                }

                if (count($sql) > 0) {

                    CallOnLine::model()->insertCalls($sql);
                }
            }
            sleep(4);
        }

        if (date('H:i') > '23:52') {
            CallOnlineChart::model()->deleteAll('date < :key', array(':key' => date('Y-m-d')));
        }
    }

    private function isDid($id_did)
    {
        return Diddestination::model()->findAll(array(
            'select'    => 't.id, t.id_user, voip_call',
            'join'      => 'JOIN pkg_did AS d ON t.id_did = d.id',
            'condition' => "did = '" . $id_did . "'",
        ));
    }
}
