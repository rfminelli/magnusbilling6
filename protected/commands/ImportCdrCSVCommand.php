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
class ImportCdrCSVCommand extends CConsoleCommand
{
    public function run($args)
    {

        /*
        ;execute it to configure CDR_CUSTOM in Asterisk, then load module cdr_custom
        echo '[mappings]
        Master.csv => ${CSV_QUOTE(${CDR(clid)})},${CSV_QUOTE(${CDR(src)})},${CSV_QUOTE(${CDR(dst)})},${CSV_QUOTE(${CDR(dcontext)})},${CSV_QUOTE(${CDR(channel)})},${CSV_QUOTE(${CDR(dstchannel)})},${CSV_QUOTE(${CDR(lastapp)})},${CSV_QUOTE(${CDR(lastdata)})},${CSV_QUOTE(${CDR(start)})},${CSV_QUOTE(${CDR(answer)})},${CSV_QUOTE(${CDR(end)})},${CSV_QUOTE(${CDR(duration)})},${CSV_QUOTE(${CDR(billsec)})},${CSV_QUOTE(${CDR(disposition)})},${CSV_QUOTE(${CDR(amaflags)})},${CSV_QUOTE(${CDR(accountcode)})},${CSV_QUOTE(${CDR(uniqueid)})},${CSV_QUOTE(${CDR(userfield)})},${CDR(sequence)}' > /etc/asterisk/cdr_custom.conf

        touch /var/log/asterisk/cdr-csv/error.csv
         */

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

        if (file_exists('/var/log/asterisk/cdr-custom/Master.csv')) {
            exec('mv /var/log/asterisk/cdr-custom/Master.csv /var/log/asterisk/cdr-custom/Master' . $time . '.csv');

            $fila = 1;
            if (($gestor = fopen('/var/log/asterisk/cdr-custom/Master' . $time . '.csv', "r")) !== false) {
                while (($datos = fgetcsv($gestor, 1000, ",")) !== false) {

                    if ($datos[12] > 0) {

                        $result = exec("egrep '" . $datos[16] . ',' . $datos[1] . "' /var/log/asterisk/cdr-csv/MBilling_Success.csv");
                        if (strlen($result) < 10) {
                            print_r($datos);
                            echo "egrep '" . $datos[16] . ',' . $datos[1] . "' /var/log/asterisk/cdr-csv/MBilling_Success.csv";
                        } else {

                            $result = explode(',', $result);
                            if ($result[12] != $datos[12]) {
                                exec('echo "' . print_r($datos, true) . print_r($result, true) . '" >> /var/log/asterisk/cdr-csv/error.csv');
                                print_r($result);
                                print_r($datos);
                            }
                        }
                    }
                }
                fclose($gestor);
            }
            exec('rm -rf /var/log/asterisk/cdr-custom/Master' . $time . '.csv');
        }

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
                if ($file != 'Master.csv' && $file != 'error.csv') {
                    exec('rm -rf /var/log/asterisk/cdr-csv/' . $file);
                }
                continue;
            }
            $files[$file] = filemtime($dir . '/' . $file);
        }

        arsort($files);
        $files = array_keys($files);

        return ($files) ? $files : false;
    }

}
