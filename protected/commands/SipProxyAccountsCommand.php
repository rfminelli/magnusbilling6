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
//not check credit and send call to any number, active or inactive
class SipProxyAccountsCommand extends ConsoleCommand
{
    public function run($args)
    {

        $modelSip     = Sip::model()->findAll();
        $modelServers = Servers::model()->findAll('type = "sipproxy" AND status = 1');

        $sqlproxy = 'TRUNCATE subscriber;';
        $sqlproxy .= 'TRUNCATE address;';
        $sqlproxy .= 'TRUNCATE domain;';

        foreach ($modelServers as $key => $server) {

            $hostname = $server->host;
            $dbname   = 'opensips';
            $table    = 'subscriber';
            $user     = $server->username;
            $password = $server->password;
            $port     = $server->port;

            $dsn = 'mysql:host=' . $hostname . ';dbname=' . $dbname;

            $con         = new CDbConnection($dsn, $user, $password);
            $con->active = true;

            if (preg_match("/\|/", $server->description)) {
                $remoteProxyIP = explode("|", $server->description);
                $remoteProxyIP = end($remoteProxyIP);
                if (!filter_var($remoteProxyIP, FILTER_VALIDATE_IP)) {
                    $remoteProxyIP = $hostname;
                }
            } else {
                $remoteProxyIP = $hostname;
            }

            foreach ($modelSip as $key => $sip) {

                if ($sip->host == 'dynamic') {
                    $sqlproxy .= "INSERT INTO $dbname.$table (username,domain,ha1,accountcode,trace) VALUES ('" . $sip->defaultuser . "', '$remoteProxyIP','" . md5($sip->defaultuser . ':' . $remoteProxyIP . ':' . $sip->secret) . "', '" . $sip->accountcode . "', '" . $sip->trace . "');";
                } else {
                    $sqlproxy .= "INSERT INTO $dbname.address (grp,ip,port,context_info) VALUES ('0', '$sip->host','0', '" . $sip->accountcode . '|' . $sip->name . "');";
                }
            }
        }

        $sqlproxy .= "INSERT INTO $dbname.domain (domain) VALUES ('" . $remoteProxyIP . "')";

        try {
            $con->createCommand($sqlproxy)->execute();
        } catch (Exception $e) {
            print_r($e);
        }

    }
}
