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
class Tts
{

    public static function create($string, $file)
    {
        $config = LoadConfig::getConfig();
        $name   = urlencode(utf8_encode($string));
        print "VERBOSE \"TTS create file $name  \"\n";
        //http://api.voicerss.org/?key=0ed8d233c8534591a7abf4b620606bc2&src=Adilson&hl=pt-br
        $tts_url = preg_replace('/\$name/', $name, $config['global']['tts_url']);
        print "VERBOSE \"TTS $tts_url \"\n";
        if (preg_match("/ttsgo/", $tts_url)) {

            $ch = curl_init();
            //Caso tenha dificuldade com a requisição via HTTPS, descomente a linha abaixo
            //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_URL, $tts_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $retorno = curl_exec($ch);

            $objJson = json_decode($retorno);

            $agi->verbose(print_r($objJson->url, true));
            $fp = fopen('/tmp/' . $file . '.wav', 'w');
            fwrite($fp, file_get_contents($objJson->url));
            fclose($fp);
            exec('sox /tmp/' . $file . '.wav -c 1 -r 8000 /tmp/' . $file . '.sln && rm -rf /tmp/' . $file . '.wav');

        } else {

            //https://translate.google.com/translate_tts?ie=UTF-8&q=$name&tl=pt-BR&total=1&idx=0&textlen=5&client=tw-ob&tk=$token
            if (preg_match("/google/", $tts_url)) {
                $token   = Tts::make_token($name);
                $tts_url = preg_replace('/\$token/', $token, $tts_url);
            }
            print "VERBOSE \"TTS $file \"\n";
            exec("wget -q -U Mozilla -O \"/tmp/$file.mp3\" \"$tts_url\"");
            exec("mpg123 -w /tmp/$file.wav /tmp/$file.mp3 && rm -rf /tmp/$file.mp3");
            exec("sox -v 2.0 /tmp/$file.wav /tmp/$file2.wav && rm -rf /tmp/$file.wav");
            exec("sox /tmp/$file2.wav -c 1 -r 8000 /tmp/$file.wav && rm -rf /tmp/$file2.wav ");
        }
        print "VERBOSE \"TTS $file \"\n";
        return '/tmp/' . $file;
    }

    public static function make_token($line)
    {
        $text  = $line;
        $time  = round(time() / 3600);
        $chars = unpack('C*', $text);
        $stamp = $time;

        foreach ($chars as $key => $char) {
            $stamp = Tts::make_rl($stamp + $char, '+-a^+6');
        }

        $stamp = Tts::make_rl($stamp, '+-3^+b+-f');

        if ($stamp < 0) {
            $stamp = ($stamp & 2147483647) + 2147483648;
        }
        $stamp %= pow(10, 6);
        return ($stamp . '.' . ($stamp ^ $time));

    }

    public static function make_rl($num, $str)
    {
        for ($i = 0; $i < strlen($str) - 2; $i += 3) {
            $d = substr($str, $i + 2, 1);
            if (ord($d) >= ord('a')) {
                $d = ord($d) - 87;
            } else {
                $d = round($d);
            }
            if (substr($str, $i + 1, 1) == '+') {
                $d = $num >> $d;
            } else {
                $d = $num << $d;
            }
            if (substr($str, $i, 1) == '+') {
                $num = $num + $d & 4294967295;
            } else {
                $num = $num ^ $d;
            }
        }
        return $num;
    }
}
