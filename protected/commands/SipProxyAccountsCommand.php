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

            $sql = "TRUNCATE $table";
            $con->createCommand($sql)->execute();

            if (preg_match("/\|/", $server->description)) {
                $remoteProxyIP = trim(end(explode("|", $server->description)));
                if (!filter_var($remoteProxyIP, FILTER_VALIDATE_IP)) {
                    $remoteProxyIP = $hostname;
                }
            } else {
                $remoteProxyIP = $hostname;
            }

            foreach ($modelSip as $key => $sip) {

                $sql = "INSERT INTO $dbname.$table (username,domain,ha1,accountcode,trace) VALUES ('" . $sip->defaultuser . "', '$remoteProxyIP','" . md5($sip->defaultuser . ':' . $remoteProxyIP . ':' . $sip->secret) . "', '" . $sip->accountcode . "', '" . $sip->trace . "')";
                try {
                    $con->createCommand($sql)->execute();
                } catch (Exception $e) {
                    //
                }
            }

        }
    }
}
