<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2018 MagnusBilling. All rights reserved.
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

/*
0 4 * * * php /var/www/html/mbilling/cron.php SummaryTablesCdr processCdrLast30Days
0 8-22 * * * php /var/www/html/mbilling/cron.php SummaryTablesCdr processCdrToday
 */

class SummaryTablesCdrCommand extends CConsoleCommand
{
    private $con;
    private $signal;
    private $date;
    public function run($args)
    {

        if (isset($args[0])) {
            try {
                if ($args[0] == 'processCdrToday') {
                    $this->processSummary('day');
                } elseif ($args[0] == 'processCdrLast30Days') {
                    $this->processSummary('month');
                }
            } catch (Exception $e) {
                $class_methods = get_class_methods('SummaryTablesCdrCommand');
                echo "Availables comands";
                for ($i = 1; $i < 16; $i++) {
                    echo $class_methods[$i] . "\n";
                }
            }

        } else {
            $this->perDay();
            $this->perDayUser();
            $this->perDayTrunk();
            $this->perDayAgent();
            $this->perMonth();
            $this->perMonthUser();
            $this->perMonthTrunk();
            $this->perUser();
            $this->perTrunk();
        }

    }

    public function processSummary($type)
    {

        if ($type == 'day') {
            $this->date   = date("Y-m-d");
            $filter       = "starttime > '$this->date'";
            $this->signal = '=';
        } elseif ($type == 'month') {
            $this->date   = date("Y-m-d", strtotime("- 30 day"));
            $filter       = "starttime > '$this->date 23:59:59'";
            $this->signal = '>';
        }

        //perday
        $this->perDay($filter);
        $this->perDayUser($filter);
        $this->perDayTrunk($filter);
        $this->perDayAgent($filter);

        //permonth
        $filter = "starttime > '" . date("Y-m") . "-01'";

        $this->perMonth($filter);
        $this->perMonthUser($filter);
        $this->perMonthTrunk($filter);

        if ($type == 'month') {

            $this->perUser();
            $this->perTrunk();
        }

    }
    public function perDay($filter = 1)
    {

        $sql = "SELECT DATE(starttime) AS day,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter GROUP BY day ORDER BY day DESC";
        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['day'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['day'] . "', '" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "'),";
        }

        $sql = "DELETE FROM pkg_cdr_summary_day WHERE day $this->signal '$this->date' ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_day (day, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill) VALUES " . substr($line, 0, -1) . ";";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        echo "perDay " . date('H:i:s') . "\n";

        $sql = "SELECT DATE(starttime) AS day,
                count(*) as nbcall_fail
                FROM pkg_cdr_failed t WHERE $filter  GROUP BY day ORDER BY day DESC";
        $model = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($model as $key => $value) {
            $sql = "UPDATE pkg_cdr_summary_day SET nbcall_fail =" . $value['nbcall_fail'] . " WHERE day = '" . $value['day'] . "' ";
            Yii::app()->db->createCommand($sql)->execute();
        }

        $sql = "UPDATE  pkg_cdr_summary_day SET asr = (nbcall / ( nbcall_fail + nbcall) ) * 100 ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_day SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perDayUser($filter = 1)
    {

        $sql = "SELECT DATE(starttime) AS day,
                id_user,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill,
                sum(agent_bill) AS agent_bill
                FROM pkg_cdr t WHERE $filter GROUP BY day, id_user ORDER BY day DESC";

        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['day'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['day'] . "','" . $value['id_user'] . "', '" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "','" . $value['agent_bill'] . "'),";
        }

        $sql = "DELETE FROM pkg_cdr_summary_day_user WHERE day $this->signal '$this->date' ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_day_user (day, id_user, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill, agent_bill) VALUES " . substr($line, 0, -1) . ";";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            echo $sql . "\n";
            print_r($e->getMessage());
        }

        echo "perDayUser " . date('H:i:s') . "\n";

        $sql = "UPDATE pkg_cdr_summary_day_user t  INNER JOIN pkg_user u ON t.id_user = u.id SET t.isAgent = IF(u.id_user > 1, 1,0);";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_day_user SET lucro = sessionbill - buycost WHERE isAgent = 0";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_day_user SET lucro = agent_bill - sessionbill WHERE isAgent = 1";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perDayTrunk($filter = 1)
    {

        $sql = "SELECT DATE(starttime) AS day,
                id_trunk,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter AND id_trunk IS NOT NULL GROUP BY day, id_trunk ORDER BY day DESC";

        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['day'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['day'] . "','" . $value['id_trunk'] . "', '" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "'),";
        }

        $sql = "DELETE FROM pkg_cdr_summary_day_trunk WHERE day $this->signal '$this->date' ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_day_trunk (day, id_trunk, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill) VALUES " . substr($line, 0, -1) . ";";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        echo "perDayTrunk " . date('H:i:s') . "\n";

        $sql = "UPDATE  pkg_cdr_summary_day_trunk SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perDayAgent($filter = 1)
    {

        $sql = "SELECT DATE(starttime) AS day,
                c.id_user as id_user,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t
                LEFT JOIN pkg_user c ON t.id_user = c.id
                WHERE  $filter AND c.id_user > 1  GROUP BY day, c.id_user ORDER BY day DESC";

        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['day'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['day'] . "','" . $value['id_user'] . "', '" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "'),";
        }

        $sql = "DELETE FROM pkg_cdr_summary_day_agent WHERE day $this->signal '$this->date' ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_day_agent (day, id_user, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill) VALUES " . substr($line, 0, -1) . ";";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        echo "perDayAgent " . date('H:i:s') . "\n";

        $sql = "UPDATE  pkg_cdr_summary_day_agent SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perMonth($filter = 1)
    {

        $sql = "SELECT EXTRACT(YEAR_MONTH FROM starttime) AS month,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter GROUP BY month ORDER BY month DESC";
        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['month'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['month'] . "', '" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "'),";
        }

        $sql = "DELETE FROM pkg_cdr_summary_month WHERE month = '" . date("Ym") . "' ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_month (month, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill) VALUES " . substr($line, 0, -1) . ";";

        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        echo "perMonth " . date('H:i:s') . "\n";

        $sql = "UPDATE  pkg_cdr_summary_month SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perMonthUser($filter = 1)
    {

        $sql = "SELECT EXTRACT(YEAR_MONTH FROM starttime) AS month,
                id_user,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill,
                sum(agent_bill) AS agent_bill
                FROM pkg_cdr t WHERE $filter GROUP BY month, id_user ORDER BY month DESC";
        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['month'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['month'] . "','" . $value['id_user'] . "', '" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "','" . $value['agent_bill'] . "'),";
        }

        $sql = "DELETE FROM pkg_cdr_summary_month_user WHERE month = '" . date("Ym") . "' ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_month_user (month, id_user, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill,agent_bill) VALUES " . substr($line, 0, -1) . ";";

        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        echo "perMonthUser " . date('H:i:s') . "\n";

        $sql = "UPDATE pkg_cdr_summary_month_user t  INNER JOIN pkg_user u ON t.id_user = u.id SET t.isAgent = IF(u.id_user > 1, 1,0);";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_month_user SET lucro = sessionbill - buycost  WHERE isAgent = 0";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_month_user SET lucro = agent_bill - sessionbill  WHERE isAgent = 1";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perMonthTrunk($filter = 1)
    {

        $sql = "SELECT EXTRACT(YEAR_MONTH FROM starttime) AS month,
                id_trunk,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter AND  id_trunk IS NOT NULL GROUP BY month, id_trunk ORDER BY month DESC";

        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['month'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['month'] . "','" . $value['id_trunk'] . "','" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "'),";
        }

        $sql = "DELETE FROM pkg_cdr_summary_month_trunk WHERE month = '" . date("Ym") . "' ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_month_trunk (month, id_trunk, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill) VALUES " . substr($line, 0, -1) . ";";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        echo "perMonthTrunk " . date('H:i:s') . "\n";

        $sql = "UPDATE  pkg_cdr_summary_month_trunk SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perUser($filter = 1)
    {

        $sql = "SELECT t.id_user,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill,
                c.id_user ,
                sum(agent_bill) AS agent_bill
                FROM pkg_cdr t
                LEFT JOIN pkg_user c ON t.id_user = c.id
                GROUP BY t.id_user";
        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['id_user'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['id_user'] . "','" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "','" . $value['isAgent'] . "','" . $value['agent_bill'] . "'),";
        }

        $sql = "TRUNCATE pkg_cdr_summary_user";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_user (id_user, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill, isAgent, agent_bill) VALUES " . substr($line, 0, -1) . ";";

        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
            //print_r($e);
            return;
        }

        $sql = "SELECT t.id_user,
        count(*) as nbcall_fail
        FROM pkg_cdr_failed t LEFT JOIN pkg_user c ON t.id_user = c.id
        GROUP BY t.id_user";
        $model = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($model as $key => $value) {
            $sql = "UPDATE pkg_cdr_summary_user SET nbcall_fail =" . $value['nbcall_fail'] . " WHERE id_user = '" . $value['id_user'] . "' ";
            Yii::app()->db->createCommand($sql)->execute();
        }

        $sql = "UPDATE  pkg_cdr_summary_user SET isAgent = 0 WHERE isAgent <= 1";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_user SET isAgent =1 WHERE isAgent > 1";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_user SET asr = (nbcall / ( nbcall_fail + nbcall) ) * 100 ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_user SET lucro = sessionbill - buycost  WHERE isAgent = 0";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_user SET lucro = agent_bill - sessionbill  WHERE isAgent = 1";
        Yii::app()->db->createCommand($sql)->execute();

    }

    public function perTrunk($filter = 1)
    {
        $sql = "SELECT t.id_trunk,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t
                LEFT JOIN pkg_trunk c ON t.id_trunk = c.id
                GROUP BY t.id_trunk";
        $result = Yii::app()->db->createCommand($sql)->queryAll();

        if (!isset($result[0]['id_trunk'])) {
            return;
        }

        $line = '';
        foreach ($result as $key => $value) {
            $line .= "('" . $value['id_trunk'] . "','" . $value['sessiontime'] . "','" . $value['aloc_all_calls'] . "','" . $value['nbcall'] . "','" . $value['buycost'] . "','" . $value['sessionbill'] . "'),";
        }

        $sql = "TRUNCATE pkg_cdr_summary_trunk";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "INSERT INTO pkg_cdr_summary_trunk (id_trunk, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill) VALUES " . substr($line, 0, -1) . ";";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        $sql = "SELECT t.id_trunk,
                count(*) as nbcall_fail
                FROM pkg_cdr_failed t LEFT JOIN pkg_user c ON t.id_trunk = c.id
                GROUP BY t.id_trunk";
        $model = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($model as $key => $value) {
            $sql = "UPDATE pkg_cdr_summary_trunk SET nbcall_fail =" . $value['nbcall_fail'] . " WHERE id_trunk = '" . $value['id_trunk'] . "' ";
            Yii::app()->db->createCommand($sql)->execute();
        }

        $sql = "UPDATE  pkg_cdr_summary_trunk SET asr = (nbcall / ( nbcall_fail + nbcall) ) * 100 ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_trunk SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function dropTables()
    {
        echo $sql = "DROP TABLE pkg_cdr_summary_day, pkg_cdr_summary_day_agent, pkg_cdr_summary_day_trunk, pkg_cdr_summary_day_user, pkg_cdr_summary_month, pkg_cdr_summary_month_trunk, pkg_cdr_summary_month_user, pkg_cdr_summary_trunk,  pkg_cdr_summary_user";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function recreateTables()
    {
        $this->dropTables();
        $this->creationTables();
    }

    public function truncateTables()
    {
        $sql = "TRUNCATE pkg_cdr_summary_day;
                TRUNCATE pkg_cdr_summary_day_agent;
                TRUNCATE pkg_cdr_summary_day_trunk;
                TRUNCATE pkg_cdr_summary_day_user;
                TRUNCATE pkg_cdr_summary_month;
                TRUNCATE pkg_cdr_summary_month_trunk;
                TRUNCATE pkg_cdr_summary_month_user;
                TRUNCATE pkg_cdr_summary_trunk;
                TRUNCATE pkg_cdr_summary_user;";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function creationTables()
    {
        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_day (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    day varchar(10) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    nbcall_fail int(11) DEFAULT NULL,
                    buycost float NOT NULL DEFAULT '0',
                    sessionbill float NOT NULL DEFAULT '0',
                    lucro float DEFAULT NULL,
                    asr float DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY day (day)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_day_user (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    day varchar(10) NOT NULL,
                    id_user int(11) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    buycost float  NULL DEFAULT '0',
                    sessionbill float NULL DEFAULT '0',
                    lucro float NULL DEFAULT '0',
                    isAgent TINYINT( 1 ) NULL DEFAULT NULL,
                    agent_bill FLOAT NOT NULL DEFAULT  '0',
                    PRIMARY KEY (id),
                    KEY day (day),
                    KEY id_user (id_user)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_day_trunk (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    day varchar(10) NOT NULL,
                    id_trunk int(11) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    buycost float NOT NULL DEFAULT '0',
                    sessionbill float NOT NULL DEFAULT '0',
                    lucro float NULL DEFAULT '0',
                    PRIMARY KEY (id),
                    KEY day (day),
                    KEY id_trunk (id_trunk)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_day_agent (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    day varchar(10) NOT NULL,
                    id_user int(11) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    buycost float NOT NULL DEFAULT '0',
                    sessionbill float NOT NULL DEFAULT '0',
                    lucro float NULL DEFAULT '0',
                    PRIMARY KEY (id),
                    KEY day (day),
                    KEY id_user (id_user)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_month (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    month varchar(20) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    buycost float NOT NULL DEFAULT '0',
                    sessionbill float NOT NULL DEFAULT '0',
                    lucro float NULL DEFAULT '0',
                    PRIMARY KEY (id),
                    UNIQUE KEY month (month)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_month_user (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    month varchar(20) NOT NULL,
                    id_user int(11) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    buycost float NOT NULL DEFAULT '0',
                    sessionbill float NOT NULL DEFAULT '0',
                    lucro float NULL DEFAULT '0',
                    isAgent TINYINT( 1 ) NULL DEFAULT NULL ;
                    agent_bill FLOAT NOT NULL DEFAULT  '0';
                    PRIMARY KEY (id),
                    KEY month (month),
                    KEY id_user (id_user)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_month_trunk (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    month varchar(20) NOT NULL,
                    id_trunk int(11) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    buycost float NOT NULL DEFAULT '0',
                    sessionbill float NOT NULL DEFAULT '0',
                    lucro float NULL DEFAULT '0',
                    PRIMARY KEY (id),
                    KEY month (month),
                    KEY id_trunk (id_trunk)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_user (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    id_user int(11) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    nbcall_fail int(11) DEFAULT NULL,
                    buycost float NOT NULL DEFAULT '0',
                    sessionbill float NOT NULL DEFAULT '0',
                    lucro float DEFAULT NULL,
                    asr float DEFAULT NULL,
                    isAgent INT( 11 ) NULL DEFAULT NULL ;
                    agent_bill FLOAT NOT NULL DEFAULT  '0';
                    PRIMARY KEY (id),
                    KEY id_user (id_user)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS pkg_cdr_summary_trunk (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    id_trunk int(11) NOT NULL,
                    sessiontime bigint(25) NOT NULL,
                    aloc_all_calls int(11) NOT NULL,
                    nbcall int(11) NOT NULL,
                    nbcall_fail int(11) DEFAULT NULL,
                    buycost float NOT NULL DEFAULT '0',
                    sessionbill float NOT NULL DEFAULT '0',
                    lucro float DEFAULT NULL,
                    asr float DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY id_trunk (id_trunk)
                    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
        Yii::app()->db->createCommand($sql)->execute();
    }
}
