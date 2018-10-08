<?php
/**
 * Modelo para a tabela "Ivr".
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
 * 24/09/2012
 */

class Ivr extends Model
{
    protected $_module = 'ivr';
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
        return 'pkg_ivr';
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
            array('id_user, direct_extension', 'numerical', 'integerOnly' => true),
            array('monFriStart, monFriStop, satStart, satStop, sunStart, sunStop', 'length', 'max' => 5),
            array('name, option_0, option_1, option_2, option_3, option_4, option_5, option_6, option_7, option_8, option_9, option_10', 'length', 'max' => 50),
            array('option_out_0, option_out_1, option_out_2, option_out_3, option_out_4, option_out_5, option_out_6, option_out_7, option_out_8, option_out_9, option_out_10', 'length', 'max' => 50),
        );
    }

    /**
     * @return array regras de relacionamento.
     */
    public function relations()
    {
        return array(
            'idUser' => array(self::BELONGS_TO, 'User', 'id_user'),
        );
    }

}
