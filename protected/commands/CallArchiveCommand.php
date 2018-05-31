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
class CallArchiveCommand extends ConsoleCommand
{
    public function run($args)
    {
        $prior_x_month = $this->config['global']['archive_call_prior_x_month'];

        $condition = "DATE_SUB(NOW(),INTERVAL $prior_x_month MONTH) > starttime";

        CallFailed::model()->createDataBaseIfNotExist();

        $c      = 0;
        $tables = array('pkg_cdr', 'pkg_cdr_failed');
        foreach ($tables as $key => $table) {

            $sql    = "SELECT count(*) AS count FROM $table WHERE $condition ";
            $result = Yii::app()->db->createCommand($sql)->queryAll();

            $loop = number_format($result[0]['count'] / 10000, 0);

            if ($table == 'pkg_cdr') {
                $func_fields = "id_user, id_plan, id_prefix, id_trunk, sessionid, uniqueid, starttime, sessiontime, calledstation, sessionbill, sipiax, src, buycost, real_sessiontime, terminatecauseid, agent_bill";
            } else {
                $func_fields = "id_user, id_plan, id_prefix, id_trunk, sessionid, uniqueid, starttime, calledstation, sipiax, src, terminatecauseid";
            }

            if ($c == 0) {
                $condition = $condition . " ORDER BY id LIMIT 10000";
                $c++;
            }

            for ($i = 0; $i < $loop; $i++) {
                echo "New insert \n";
                $sql = "INSERT INTO " . $table . "_archive ($func_fields) SELECT $func_fields FROM " . $table . " WHERE $condition";
                try {
                    Yii::app()->db->createCommand($sql)->execute();
                } catch (Exception $e) {

                }
                $sql = "DELETE FROM $table WHERE $condition";
                Yii::app()->db->createCommand($sql)->execute();
                sleep(60);
            }
        }

    }
}
