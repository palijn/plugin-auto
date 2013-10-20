<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Automobile class for galette Auto plugin
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugins
 * @package   GaletteAuto
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-16
 */

namespace GaletteAuto;

use Analog\Analog as Analog;
use Galette\Entity\Adherent;

/**
 * Automobile Transmissions class for galette Auto plugin
 *
 * @category  Plugins
 * @name      Auto
 * @package   GaletteAuto
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-16
 */
class Auto
{
    const TABLE = 'cars';
    const PK = 'id_car';

    private $_fields = array(
        'id_car'                        => 'integer',
        'car_name'                      => 'string',
        'car_registration'              => 'string',
        'car_first_registration_date'   => 'date',
        'car_first_circulation_date'    => 'date',
        'car_mileage'                   => 'integer',
        'car_comment'                   => 'string',
        'car_creation_date'             => 'date',
        'car_chassis_number'            => 'string',
        'car_seats'                     => 'integer',
        'car_horsepower'                => 'integer',
        'car_engine_size'               => 'integer',
        'car_fuel'                      => 'integer',
        Color::PK                       => 'integer',
        Body::PK                        => 'integer',
        State::PK                       => 'integer',
        Transmission::PK                => 'integer',
        Finition::PK                    => 'integer',
        Model::PK                       => 'integer',
        Adherent::PK                    => 'integer'
    );

    private $_id;                       //identifiant
    private $_registration;             //immatriculation
    private $_name;                     //petit nom
    private $_first_registration_date;  //date de première immatriculation
    private $_first_circulation_date;   //date de prmière mise en service
    private $_mileage;                  //kilométrage
    private $_comment;                  //commentaire
    private $_chassis_number;           //numéro de chassis
    private $_seats;                    //nombre de places
    private $_horsepower;               //puissance fiscale
    private $_engine_size;              //cylindrée
    private $_creation_date;            //date de création
    private $_fuel;                     //carburant

    //External objects
    private $_picture;                  //photo de la voiture
    private $_finition;                 //niveau de finition
    private $_color;                    //couleur
    private $_model;                    //modèle
    private $_transmission;             //type de transmission
    private $_body;                     //carrosserie
    private $_history;                  //historique
    private $_owner;                    //propriétaire actuel
    private $_state;                    //état actuel

    const FUEL_PETROL = 1;
    const FUEL_DIESEL = 2;
    const FUEL_GAS = 3;
    const FUEL_ELECTRICITY = 4;
    const FUEL_BIO = 5;

    private $_propnames;                //textual properties names

    //do we have to fire an history entry?
    private $_fire_history = false;

    //internal properties (not updatable outside the object)
    private $_internals = array (
        'id',
        'creation_date',
        'history',
        'picture',
        'propnames',
        'internals',
        'fields',
        'fire_history'
    );

    /**
    * Default constructor
    *
    * @param ResultSet $args A resultset row to load
    */
    public function __construct($args = null)
    {
        $this->_propnames = array(
            'name'                      => _T("name"),
            'model'                     => _T("model"),
            'registration'              => _T("registration"),
            'first_registration_date'   => _T("first registration date"),
            'first_circulation_date'    => _T("first circulation date"),
            'mileage'                   => _T("mileage"),
            'seats'                     => _T("seats"),
            'horsepower'                => _T("horsepower"),
            'engine_size'               => _T("engine size"),
            'color'                     => _T("color"),
            'state'                     => _T("state"),
            'finition'                  => _T("finition"),
            'transmission'              => _T("transmission"),
            'body'                      => _T("body")
        );

        $this->_model = new Model();
        $this->_color = new Color();
        $this->_state = new State();

        $deps = array(
            'picture'   => false,
            'groups'    => false,
            'dues'      => false
        );
        $this->_owner = new Adherent(null, $deps);
        $this->_transmission = new Transmission();
        $this->_finition = new Finition();
        $this->_picture = new Picture();
        $this->_body = new Body();
        $this->_history = new History();
        if ( is_object($args) ) {
            $this->_loadFromRS($args);
        }
    }

    /**
    * Loads a car from its id
    *
    * @param integer $id the identifiant for the car to load
    *
    * @return boolean
    */
    public function load($id)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . AUTO_PREFIX . self::TABLE)
                ->where(self::PK . ' = ?', $id);

            $this->_loadFromRS($select->query()->fetch());
            return true;
        } catch (\Exception $e) {
            Analog::log(
                '[' . get_class($this) . '] Cannot load car form id `' . $id .
                '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
    * Populate object from a resultset row
    *
    * @param ResultSet $r a resultset row
    *
    * @return void
    */
    private function _loadFromRS($r)
    {
        $pk = self::PK;
        $this->_id = $r->$pk;
        $this->_registration = $r->car_registration;
        $this->_name = $r->car_name;
        $this->_first_registration_date = $r->car_first_registration_date;
        $this->_first_circulation_date = $r->car_first_circulation_date;
        $this->_mileage = $r->car_mileage;
        $this->_comment = $r->car_comment;
        $this->_chassis_number = $r->car_chassis_number;
        $this->_seats = $r->car_seats;
        $this->_horsepower = $r->car_horsepower;
        $this->_engine_size = $r->car_engine_size;
        $this->_creation_date = $r->car_creation_date;
        $this->_fuel = $r->car_fuel;
        //External objects
        $this->_picture = new Picture((int)$this->_id);
        $fpk = Finition::PK;
        $this->_finition->load((int)$r->$fpk);
        $cpk = Color::PK;
        $this->_color->load((int)$r->$cpk);
        $mpk = Model::PK;
        $this->_model->load((int)$r->$mpk);
        $tpk = Transmission::PK;
        $this->_transmission->load((int)$r->$tpk);
        $bpk = Body::PK;
        $this->_body->load((int)$r->$bpk);
        $opk = Adherent::PK;
        $this->_owner->load((int)$r->$opk);
        $spk = State::PK;
        $this->_state->load((int)$r->$spk);
        $this->_history->load((int)$this->_id);
    }

    /**
    * Return the list of available fuels
    *
    * @return array
    */
    public function listFuels()
    {
        $f = array(
            self::FUEL_PETROL       => _T("Petrol"),
            self::FUEL_DIESEL       => _T("Diesel"),
            self::FUEL_GAS          => _T("Gas"),
            self::FUEL_ELECTRICITY  => _T("Electricity"),
            self::FUEL_BIO          => _T("Bio")
        );
        return $f;
    }

    /**
    * Stores the vehicle in the database
    *
    * @param boolean $new true if it's a new record, false to update on
    *                       that already exists. Defaults to false
    *
    * @return boolean
    */
    public function store($new = false)
    {
        global $zdb, $hist;

        if ( $new ) {
            $this->_creation_date = date('Y-m-d');
        }

        try {
            $values = array();

            foreach ( $this->_fields as $k=>$v ) {
                switch ( $k ) {
                case self::PK:
                    break;
                case Color::PK:
                    $values[$k] = $this->_color->id;
                    break;
                case Body::PK:
                    $values[$k] = $this->_body->id;
                    break;
                case State::PK:
                    $values[$k] = $this->_state->id;
                    break;
                case Transmission::PK:
                    $values[$k] = $this->_transmission->id;
                    break;
                case Finition::PK:
                    $values[$k] = $this->_finition->id;
                    break;
                case Model::PK:
                    $values[$k] = $this->_model->id;
                    break;
                case Adherent::PK:
                    $values[$k] = $this->_owner->id;
                    break;
                default:
                    $propName = substr($k, 3, strlen($k));
                    switch($v){
                    case 'string':
                    case 'date':
                        $values[$k] = $this->$propName;
                        break;
                    case 'integer':
                        $values[$k] = (
                            ($this->$propName != 0 && $this->$propName != '')
                                ? $this->$propName
                                : new \Zend_Db_Expr('NULL')
                        );
                        break;
                    default:
                        $values[$k] = $this->$propName;
                        break;
                    }
                    break;
                }
            }

            if ( $new === true ) {
                $add = $zdb->db->insert(
                    PREFIX_DB . AUTO_PREFIX . self::TABLE,
                    $values
                );
                if ( $add > 0) {
                    $this->_id = $zdb->db->lastInsertId();
                    // logging
                    $hist->add(
                        _T("New car added"),
                        strtoupper($this->_name)
                    );
                } else {
                    $hist->add('Fail to add new car.');
                    throw new Exception(
                        'An error occured inserting new car!'
                    );
                }
            } else {
                $edit = $zdb->db->update(
                    PREFIX_DB . AUTO_PREFIX . self::TABLE,
                    $values,
                    self::PK . '=' . $this->_id
                );
                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ( $edit > 0 ) {
                    $hist->add(
                        _T("Car updated"),
                        strtoupper($this->_name)
                    );
                }
            }

            //if all goes well, we check to add an entry into car's history
            $h = $this->_history->getLatest();
            if ( $h !== false ) {
                foreach ( $h as $k=>$v ) {
                    if ( $k != 'history_date' && $this->$k != $v ) {
                        //if one has been modified, we flag to add an entry event
                        $this->_fire_history = true;
                        break;
                    }
                }
            } else if ( !$new ) {
                //no history entry... yet! Let's create one.
                $this->_fire_history = true;
            }

            if ( $this->_fire_history ) {
                $h_props = array();
                foreach ( $this->_history->fields as $prop ) {
                    if ( $prop != 'history_date' ) {
                        $h_props[$prop] = $this->$prop;
                    } else {
                        $h_props[$prop] = date('Y-m-d H:i:s');
                    }
                }
                $this->_history->register($h_props);
                $this->_fire_history = false;
            }

            return true;
        } catch (\Exception $e) {
            Analog::log(
                '[' . get_class($this) . '] An error has occured ' .
                (($new)?'inserting':'updating') . ' car | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * List object's properties
    *
    * @param boolean $restrict true to exclude $this->_internals from returned
    *               result, false otherwise. Default to false
    *
    * @return array
    */
    private function _getAllProperties($restrict = false)
    {
        $result = array();
        foreach ( $this as $key => $value ) {
            if ( !$restrict
                || ($restrict && !in_array(substr($key, 1), $this->_internals))
            ) {
                $result[] = substr($key, 1);
            }
        }
        return $result;
    }

    /**
    * Get object's properties. List only properties that can be modified
    *   externally (ie. not in $this->_internals)
    *
    * @return array
    */
    public function getProperties()
    {
        return $this->_getAllProperties(true);
    }

    /**
    * Does the current car has a picture?
    *
    * @return boolean
    */
    public function hasPicture()
    {
        return $this->_picture->hasPicture();
    }

    /**
    * Set car's owner to current logged user
    *
    * @return void
    */
    public function appropriateCar()
    {
        global $login;
        $this->_owner->load($login->id);
    }

    /**
    * Returns plain text property name, generally used for translations
    *
    * @param string $name property name
    *
    * @return string property
    */
    public function getPropName($name)
    {
        if ( isset($this->_propnames[$name]) ) {
            return $this->_propnames[$name];
        } else {
            throw new UnexpectedValueException('Unknown propname ' . $name);
        }
    }

    /**
    * Global getter method
    *
    * @param string $name name of the property we want to retrive
    *
    * @return false|object the called property
    */
    public function __get($name)
    {
        $forbidden = array();
        if ( !in_array($name, $forbidden) ) {
            switch ( $name ) {
            case self::PK:
                return $this->_id;
                break;
            case Adherent::PK:
                return $this->_owner->id;
                break;
            case Color::PK:
                return $this->_color->id;
                break;
            case State::PK:
                return $this->_state->id;
                break;
            case 'car_registration':
                return $this->_registration;
                break;
            case 'first_registration_date':
            case 'first_circulation_date':
            case 'creation_date':
                $rname = '_' . $name;
                if ( $this->$rname != '' ) {
                    try {
                        $d = new \DateTime($this->$rname);
                        return $d->format(_T("Y-m-d"));
                    } catch (\Exception $e) {
                        //oops, we've got a bad date :/
                        Analog::log(
                            'Bad date (' . $his->$rname . ') | ' .
                            $e->getMessage(),
                            Analog::WARNING
                        );
                        return $this->$rname;
                    }
                }
                break;

                break;
            case Color::PK:
                return $this->_colors->id;
                break;
            case 'picture':
                return $this->_picture;
                break;
            default:
                $rname = '_' . $name;
                if ( isset($this->$rname) ) {
                    return $this->$rname;
                } else {
                    Analog::log(
                        '[' . get_class($this) . '] Property ' . $rname .
                        ' is not set',
                        Analog::WARNING
                    );
                }
                break;
            }
        } else {
            Analog::log(
                '[' . get_class($this) . '] Unable to retrieve `' . $name . '`',
                Analog::INFO
            );
            return false;
        }
    }

    /**
    * Global setter method
    *
    * @param string $name  name of the property we want to assign a value to
    * @param object $value a relevant value for the property
    *
    * @return void
    */
    public function __set($name, $value)
    {
        if ( !in_array($name, $this->_internals) ) {
            switch($name){
            case 'finition':
                $this->_finition->load((int)$value);
                break;
            case 'color':
                $this->_color->load((int)$value);
                break;
            case 'model':
                $this->_model->load((int)$value);
                break;
            case 'transmission':
                $this->_transmission->load((int)$value);
                break;
            case 'body':
                $this->_body->load((int)$value);
                break;
            case 'owner':
                $this->_owner->load((int)$value);
                break;
            case 'state':
                $this->_state->load((int)$value);
                break;
            default:
                $rname = '_' . $name;
                $this->$rname = $value;
                break;
            }
        } else {
            Analog::log(
                '[' . get_class($this) . '] Trying to set an internal property (`' .
                $name . '`)',
                Analog::INFO
            );
            return false;
        }
    }
}