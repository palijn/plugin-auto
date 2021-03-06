<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Add/Edit vehicles
 *
 * This page should be loaded directly, or via ajax.
 * Via ajax, we do not have a full html page, but only
 * that will be displayed using javascript on another page
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
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
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id: vehicles.php 556 2009-05-12 07:30:49Z trashy $
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-09-26
 */

use Analog\Analog as Analog;
use GaletteAuto\Color;
use GaletteAuto\State;
use GaletteAuto\Finition;
use GaletteAuto\Body;
use GaletteAuto\Transmission;
use GaletteAuto\Auto;
use GaletteAuto\Model;
use GaletteAuto\Picture;

define('GALETTE_BASE_PATH', '../../');
if ( !isset($mine) ) {
    $mine = false;
}
require_once GALETTE_BASE_PATH . 'includes/galette.inc.php';
if ( !$login->isLogged()
    || (!$mine && !$login->isAdmin()
    && !$login->isStaff())
) {
    header('location: ' . GALETTE_BASE_PATH . 'index.php');
    die();
}

//Constants from plugin
require_once '_config.inc.php';

$is_new = ( isset($_GET[Auto::PK]) && is_int((int)$_GET[Auto::PK]) ) ? false : true;
$set = get_form_value('set', null);

$auto = new Auto();
if ( !$is_new ) {
    $auto->load((int)$_GET[Auto::PK]);
} else {
    if ( $mine ) {
        $auto->appropriateCar($login);
    } else {
        if ( isset($_GET['id_adh']) && ($login->isAdmin() || $login->isStaff()) ) {
            $auto->owner = $_GET['id_adh'];
        }
    }
}

$title = ( $is_new )
    ? _T("New vehicle", "auto")
    : str_replace('%s', $auto->name, _T("Change vehicle '%s'", "auto"));

//We have a new or a modified object
if ( get_numeric_form_value('modif', 0) == 1
    || get_numeric_form_value('new', 0) == 1
    && !isset($_POST['cancel'])
) {
    // initialize warnings
    $error_detected = array();
    $warning_detected = array();
    $confirm_detected = array();

    if ( !$is_new && get_numeric_form_value(Auto::PK, null) != null ) {
        $auto->load(get_numeric_form_value(Auto::PK, null));
    } else if ( !$is_new ) {
        $error_detected[]
            = _T("- No id provided for modifying this record! (internal)", "auto");
    }

    /** TODO: make required fields dynamic, as in main Galette */
    $required = array(
        'name'                      => 1,
        'model'                     => 1,
        'first_registration_date'   => 1,
        'first_circulation_date'    => 1,
        'color'                     => 1,
        'state'                     => 1,
        'registration'              => 1,
        'body'                      => 1,
        'transmission'              => 1,
        'finition'                  => 1,
        'fuel'                      => 1
    );

    //check for required fields, and correct values
    foreach ( $auto->getProperties() as $prop ) {
        $value = get_form_value($prop, null);
        switch ( $prop ) {
        //string values, no check
        case 'name':
        case 'comment':
            $value = get_form_value($prop, null);
            if ( $value == '' && in_array($prop, array_keys($required)) ) {
                $error_detected[] = str_replace(
                    '%s',
                    '<a href="#' . $prop . '">' .$auto->getPropName($prop) . '</a>',
                    _T("- Mandatory field %s empty.", "auto")
                );
            } else {
                $auto->$prop = $value;
            }
            break;
        //string values with special check
        case 'chassis_number':
        case 'registration':
            /** TODO: how are built chassis number and registration? */
            if ( $value == '' && in_array($prop, array_keys($required)) ) {
                $error_detected[] = str_replace(
                    '%s',
                    '<a href="#' . $prop . '">' .$auto->getPropName($prop) . '</a>',
                    _T("- Mandatory field %s empty.", "auto")
                );
            } else {
                $auto->$prop = $value;
            }
            break;
        //dates
        case 'first_registration_date':
        case 'first_circulation_date':
            if ( $value == '' && in_array($prop, array_keys($required)) ) {
                $error_detected[] = str_replace(
                    '%s',
                    '<a href="#' . $prop . '">' .$auto->getPropName($prop) . '</a>',
                    _T("- Mandatory field %s empty.", "auto")
                );
            } elseif ( preg_match("@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@", $value, $array_jours) ) {
                if ( checkdate($array_jours[2], $array_jours[1], $array_jours[3]) ) {
                    $value = $array_jours[3].'-'.$array_jours[2].'-'.$array_jours[1];
                    $auto->$prop = $value;
                } else {
                    $error_detected[] = str_replace(
                        '%s',
                        $auto->getPropName($prop),
                        _T("- Non valid date for %s!", "auto")
                    );
                }
            } else {
                $error_detected[] = str_replace(
                    '%s',
                    $auto->getPropName($prop),
                    _T("- Wrong date format for %s (dd/mm/yyyy)!", "auto")
                );
            }
            break;
        //numeric values
        case 'mileage':
        case 'seats':
        case 'horsepower':
        case 'engine_size':
            if ( $value == '' && in_array($prop, array_keys($required)) ) {
                $error_detected[] = str_replace(
                    '%s',
                    '<a href="#' . $prop . '">' .$auto->getPropName($prop) . '</a>',
                    _T("- Mandatory field %s empty.", "auto")
                );
            } else {
                if ( is_int((int)$value) ) {
                    $auto->$prop = $value;
                } else if ( $value != '' ) {
                    $error_detected[] = str_replace(
                        '%s',
                        '<a href="#' . $prop . '">' .$auto->getPropName($prop) . '</a>',
                        _T("- You must enter a positive integer for %s", "auto")
                    );
                }
            }
            break;
        //constants
        case 'fuel':
            if ( in_array($value, array_keys($auto->listFuels())) ) {
                $auto->fuel = $value;
            } else {
                $error_detected[] = _T("- You must choose a fuel in the list", "auto");
            }
            break;
        //external objects
        case 'finition':
        case 'color':
        case 'model':
        case 'transmission':
        case 'body':
        case 'state':
        case 'model':
            if ( $value > 0 ) {
                $auto->$prop = $value;
            } else {
                $name = '';
                switch ( $prop ) {
                case 'finition':
                    $name = Finition::FIELD;
                    break;
                case 'color':
                    $name = Color::FIELD;
                    break;
                case 'model':
                    $name = Model::FIELD;
                    break;
                case 'transmission':
                    $name = Transmission::FIELD;
                    break;
                case 'body':
                    $name = Body::FIELD;
                    break;
                case 'state':
                    $name = State::FIELD;
                    break;
                default:
                    Analog::log(
                        'Unable to retrieve the textual value for prop `' .
                        $prop . '`',
                        Analog::INFO
                    );
                    $name = '(unknow)';
                }
                $error_detected[] = str_replace(
                    '%s',
                    '<a href="#' . $prop . '">' . $auto->getPropName($name) . '</a>',
                    _T("- You must choose a %s in the list", "auto")
                );
            }
            break;
        case 'owner':
            $value = get_numeric_form_value($prop, 0);
            if ( $value > 0 ) {
                $auto->$prop = $value;
            } else {
                $error_detected[] = _T("- you must attach an owner to this car", "auto");
            }
            break;
        default:
            /** TODO: what's the default? */
            Analog::log(
                'Trying to edit an Auto property that is not catched in the source code! (prop is: ' . $prop . ')',
                Analog::ERROR
            );
            break;
        }//switch
    }//foreach

    // picture upload
    if ( isset($_FILES['photo']) ) {
        if ( $_FILES['photo']['tmp_name'] !='' ) {
            if ( is_uploaded_file($_FILES['photo']['tmp_name']) ) {
                $res = $auto->picture->store($_FILES['photo']);
                if ( $res < 0) {
                    switch ( $res ) {
                    case Picture::INVALID_FILE:
                        $patterns = array('|%s|', '|%t|');
                        $replacements = array(
                            $auto->picture->getAllowedExts(),
                            htmlentities($auto->picture->getBadChars())
                        );
                        $error_detected[] = preg_replace(
                            $patterns,
                            $replacements,
                            _T("- Filename or extension is incorrect. Only %s files are allowed. File name should not contains any of: %t")
                        );
                        break;
                    case Picture::FILE_TOO_BIG:
                        $error_detected[] = preg_replace(
                            '|%d|',
                            Picture::MAX_FILE_SIZE,
                            _T("File is too big. Maximum allowed size is %d")
                        );
                        break;
                    case Picture::MIME_NOT_ALLOWED:
                        /** FIXME: should be more descriptive */
                        $error_detected[] = _T("Mime-Type not allowed");
                        break;
                    case Picture::SQL_ERROR:
                    case Picture::SQL_BLOB_ERROR:
                        $error_detected[] = _T("An SQL error has occured.");
                        break;
                    }
                }
            }
        }
    }

    //delete photo
    if ( isset($_POST['del_photo']) ) {
        if ( !$auto->picture->delete() ) {
            $error_detected[]
                = _T("An error occured while trying to delete car's photo", "auto");
        }
    }

    //if no errors were thrown, we can store the car
    if ( count($error_detected) == 0 ) {
        if ( !$auto->store($is_new) ) {
            $error_detected[]
                = _T("- An error has occured while saving car in the database.", "auto");
        } else {
            if ( $mine ) {
                header('location: my_vehicles.php');
            } else {
                header('location: vehicles_list.php');
            }
        }
    }
} else if ( isset($_POST['cancel']) ) {
    unset($_POST['new']);
    $is_new = false;
    unset($_POST['modif']);
}

if ( isset($error_detected) ) {
    $tpl->assign('error_detected', $error_detected);
}

$tpl->assign('page_title', $title);

//Set the path to the current plugin's templates,
//but backup main Galette's template path before
$orig_template_path = $tpl->template_dir;
$tpl->template_dir = 'templates/' . $preferences->pref_theme;
$tpl->assign('mode', (($is_new) ? 'new' : 'modif'));

if ( !$is_new ) {
    $auto->load(get_numeric_form_value(Auto::PK, null));
}

$tpl->compile_id = AUTO_SMARTY_PREFIX;
$tpl->assign('require_calendar', true);
$tpl->assign('require_dialog', true);
$tpl->assign('show_mine', $mine);
$tpl->assign('car', $auto);
$tpl->assign('models', $auto->model->getList((int)$auto->model->brand));
$tpl->assign('js_init_models', (($auto->model->brand != '') ? false : true));
$tpl->assign('brands', $auto->model->obrand->getList());
$tpl->assign('colors', $auto->color->getList());
$tpl->assign('bodies', $auto->body->getList());
$tpl->assign('transmissions', $auto->transmission->getList());
$tpl->assign('finitions', $auto->finition->getList());
$tpl->assign('states', $auto->state->getList());
$tpl->assign('fuels', $auto->listFuels());
$tpl->assign('time', time());
$content = $tpl->fetch('vehicles.tpl', AUTO_SMARTY_PREFIX);

$tpl->assign('content', $content);
//Set path to main Galette's template
$tpl->template_dir = $orig_template_path;
$tpl->display('page.tpl', AUTO_SMARTY_PREFIX);
