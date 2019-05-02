<?php
/**
 * Modelo para a tabela "Configuration".
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2018 MagnusSolution. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v3
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 17/08/2012
 */

class Configuration extends Model
{
    protected $_module = 'configuration';
    /**
     * Retorna a classe estatica da model.
     * @return Prefix classe estatica da model.
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return nome da tabela.
     */
    public function tableName()
    {
        return 'pkg_configuration';
    }

    /**
     * @return nome da(s) chave(s) primaria(s).
     */
    public function primaryKey()
    {
        return 'id';
    }

    /**
     * @return array validacao dos campos da model.
     */
    public function rules()
    {
        return array(
            array('config_key', 'required'),
            array('status', 'numerical', 'integerOnly' => true),
            array('config_title, config_key', 'length', 'max' => 100),
            array('config_value', 'length', 'max' => 200),
            array('config_description', 'length', 'max' => 500),
            array('config_group_title', 'length', 'max' => 64),
            array('config_value', 'checkConfg'),
        );
    }

    public function checkConfg($attribute, $params)
    {
        $error = false;
        //validation values

        if ($this->config_key == 'base_language') {
            $valuesAllow        = array('es', 'en', 'pt_BR', 'it');
            $this->config_value = $this->config_value == 'br' ? 'pt_BR' : $this->config_value;
            if (!in_array($this->config_value, $valuesAllow)) {
                $error = true;
            }

            Yii::app()->session['language'] = Yii::app()->language = $this->config_value;

            $this->updateSqlConfig();
        }

        if ($this->config_key == 'template') {
            $valuesAllow = array(
                'green-triton', 'green-classic', 'green-neptune', 'green-crisp',
                'blue-triton', 'blue-triton', 'blue-classic', 'blue-neptune', 'blue-crisp',
                'yellow-triton', 'yellow-classic', 'yellow-neptune', 'yellow-crisp',
                'orange-triton', 'orange-classic', 'orange-neptune', 'orange-crisp',
                'purple-triton', 'purple-classic', 'purple-neptune', 'purple-crisp',
                'gray-triton', 'gray-neptune', 'gray-classic', 'gray-crisp',
                'red-triton', 'red-neptune', 'red-classic', 'red-crisp');

            if (!in_array($this->config_value, $valuesAllow)) {
                $this->addError($attribute, Yii::t('yii', 'ERROR: Invalid option'));
            }
        }

        if ($error) {
            $this->addError($attribute, Yii::t('yii', 'ERROR: Invalid option'));
        }
    }

    public function updateSqlConfig()
    {
        $modelConfig = Configuration::model()->findAll();
        $sql         = '';
        foreach ($modelConfig as $key => $config) {
            if (!preg_match('/config_t/', Yii::t('yii', 'config_title_' . $config->config_key))) {
                $sql .= "UPDATE pkg_configuration SET config_title = '" . Yii::t('yii', 'config_title_' . $config->config_key) . "', config_description = '" . Yii::t('yii', 'config_desc_' . $config->config_key) . "' WHERE  config_key = '" . $config->config_key . "';";
            }
        }
        if (strlen($sql) > 10) {
            Yii::app()->db->createCommand($sql)->execute();
        }

        if ($this->config_value == 'pt_BR') {
            $sql = "UPDATE pkg_configuration SET status = 1  WHERE  config_key = 'portabilidadeUsername';
                UPDATE pkg_configuration SET status = 1  WHERE  config_key = 'portabilidadePassword'";
        } elseif ($this->config_value == 'es' || $this->config_value == 'en') {
            $sql = "UPDATE pkg_configuration SET status = 0  WHERE  config_key = 'portabilidadeUsername';
                UPDATE pkg_configuration SET status = 0  WHERE  config_key = 'portabilidadePassword'";
        }
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function updateSqlConfigEs()
    {
        $sql = array(
            "UPDATE pkg_configuration SET config_title = 'Ramal Voip para Espiar llamadas', config_description = 'Ramal Voip para Espiar llamadas' WHERE  config_key = 'channel_spy'",
            "UPDATE pkg_configuration SET config_title = 'Moneda', config_description = 'Moneda' WHERE  config_key = 'base_currency'",
            "UPDATE pkg_configuration SET config_title = 'Idioma', config_description = 'Opciones \nen Ingles \nes Español \npt_BR Portugues' WHERE config_key = 'base_language'",
            "UPDATE pkg_configuration SET config_title = 'Version', config_description = 'MBilling Version' WHERE  config_key = 'version'",
            "UPDATE pkg_configuration SET config_title = 'Licencia', config_description = 'MBilling Licencia' WHERE  config_key = 'licence'",
            "UPDATE pkg_configuration SET config_title = 'IP Servidor', config_description = 'Ip Servidor Magnus' WHERE  config_key = 'ip_servers'",
            "UPDATE pkg_configuration SET config_title = 'Color Entrono Grafico', config_description = 'Opciones:\ngreen, gray, blue, yellow, red, orange, purple' WHERE  config_key = 'template'",
            "UPDATE pkg_configuration SET config_title = 'País', config_description = 'Allowed values\nUSA United States,\nBRL Brasil,\nARG Argentina,\nNLD Netherlands,\nESP Spanish,\nITA Italy,\nMEX Mexico' WHERE  config_key = 'base_country'",
            "UPDATE pkg_configuration SET config_title = 'Entorno Web, Escritorio', config_description = 'Activar Escritorio, solo en version FULL\n1 - Activo (Solo valido Version FULL)\n0 - Desactivado' WHERE  config_key = 'layout'",
            "UPDATE pkg_configuration SET config_title = 'Fondo Escritorio', config_description = 'Fondo de escritorio, Activo solo en version FULL.' WHERE  config_key = 'wallpaper'",
            "UPDATE pkg_configuration SET config_title = 'SMTP Servidor', config_description = 'SMTP Servidor' WHERE  config_key = 'smtp_host'",
            "UPDATE pkg_configuration SET config_title = 'SMTP Usuario', config_description = 'SMTP Usuario' WHERE  config_key = 'smtp_username'",
            "UPDATE pkg_configuration SET config_title = 'SMTP Password', config_description = 'SMTP Password' WHERE  config_key = 'smtp_password'",
            "UPDATE pkg_configuration SET config_title = 'SMTP Encryption', config_description = 'SMTP Encryption: tls, ssl o en blanco' WHERE  config_key = 'smtp_encryption'",
            "UPDATE pkg_configuration SET config_title = 'SMTP Puerto', config_description = 'SMTP Puerto' WHERE  config_key = 'smtp_port'",
            "UPDATE pkg_configuration SET config_title = 'Email Administrador', config_description = 'Email Para notificaciones' WHERE  config_key = 'admin_email'",
            "UPDATE pkg_configuration SET config_title = 'Envio copia Email admin', config_description = 'Envio copia Email Administrador' WHERE  config_key = 'admin_received_email'",
            "UPDATE pkg_configuration SET config_title = 'Dia Notificaciones', config_description = 'Numero de dias para aviso de Saldo' WHERE  config_key = 'delay_notifications'",
            "UPDATE pkg_configuration SET config_title = 'Redondeo de llamada', config_description = 'Redondeo costo llamada.\n1: Si\n0: No' WHERE  config_key = 'bloc_time_call'",
            "UPDATE pkg_configuration SET config_title = 'Dia de pago de las Ofertas', config_description = 'Numero de dias, antes del vencimiento de la Oferta' WHERE  config_key = 'planbilling_daytopay'",
            "UPDATE pkg_configuration SET config_title = 'Limite de Recarga por Agente', config_description = 'Limite de Recarga Agente-Cliente' WHERE  config_key = 'agent_limit_refill'",
            "UPDATE pkg_configuration SET config_title = 'Archivo Cdr', config_description = 'Numero de meses, maximo 10Meses.' WHERE  config_key = 'archive_call_prior_x_month'",
            "UPDATE pkg_configuration SET config_title = 'Pagos, Valores Aceptados', config_description = 'Valores Aceptados, No desponibles en la version FREE' WHERE  config_key = 'purchase_amount'",
            "UPDATE pkg_configuration SET config_title = 'Decimales', config_description = 'Numero de decimales a tener en cuenta.' WHERE  config_key = 'decimal_precision'",
            "UPDATE pkg_configuration SET config_title = 'Paypal activo para nuevos clientes', config_description = 'Paypal activo para nuevos clientes. \n\n0 - Desactivado (RECOMENDADO )\n1 - Activado' WHERE  config_key = 'paypal_new_user'",
            "UPDATE pkg_configuration SET status = 0  WHERE  config_key = 'portabilidadeUsername'",
            "UPDATE pkg_configuration SET status = 0  WHERE  config_key = 'portabilidadePassword'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Llamada Contestada', config_description = 'Si se activa, el MBilling responde a la llamada.\nDefault: 0' WHERE  config_key = 'answer_call' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - User DNID', config_description = 'Si el cliente no necesita programación activa de nuevo el número que desea llamar después de introducir el PIN.\n\n1 - Activado (DEFECTO)\n0 - Desactivado' WHERE  config_key = 'use_dnid' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Avisos de audio', config_description = 'Avisos de Audio, Si esta disabilidado, MBilling envia code 603.\n\n1 - Ativo\n0 - Desativado\n' WHERE  config_key = 'play_audio' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Intro Prompt', config_description = 'Para especificar un mensaje para dar inicio de las llamadas' WHERE  config_key = 'intro_prompt' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Grabacion de llamadas', config_description = 'Activa la grabacion de llamadas, para los clientes.\nPRECAUCION, ESTA OPCION REQUIERE UN ALTO RENDIMIENTO DE NUESTRO SERVIDOR.\n\n0: Desactivado\n1: Activado' WHERE  config_key = 'record_call' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Prefijo Internacional', config_description = 'Prefijos para quitar, Ejemplo, llamada a España 00349x., prefijo a quitar 00' WHERE  config_key = 'international_prefixes' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Locucion Precio de Venta', config_description = 'Locucion Precio de Vanta de Tarifa.\n\n0 - No\n1 - Si' WHERE  config_key = 'say_rateinitial' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Locucion Duracion', config_description = 'Locucion, de la cantidad de tiempo que el cliente puede llamar.\n\n0 - No\n1 - Si' WHERE  config_key = 'say_timetocall' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Autentificacion por Caller-Id', config_description = 'Activa la Autentificacion por CallerID.\n\n0 - Desactivado\n1 - Activado' WHERE  config_key = 'cid_enable' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Conmutacion por error LCR/LCD', config_description = 'Si habilita, MBilling va a cursar llamada, por el mas barato' WHERE  config_key = 'failover_lc_prefix' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Parametros comando Dial ', config_description = 'Mas info: http://voip-info.org/wiki-Asterisk+cmd+dial' WHERE  config_key = 'dialcommand_param' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - LLamadas Internas, Parametros comando Dial ', config_description = 'Parametros de llamada entre usuario.\n\nby defecto (3600000  =  1HOUR MAX CALL).' WHERE  config_key = 'dialcommand_param_sipiax_friend' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Parametros del Parametro DID', config_description = 'Parametros DID' WHERE  config_key = 'dialcommand_param_call_2did' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Conmutacion por error, Limite de Reintentos', config_description = 'Definir el número de tiempo que queremos autorizar la investigación del TRONCAL de conmutación por error cuando falla una llamada' WHERE  config_key = 'failover_recursive_limit' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Número de intentos', config_description = 'Número de intentos para marcar el número\n Valor Minimo 1' WHERE  config_key = 'number_try' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Llamadas Salientes', config_description = 'Marcacion Llamadas Salientes<br>YES -> SIP/number@trunk - NO  SIP/trunk/number<br>Ambos deben trabajar exactamente igual, pero yo he experimentado uno de los casos, cuando la puerta de enlace estaba apoyando número@TRUNK, Asi que en caso de problemas, probarlo.' WHERE  config_key = 'switchdialcommand' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 1 - Locucion Saldo Despues de la Llamada', config_description = 'Locucion de saldo restantes en la cuenta despues de la llamada\n\n0 - No\n1 - Si' WHERE  config_key = 'say_balance_after_call' AND config_group_title = 'agi-conf1'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Respuesta de llamada', config_description = 'Si esta activa MBilling Responde a la llamada que inicia.\nDefault: 0' WHERE  config_key = 'answer_call' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - User DNID', config_description = 'Si el cliente no necesita programación activa de nuevo el número que desea llamar después de introducir el PIN.\n\n1 - Enable (DEFAULT)\n0 - Disable' WHERE  config_key = 'use_dnid' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Avisos de audio', config_description = 'Avisos de Audio, Si esta disabilidado, MBilling envia code 603.\n\n1 - Ativo\n0 - Desativado\n' WHERE  config_key = 'play_audio' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Intro Prompt', config_description = 'Para especificar un mensaje para dar inicio de las llamadas' WHERE  config_key = 'intro_prompt' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Grabacion de llamadas', config_description = 'Activa la grabacion de llamadas, para los clientes.\nPRECAUCION, ESTA OPCION REQUIERE UN ALTO RENDIMINENTO DE NUESTRO SERVIDOR.\n\n0: Desactivado\n1: Activado' WHERE  config_key = 'record_call' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Prefijo Internacional', config_description = 'Prefijos para quitar, Ejemplo, llamada a España 00349x., prefijo a quitar 00' WHERE  config_key = 'international_prefixes' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Locucion Precio de Venta', config_description = 'Locucion Precio de Vanta de Tarifa.\n\n0 - No\n1 - Yes' WHERE  config_key = 'say_rateinitial' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Locucion Duracion', config_description = 'Locucion, de la cantidad de tiempo que el cliente puede llamar.\n\n0 - No\n1 - Si' WHERE  config_key = 'say_timetocall' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Autentificacion por Caller-Id', config_description = 'Activa la Autentificacion por CallerID\n\n0 - Desactivado\n1 - Activado' WHERE  config_key = 'cid_enable' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Conmutacion por error LCR/LCD', config_description = 'Si habilita, MBilling va a cursar llamada, por el mas barato' WHERE  config_key = 'failover_lc_prefix' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Parametros comando Dial', config_description = 'Mas info: http://voip-info.org/wiki-Asterisk+cmd+dial' WHERE  config_key = 'dialcommand_param' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - LLamadas Internas, Parametros comando Dial ', config_description = 'Parametros de llamada entre usuario.\n\nby defecto (3600000  =  1HOUR MAX CALL).' WHERE  config_key = 'dialcommand_param_sipiax_friend' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Parametros del Parametro DID', config_description = 'Parametros DID' WHERE  config_key = 'dialcommand_param_call_2did' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Conmutacion por error, Limite de Reintentos', config_description = 'Definir el número de tiempo que queremos autorizar la investigación del TRONCAL de conmutación por error cuando falla una llamada' WHERE  config_key = 'failover_recursive_limit' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Número de intentos', config_description = 'Número de intentos para marcar el número\n Valor Minimo 1' WHERE  config_key = 'number_try' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Llamadas Salientes', config_description = 'Marcacion Llamadas Salientes<br>YES -> SIP/number@trunk - NO  SIP/trunk/number<br>Ambos deben trabajar exactamente igual, pero yo he experimentado uno de los casos, cuando la puerta de enlace estaba apoyando número@TRUNK, Asi que en caso de problemas, probarlo.' WHERE  config_key = 'switchdialcommand' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 2 - Locucion Saldo Despues de la Llamada', config_description = 'Locucion de saldo restantes en la cuenta despues de la llamada\n\n0 - No\n1 - Si' WHERE  config_key = 'say_balance_after_call' AND config_group_title = 'agi-conf2'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Respuesta de llamada', config_description = 'Si esta activa MBilling Responde a la llamada que inicia.\nDefault: 0' WHERE  config_key = 'answer_call' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - User DNID', config_description = 'Si el cliente no necesita programación activa de nuevo el número que desea llamar después de introducir el PIN.\n\n1 - Enable (DEFAULT)\n0 - Disable' WHERE  config_key = 'use_dnid' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Avisos de audio', config_description = 'Avisos de Audio, Si esta disabilidado, MBilling envia code 603.\n\n1 - Ativo\n0 - Desativado\n' WHERE  config_key = 'play_audio' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Intro Prompt', config_description = 'Para especificar un mensaje para dar inicio de las llamadas' WHERE  config_key = 'intro_prompt' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Grabacion de llamadas', config_description = 'Activa la grabacion de llamadas, para los clientes.\nPRECAUCION, ESTA OPCION REQUIERE UN ALTO RENDIMINENTO DE NUESTRO SERVIDOR.\n\n0: Desactivado\n1: Activado' WHERE  config_key = 'record_call' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Prefijo Internacional', config_description = 'Prefijos para quitar, Ejemplo, llamada a España 00349x., prefijo a quitar 00' WHERE  config_key = 'international_prefixes' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Locucion Precio de Venta', config_description = 'Locucion Precio de Vanta de Tarifa.\n\n0 - No\n1 - Yes' WHERE  config_key = 'say_rateinitial' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Locucion Duracion', config_description = 'Locucion, de la cantidad de tiempo que el cliente puede llamar.\n\n0 - No\n1 - Si' WHERE  config_key = 'say_timetocall' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Autentificacion por Caller-Id', config_description = 'Activa la Autentificacion por CallerID\n\n0 - Desactivado\n1 - Activado' WHERE  config_key = 'cid_enable' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Conmutacion por error LCR/LCD', config_description = 'Si habilita, MBilling va a cursar llamada, por el mas barato' WHERE  config_key = 'failover_lc_prefix' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Parametros comando Dial', config_description = 'Mas info: http://voip-info.org/wiki-Asterisk+cmd+dial' WHERE  config_key = 'dialcommand_param' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - LLamadas Internas, Parametros comando Dial ', config_description = 'Parametros de llamada entre usuario.\n\nby defecto (3600000  =  1HOUR MAX CALL).' WHERE  config_key = 'dialcommand_param_sipiax_friend' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Parametros del Parametro DID', config_description = 'Parametros DID' WHERE  config_key = 'dialcommand_param_call_2did' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Conmutacion por error, Limite de Reintentos', config_description = 'Definir el número de tiempo que queremos autorizar la investigación del TRONCAL de conmutación por error cuando falla una llamada' WHERE  config_key = 'failover_recursive_limit' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Número de intentos', config_description = 'Número de intentos para marcar el número\n Valor Minimo 1' WHERE  config_key = 'number_try' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Llamadas Salientes', config_description = 'Marcacion Llamadas Salientes<br>YES -> SIP/number@trunk - NO  SIP/trunk/number<br>Ambos deben trabajar exactamente igual, pero yo he experimentado uno de los casos, cuando la puerta de enlace estaba apoyando número@TRUNK, Asi que en caso de problemas, probarlo.' WHERE  config_key = 'switchdialcommand' AND config_group_title = 'agi-conf3'",
            "UPDATE pkg_configuration SET config_title = 'AGI 3 - Locucion Saldo Despues de la Llamada', config_description = 'Locucion de saldo restantes en la cuenta despues de la llamada\n\n0 - No\n1 - Si' WHERE  config_key = 'say_balance_after_call' AND config_group_title = 'agi-conf3'",
        );
        foreach ($sql as $value) {
            Yii::app()->db->createCommand($value)->execute();
        }
    }

}
