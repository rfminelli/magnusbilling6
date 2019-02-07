<?php
/**
 * Modelo para a tabela "Plan".
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v3
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 24/07/2012
 */

class Plan extends Model
{
    protected $_module = 'plan';
    /**
     * Retorna a classe estatica da model.
     * @return SubModule classe estatica da model.
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
        return 'pkg_plan';
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
            array('name', 'required'),
            array('id_user, tariff_limit, play_audio,techprefix, lcrtype, signup, portabilidadeMobile, portabilidadeFixed', 'numerical', 'integerOnly' => true),
            array('name, ini_credit', 'length', 'max' => 50),
            array('techprefix', 'length', 'max' => 5),
            array('name', 'unique', 'caseSensitive' => 'false'),
        );
    }

    /*
     * @return array regras de relacionamento.
     */
    public function relations()
    {
        return array(
            'idUser' => array(self::BELONGS_TO, 'User', 'id_user'),
        );
    }

    public function searchTariff($id_plan, $destination)
    {
        //return the maximun length of prefix
        $modelPrefixLength = PrefixLength::model()->find('code = :key', array(':key' => substr($destination, 0, 2)));

        if (count($modelPrefixLength)) {
            $max_len_prefix = $modelPrefixLength->length;
            $prefixclause   = '((';
            while ($max_len_prefix >= 1) {
                $prefixclause .= "prefix='" . substr($destination, 0, $max_len_prefix) . "' OR ";
                $max_len_prefix--;
            }

            $prefixclause = substr($prefixclause, 0, -3) . ")";

            $prefixclause .= " OR (prefix LIKE '&_%' ESCAPE '&' AND '" . $destination . "'
                REGEXP REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT('^', prefix, '$'),
                'X', '[0-9]'), 'Z', '[1-9]'), 'N', '[2-9]'), '.', '.+'), '_', '')))";

        } else {
            $max_len_prefix = 6;
            $prefixclause   = "  (prefix LIKE '&_%' ESCAPE '&' AND '" . $destination . "'
                        REGEXP REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT('^', prefix, '$'),
                        'X', '[0-9]'), 'Z', '[1-9]'), 'N', '[2-9]'), '.', '.+'), '_', ''))";
        }

        $sql = "SELECT lcrtype, pkg_plan.id AS id_plan, pkg_prefix.prefix AS dialprefix,
                pkg_plan.name, pkg_rate.id_prefix, pkg_rate.id AS id_rate, buyrate,  buyrateinitblock buyrateinitblock,
                buyrateincrement, rateinitial, initblock, billingblock, connectcharge, disconnectcharge disconnectcharge,
                pkg_rate.id_trunk AS id_trunk, pkg_trunk.trunkprefix AS rc_trunkprefix, pkg_trunk.directmedia AS rc_directmedia,
                pkg_trunk.providertech AS rc_providertech ,pkg_trunk.providerip AS rc_providerip,
                pkg_trunk.removeprefix AS rc_removeprefix, pkg_trunk.failover_trunk AS rt_failover_trunk,
                pkg_trunk.addparameter AS rt_addparameter_trunk, pkg_trunk.status, pkg_trunk.inuse, pkg_trunk.maxuse,
                pkg_trunk.allow_error,pkg_trunk.if_max_use, pkg_rate.additional_grace AS additional_grace, minimal_time_charge,
                minimal_time_buy, pkg_trunk.link_sms, pkg_trunk.user user, pkg_trunk.secret, package_offer ,
                pkg_trunk.id_provider, pkg_provider.credit_control, pkg_provider.credit
                FROM pkg_plan
                LEFT JOIN pkg_rate ON pkg_plan.id = pkg_rate.id_plan
                LEFT JOIN pkg_trunk AS pkg_trunk ON pkg_trunk.id = pkg_rate.id_trunk
                LEFT JOIN pkg_prefix ON pkg_rate.id_prefix = pkg_prefix.id
                LEFT JOIN pkg_provider ON pkg_trunk.id_provider = pkg_provider.id
                WHERE pkg_plan.id=$id_plan AND pkg_rate.status = 1 AND $prefixclause
                ORDER BY LENGTH( prefix ) DESC ";
        return array($sql, Yii::app()->db->createCommand($sql)->queryAll());
    }
}
