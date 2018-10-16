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
0 8,10,12,14,16,18,20,22 * * * php /var/www/html/mbilling/cron.php SummaryTablesCdr processCdrToday
 */

class SummaryTablesCdrCommand extends CConsoleCommand
{
    public function run($args)
    {
        if (isset($args[0])) {
            try {
                $this->$args[0]();
            } catch (Exception $e) {
                $class_methods = get_class_methods('SummaryTablesCdrCommand');
                echo "Availables comands";
                for ($i = 1; $i < 14; $i++) {
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
    public function processCdrToday()
    {
        $today  = date("Y-m-d");
        $filter = "starttime > '$today'";

        //perday
        $sql = "DELETE FROM pkg_cdr_summary_day WHERE day = '$today' ";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perDay($filter);
    }
    public function processCdrLast30Days()
    {
        $lastMonth = date("Y-m-d", strtotime("- 30 day"));
        $filter    = "starttime > '$lastMonth 23:59:59'";

        //perday
        $sql = "DELETE FROM pkg_cdr_summary_day WHERE day > '$lastMonth' ";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perDay($filter);

        //perdayuser
        $sql = "DELETE FROM pkg_cdr_summary_day_user WHERE day > '$lastMonth' ";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perDayUser($filter);

        //perdaytrunk
        $sql = "DELETE FROM pkg_cdr_summary_day_trunk WHERE day > '$lastMonth' ";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perDayTrunk($filter);

        //perDayAgent
        $sql = "DELETE FROM pkg_cdr_summary_day_agent WHERE day > '$lastMonth' ";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perDayAgent($filter);

        //permonth
        $filter = "starttime > '" . date("Y-m") . "-01'";

        $sql = "DELETE FROM pkg_cdr_summary_month WHERE month = '" . date("Ym") . "' ";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perMonth($filter);

        $sql = "DELETE FROM pkg_cdr_summary_month_user WHERE month = '" . date("Ym") . "' ";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perMonthUser($filter);

        $sql = "DELETE FROM pkg_cdr_summary_month_trunk WHERE month = '" . date("Ym") . "' ";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perMonthTrunk($filter);

        //perUser
        $sql = "TRUNCATE pkg_cdr_summary_user";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perUser();

        //perTrunk
        $sql = "TRUNCATE pkg_cdr_summary_trunk";
        Yii::app()->db->createCommand($sql)->execute();
        $this->perTrunk();

    }
    public function perDay($filter = 1)
    {
        $sql = "INSERT INTO pkg_cdr_summary_day (day, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT DATE(starttime) AS day,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter GROUP BY day ORDER BY day DESC";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

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
        $sql       = "SELECT id FROM pkg_user";
        $modelUser = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($modelUser as $key => $user) {
            $sql = "INSERT INTO pkg_cdr_summary_day_user (day, id_user, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT DATE(starttime) AS day,
                id_user,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter AND id_user = '" . $user['id'] . "' GROUP BY day ORDER BY day DESC";

            try {
                Yii::app()->db->createCommand($sql)->execute();
            } catch (Exception $e) {
                echo $sql . "\n";
                print_r($e->getMessage());
            }
        }

        $sql = "UPDATE  pkg_cdr_summary_day_user SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perDayTrunk($filter = 1)
    {
        $sql        = "SELECT id FROM pkg_trunk";
        $modelTrunk = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($modelTrunk as $key => $trunk) {
            $sql = "INSERT INTO pkg_cdr_summary_day_trunk (day, id_trunk, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT DATE(starttime) AS day,
                id_trunk,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter AND id_trunk = '" . $trunk['id'] . "' GROUP BY day ORDER BY day DESC";

            try {
                Yii::app()->db->createCommand($sql)->execute();
            } catch (Exception $e) {
                //
            }
        }

        $sql = "UPDATE  pkg_cdr_summary_day_trunk SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perDayAgent($filter = 1)
    {

        $sql        = "SELECT * FROM pkg_user WHERE id_user > 1";
        $modelAgent = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($modelAgent as $key => $user) {

            $sql = "INSERT INTO pkg_cdr_summary_day_agent (day, id_user, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT DATE(starttime) AS day,
                " . $user['id_user'] . ",
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t
                JOIN pkg_user c ON t.id_user = c.id
                WHERE  $filter AND c.id_user = '" . $user['id_user'] . "' GROUP BY day ORDER BY day DESC";
            try {
                Yii::app()->db->createCommand($sql)->execute();
            } catch (Exception $e) {
                //
            }
        }

        $sql = "UPDATE  pkg_cdr_summary_day_agent SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perMonth($filter = 1)
    {

        $sql = "INSERT INTO pkg_cdr_summary_month (month, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT EXTRACT(YEAR_MONTH FROM starttime) AS month,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter GROUP BY month ORDER BY month DESC";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        $sql = "UPDATE  pkg_cdr_summary_month SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perMonthUser($filter = 1)
    {

        $sql       = "SELECT id FROM pkg_user";
        $modelUser = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($modelUser as $key => $user) {

            $sql = "INSERT INTO pkg_cdr_summary_month_user (month, id_user, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT EXTRACT(YEAR_MONTH FROM starttime) AS month,
                id_user,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter AND  id_user = '" . $user['id'] . "' GROUP BY month ORDER BY month DESC";

            try {
                Yii::app()->db->createCommand($sql)->execute();
            } catch (Exception $e) {
                //
            }
        }

        $sql = "UPDATE  pkg_cdr_summary_month_user SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perMonthTrunk($filter = 1)
    {

        $sql   = "SELECT id FROM pkg_trunk";
        $model = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($model as $key => $trunk) {

            $sql = "INSERT INTO pkg_cdr_summary_month_trunk (month, id_trunk, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT EXTRACT(YEAR_MONTH FROM starttime) AS month,
                id_trunk,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t WHERE $filter AND id_trunk = '" . $trunk['id'] . "' GROUP BY month ORDER BY month DESC";
            try {
                Yii::app()->db->createCommand($sql)->execute();
            } catch (Exception $e) {
                //
            }
        }

        $sql = "UPDATE  pkg_cdr_summary_month_trunk SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perUser($filter = 1)
    {
        $sql = "INSERT INTO pkg_cdr_summary_user (id_user, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT t.id_user,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t
                JOIN pkg_user c ON t.id_user = c.id
                GROUP BY t.id_user";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        $sql = "SELECT t.id_user,
                count(*) as nbcall_fail
                FROM pkg_cdr_failed t JOIN pkg_user c ON t.id_user = c.id
                GROUP BY t.id_user";
        $model = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($model as $key => $value) {
            $sql = "UPDATE pkg_cdr_summary_user SET nbcall_fail =" . $value['nbcall_fail'] . " WHERE id_user = '" . $value['id_user'] . "' ";
            Yii::app()->db->createCommand($sql)->execute();
        }

        $sql = "UPDATE  pkg_cdr_summary_user SET asr = (nbcall / ( nbcall_fail + nbcall) ) * 100 ";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE  pkg_cdr_summary_user SET lucro = sessionbill - buycost";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function perTrunk($filter = 1)
    {
        $sql = "INSERT INTO pkg_cdr_summary_trunk (id_trunk, sessiontime, aloc_all_calls, nbcall, buycost, sessionbill)

                SELECT t.id_trunk,
                sum(sessiontime) AS sessiontime,
                SUM(sessiontime) / COUNT(*) AS aloc_all_calls,
                count(*) as nbcall,
                sum(buycost) AS buycost,
                sum(sessionbill) AS sessionbill
                FROM pkg_cdr t
                JOIN pkg_trunk c ON t.id_trunk = c.id
                GROUP BY t.id_trunk";
        try {
            Yii::app()->db->createCommand($sql)->execute();
        } catch (Exception $e) {
            //
        }

        $sql = "SELECT t.id_trunk,
                count(*) as nbcall_fail
                FROM pkg_cdr_failed t JOIN pkg_user c ON t.id_trunk = c.id
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
        $sql = "DROP TABLE pkg_cdr_summary_day_agent, pkg_cdr_summary_day_trunk, pkg_cdr_summary_day_user, pkg_cdr_summary_month, pkg_cdr_summary_month_trunk, pkg_cdr_summary_month_user";
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
                TRUNCATE pkg_cdr_summary_month_user;";
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
