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
class MassiveCallCommand extends ConsoleCommand
{
    public function run($args)
    {

        $UNIX_TIMESTAMP = "UNIX_TIMESTAMP(";

        $tab_day  = array(1 => 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $num_day  = date('N');
        $name_day = $tab_day[$num_day];

        $filter = 'status = :key AND type = :key  AND ' . $name_day . ' = :key AND startingdate <= :key1 AND expirationdate > :key1
                        AND  daily_start_time <= :key2 AND daily_stop_time > :key2';

        $params = array(
            ':key'  => 1,
            ':key1' => date('Y-m-d H:i:s'),
            ':key2' => date('H:i:s'),
        );

        $modelCampaign = Campaign::model()->findAll(array(
            'condition' => $filter,
            'params'    => $params,
        ));

        if ($this->debug >= 1) {
            echo "\nFound " . count($modelCampaign) . " Campaign\n\n";
        }

        foreach ($modelCampaign as $campaign) {
            if ($this->debug >= 1) {
                echo "SEARCH NUMBER IN CAMPAIGN " . $campaign->name . "\n";
            }

            //get all campaign phonebook
            $modelCampaignPhonebook = CampaignPhonebook::model()->findAll('id_campaign = :key', array(':key' => $campaign->id));
            $ids_phone_books        = array();
            foreach ($modelCampaignPhonebook as $key => $phonebook) {
                $ids_phone_books[] = $phonebook->id_phonebook;
            }

            $criteria = new CDbCriteria();
            $criteria->addInCondition('id_phonebook', $ids_phone_books);
            $criteria->addCondition('status = :key AND creationdate < :key1');
            $criteria->params[':key']  = 1;
            $criteria->params[':key1'] = date('Y-m-d H:i:s');
            $criteria->limit           = $campaign->frequency;
            $modelPhoneNumber          = PhoneNumber::model()->findAll($criteria);

            if ($this->debug >= 1) {
                echo 'Found ' . count($modelPhoneNumber) . ' Numbers in Campaign ' . "\n";
            }

            if (!count($modelPhoneNumber)) {
                if ($this->debug >= 1) {
                    echo "NO PHONE FOR CALL" . "\n\n\n";
                }

                continue;
            }

            if ($campaign->frequency <= 60) {
                //se for menos de 60 por minutos divido 60 pela frequncia e depois somo o resultado para mandar 1 chamada a cada segundos resultante da divisao.
                $sleep = 60 / $campaign->frequency;

            } else {
                //divido a frequencia por 60 e depois mando o resultado em cada segundo.
                $sleep = $campaign->frequency / 60;
            }

            $i         = 0;
            $ids       = array();
            $sleepNext = 1;
            foreach ($modelPhoneNumber as $phone) {
                $i++;

                $id_plan  = $campaign->id_plan > 0 ? $campaign->id_plan : $campaign->idUser->id_plan;
                $id_user  = $campaign->idUser->id;
                $username = $campaign->idUser->username;
                $id_agent = $campaign->idUser->id_user;

                if ($id_agent > 1) {
                    $id_plan_agent = $id_plan;
                    $modelAgent    = User::model()->findByPk((int) $id_agent);
                    $id_plan       = $modelAgent->id_plan;
                } else {
                    $id_plan_agent = 0;
                }

                $name_number = $phone->name;
                $destination = $phone->number;

                if ($campaign->restrict_phone == 1) {
                    $modelCampaignRestrictPhone = CampaignRestrictPhone::model()->find('number = :key', array(':key' => $destination));

                    if (count($modelCampaignRestrictPhone)) {
                        $phone->status = 4;
                        $phone->save();
                        if ($this->debug >= 1) {
                            echo "NUMBER " . $destination . "WAS BLOCKED\n\n\n";
                        }

                        continue;
                    }

                }

                if ($phone->try > 1) {
                    $phone->status = 0;
                    $phone->save();
                    if ($this->debug >= 1) {
                        echo "DISABLE NUMBER  " . $destination . " AFTER TWO TRYING\n\n\n";
                    }

                    continue;
                }

                if (UserCreditManager::checkGlobalCredit($id_user) === false) {
                    if ($this->debug >= 1) {
                        echo " USER NO CREDIT FOR CALL " . $username . "\n\n\n";
                    }

                    continue;
                }

                $destination = Portabilidade::getDestination($destination, $id_plan);

                $searchTariff = Plan::model()->searchTariff($id_plan, $destination);

                if (!count($searchTariff[1])) {
                    $phone->status = 0;
                    $phone->save();
                    if ($this->debug >= 1) {
                        echo " NO FOUND RATE TO CALL " . $username . "  DESTINATION $destination \n\n";
                    }

                    continue;
                }

                $searchTariff = $searchTariff[1];

                if ($searchTariff[0]['status'] == 0 || $phone->try > 0 || ($searchTariff[0]['inuse'] >= $searchTariff[0]['maxuse'] && $searchTariff[0]['maxuse'] != -1)) {
                    $modelTrunk = Trunk::model()->findByPk((int) $searchTariff[0]['rt_failover_trunk']);

                    if (count($modelTrunk) > 0) {
                        $idTrunk        = $modelTrunk->id;
                        $trunkcode      = $modelTrunk->trunkcode;
                        $trunkprefix    = $modelTrunk->trunkprefix;
                        $removeprefix   = $modelTrunk->removeprefix;
                        $providertech   = $modelTrunk->providertech;
                        $inuse          = $modelTrunk->inuse;
                        $maxuse         = $modelTrunk->maxuse;
                        $status         = $modelTrunk->status;
                        $failover_trunk = $modelTrunk->failover_trunk;
                    } else {
                        //desativa o numero de falhou na 1º tentativa e nao tem backup
                        $phone->status = 0;
                        $phone->save();
                        continue;
                    }

                } else {
                    $idTrunk        = $searchTariff[0]['id_trunk'];
                    $trunkcode      = $searchTariff[0]['rc_providerip'];
                    $trunkprefix    = $searchTariff[0]['rc_trunkprefix'];
                    $removeprefix   = $searchTariff[0]['rc_removeprefix'];
                    $providertech   = $searchTariff[0]['rc_providertech'];
                    $inuse          = $searchTariff[0]['inuse'];
                    $maxuse         = $searchTariff[0]['maxuse'];
                    $status         = $searchTariff[0]['status'];
                    $failover_trunk = $searchTariff[0]['rt_failover_trunk'];
                }

                if (substr($destination, 0, 4) == '1111') {
                    $destination = str_replace(substr($destination, 0, 7), '', $destination);
                }

                $extension = $destination;

                //retiro e adiciono os prefixos do tronco
                if (strncmp($destination, $removeprefix, strlen($removeprefix)) == 0) {
                    $destination = substr($destination, strlen($removeprefix));
                }

                $destination = $trunkprefix . $destination;

                $modelSip = Sip::model()->find('id_user = :key', array(':key' => $id_user));

                $dialstr = "$providertech/$trunkcode/$destination";

                // gerar os arquivos .call
                $call = "Action: Originate\n";
                $call = "Channel: " . $dialstr . "\n";
                $call .= "Callerid: " . $modelSip->callerid . ' ' . $phone->name . "\n";
                $call .= "Account:  MC!" . $campaign->name . "\n";
                //$call .= "MaxRetries: 1\n";
                //$call .= "RetryTime: 100\n";
                //$call .= "WaitTime: 45\n";
                $call .= "Context: billing\n";
                $call .= "Extension: " . $extension . "\n";
                $call .= "Priority: 1\n";
                $call .= "Set:CALLED=" . $extension . "\n";
                $call .= "Set:USERNAME=" . $username . "\n";
                $call .= "Set:IDUSER=" . $id_user . "\n";
                $call .= "Set:PHONENUMBER_ID=" . $phone->id . "\n";
                $call .= "Set:PHONENUMBER_CITY=" . $phone->city . "\n";
                $call .= "Set:CAMPAIGN_ID=" . $campaign->id . "\n";
                $call .= "Set:RATE_ID=" . $searchTariff[0]['id_rate'] . "\n";
                $call .= "Set:AGENT_ID=" . $id_agent . "\n";
                $call .= "Set:AGENT_ID_PLAN=" . $id_plan_agent . "\n";

                if ($this->debug > 1) {
                    echo $call . "\n\n";
                }

                AsteriskAccess::generateCallFile($call, $sleepNext);

                if ($campaign->frequency <= 60) {
                    $sleepNext += $sleep;
                } else {
                    //a cada multiplo do resultado, passo para o proximo segundo
                    if (($i % $sleep) == 0) {
                        $sleepNext += 1;
                    }

                }
                $ids[] = $phone->id;

            }

            $criteria = new CDbCriteria();
            $criteria->addInCondition('id', $ids);
            PhoneNumber::model()->updateAll(
                array(
                    'status' => '2',
                    'try'    => new CDbExpression('try + 1'),
                ),
                $criteria
            );

            echo "Campain " . $campaign->name . " sent " . $i . " calls \n\n";
        }
    }
}
