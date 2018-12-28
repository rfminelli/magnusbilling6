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
class ImportCdrCSVCommand extends ConsoleCommand
{
    public function run($args)
    {

        $configFile = '/etc/odbc.ini';
        $array      = parse_ini_file($configFile);
        $server     = $array['Server'];
        $database   = $array['Database'];

        $configFile = '/etc/asterisk/res_odbc.conf';
        $array      = parse_ini_file($configFile);

        $user = $array['username'];
        $pass = $array['password'];

        $dsn = 'mysql:host=' . $server . ';dbname=' . $database;

        $con         = new CDbConnection($dsn, $user, $pass);
        $con->active = true;
        $time        = time();

        if ($result = $this->scan_dir('/var/log/asterisk/cdr-csv/', 1)) {

            foreach ($result as $file) {
                if (preg_match('/^MBilling_/', $file) && file_exists('/var/log/asterisk/cdr-csv/' . $file)) {

                    if (preg_match('/^MBilling_Success/', $file)) {
                        $sql = "LOAD DATA LOCAL INFILE '/var/log/asterisk/cdr-csv/" . $file . "' IGNORE INTO TABLE pkg_cdr FIELDS TERMINATED BY ','  LINES TERMINATED BY '\n'  (uniqueid,callerid,starttime,id_user,id_plan,src,id_prefix,id_trunk,calledstation,buycost,sessionbill,sessiontime,real_sessiontime,agent_bill)";

                    } else if (preg_match('/^MBilling_Success/', $file)) {
                        $sql = "LOAD DATA LOCAL INFILE '/var/log/asterisk/cdr-csv/" . $file . "' IGNORE INTO TABLE pkg_cdr_failed FIELDS TERMINATED BY ','  LINES TERMINATED BY '\n'  (uniqueid,starttime,id_user,id_plan,src,id_prefix,id_trunk,calledstation,terminatecauseid,hangupcause)";
                    }

                    try {
                        Yii::app()->db->createCommand($sql)->execute();
                        exec("rm -rf /var/log/asterisk/cdr-csv/" . $file);
                    } catch (Exception $e) {
                        print_r($e);
                    }
                }
            }
        }

        if (file_exists('/var/log/asterisk/cdr-csv/MBilling_Success.csv')) {

            exec('mv /var/log/asterisk/cdr-csv/MBilling_Success.csv /var/log/asterisk/cdr-csv/MBilling_Success_' . $time . '.csv');
            $sql = "LOAD DATA LOCAL INFILE '/var/log/asterisk/cdr-csv/MBilling_Success_" . $time . ".csv' IGNORE INTO TABLE pkg_cdr FIELDS TERMINATED BY ','  LINES TERMINATED BY '\n'  (uniqueid,callerid,starttime,id_user,id_plan,src,id_prefix,id_trunk,calledstation,buycost,sessionbill,sessiontime,real_sessiontime,agent_bill)";
            try {
                Yii::app()->db->createCommand($sql)->execute();
                exec("rm -rf /var/log/asterisk/cdr-csv/MBilling_Success_" . $time . ".csv");
            } catch (Exception $e) {
                print_r($e);
            }
        }

        if (file_exists('/var/log/asterisk/cdr-csv/MBilling_Failed.csv')) {

            exec("mv /var/log/asterisk/cdr-csv/MBilling_Failed.csv /var/log/asterisk/cdr-csv/MBilling_Failed_" . $time . ".csv");
            $sql = "LOAD DATA LOCAL INFILE '/var/log/asterisk/cdr-csv/MBilling_Failed_" . $time . ".csv' IGNORE INTO TABLE pkg_cdr_failed FIELDS TERMINATED BY ','  LINES TERMINATED BY '\n'  (uniqueid,starttime,id_user,id_plan,src,id_prefix,id_trunk,calledstation,terminatecauseid,hangupcause)";
            try {
                Yii::app()->db->createCommand($sql)->execute();
                exec("rm -rf /var/log/asterisk/cdr-csv/MBilling_Failed_" . $time . ".csv");
            } catch (Exception $e) {
                print_r($e);

            }

        }

        $con = null;
    }

    public function scan_dir($dir)
    {

        $ignored = array('.', '..', '.svn', '.htaccess', 'MBilling_Failed.csv', 'MBilling_Success.csv');

        $files = array();
        foreach (scandir($dir) as $file) {
            if (in_array($file, $ignored)) {
                continue;
            }
            if (!preg_match('/^MBilling_/', $file)) {
                continue;
            }
            $files[$file] = filemtime($dir . '/' . $file);
        }

        arsort($files);
        $files = array_keys($files);

        return ($files) ? $files : false;
    }

}
