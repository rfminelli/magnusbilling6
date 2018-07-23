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
class AsteriskStartStopCommand extends ConsoleCommand
{
    public function run($args)
    {
        echo $this->processExists('asterisk');
    }

    public function processExists($processName)
    {
        $exists  = false;
        $command = "ps -A | grep -i $processName | grep -v grep", $pids;
        echo $command;
        exec($command);
        if (count($pids) > 0) {
            $exists = true;
        }
        return $exists;
    }
}
