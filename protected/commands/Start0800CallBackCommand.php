<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2017 MagnusBilling. All rights reserved.
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
//not check credit and send call to any number, active or inactive
class Start0800CallBackCommand extends ConsoleCommand
{
    public function run($args)
    {
        ini_set("max_execution_time", "900");

        for (;;) {

            if (date('s') == 58) {
                exit;
            }
            sleep(1);

            $voltarChamar = time() - 1800;
            $voltarChamar = date('Y-m-d H:i:s', $voltarChamar);
            CallBack::model()->updateAll(array('status' => '1'), 'status = 2 AND num_attempt < 4 AND last_attempt_time < :key', array(':key' => $voltarChamar));

            //esperar 60 segundos antes de tentar ligar para o cliente.
            $timeToNewCallback = date('Y-m-d H:i:s', time() - 60);
            $modelCallBack     = CallBack::model()->findAll('status = 1 AND entry_time < :key', array(':key' => $timeToNewCallback));

            if ($this->debug >= 1) {
                echo "\nFound " . count($modelCallBack) . " callback\n\n";
            }

            foreach ($modelCallBack as $callback) {

                $modelDiddestination = Diddestination::model()->find('id_did = :key', array(':key' => $callback->id_did));

                $modelQueue = Queue::model()->findByPk((int) $modelDiddestination->id_queue);

                if (!count($modelQueue)) {
                    echo "User not have QUEUE";
                    continue;
                }
                $server = AsteriskAccess::instance()->queueShow($modelQueue->name);

                $agent = '';
                foreach (explode("\n", $server["data"]) as $key => $value) {
                    //Quantos operadores estao com status not in use
                    if (!preg_match("/paused/", $value) && preg_match("/Not in use/", $value)) {
                        $agent = explode(" ", substr(trim($value), 4));
                        $agent = $agent[0];
                        break;
                    }
                }

                if (strlen($agent) < 2) {
                    echo "Nao tem agent livre para receber chamada\n";
                    continue;
                }

                echo "Agent $agent esta livre para receber chamadas\n";

                $dialstr = "SIP/$agent";

                // gerar os arquivos .call
                $call = "Channel: " . $dialstr . "\n";
                $call .= "Callerid: " . $callback['exten'] . "\n";
                $call .= "Context: billing\n";
                $call .= "Extension: " . $callback['exten'] . "\n";
                $call .= "Priority: 1\n";
                $call .= "Set:IDUSER=" . $callback['id_user'] . "\n";
                $call .= "Set:SECCALL=" . $callback['exten'] . "\n";
                $call .= "Set:IDCALLBACK=" . $callback['id'] . "\n";

                AsteriskAccess::generateCallFile($call, 1);

                $callback->num_attempt++;
                $callback->last_attempt_time = date('Y-m-d H:i:s');
                $callback->status            = 2;
                $callback->save();
                $modelError = $callback->getErrors();
                if (count($modelError)) {
                    print_r($modelError);
                }

            }
        }
    }
}
