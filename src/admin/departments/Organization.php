<?php
/*
 * This code is part of GOsa (http://www.gosa-project.org)
 * Copyright (C) 2003-2008 GONICUS GmbH
 *
 * ID: $$Id: class_departmentGeneric.inc 11085 2008-05-28 10:54:49Z hickert $$
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace GosaDepartments\admin\departments;

use \plugin as Plugin;
use \msgPool as MsgPool;
use \tests as Tests;

class Organization extends Department
{
    /* attribute list for save action */
    var $attributes     = array(
        "o",
        "ou",
        "description",
        "userPassword",
        "searchGuide",
        "seeAlso",
        "businessCategory",
        "x121Address",
        "registeredAddress",
        "manager",
        "destinationIndicator",
        "preferredDeliveryMethod",
        "telexNumber",
        "telephoneNumber",
        "internationaliSDNNumber",
        "facsimileTelephoneNumber",
        "street",
        "postOfficeBox",
        "postalCode",
        "postalAddress",
        "physicalDeliveryOfficeName",
        "st",
        "l",
        "teletexTerminalIdentifier",
        "gosaUnitTag"
    );

    var $objectclasses  = array("top", "gosaDepartment");
    var $structuralOC   = array("organization");

    /* Attributes
     */
    var $st                       = '';
    var $l                        = '';
    var $description              = '';
    var $userPassword             = '';
    var $searchGuide              = '';
    var $seeAlso                  = '';
    var $businessCategory         = '';
    var $x121Address              = '';
    var $registeredAddress        = '';
    var $destinationIndicator     = '';
    var $preferredDeliveryMethod  = '';
    var $telexNumber              = '';
    var $telephoneNumber          = '';
    var $internationaliSDNNumber  = '';
    var $facsimileTelephoneNumber = '';
    var $street                   = '';
    var $postOfficeBox            = '';
    var $postalCode               = '';
    var $postalAddress            = '';
    var $teletexTerminalIdentifier = '';
    var $physicalDeliveryOfficeName = '';

    /* Naming attributes
     */
    var $o            = '';
    var $type         = "organization";
    var $orig_o       = '';
    var $namingAttr   = "o";

    var $manager_enabled = FALSE;
    var $manager_name = '';
    var $manager = '';

    function __construct(&$config, $dn)
    {
        parent::__construct($config, $dn);
    }

    function check()
    {
        global $config;
        $message = Plugin::check();

        /* Check for presence of this department */
        $ldap = $config->get_ldap_link();
        $ldap->ls("(&(o=" . $this->o . ")(objectClass=organization))", $this->base, array('dn'));
        if ($this->orig_o == 'new' && $ldap->count()) {
            $message[] = MsgPool::duplicated(_('Name'));
        } elseif ($this->orig_dn != $this->dn && $ldap->count()) {
            $message[] = MsgPool::duplicated(_('Name'));
        }

        /* All required fields are set? */
        if ($this->o == '') {
            $message[] = MsgPool::required(_('Name'));
        } elseif (Tests::is_department_name_reserved($this->o, $this->base)) {
            $message[] = MsgPool::reserved(_('Name'));
        } elseif (preg_match('/[#+:=>\\\\\/]/', $this->o)) {
            $message[] = MsgPool::invalid(_('Name'), $this->o, "/[^#+:=>\\\\\/]/");
        }

        /* Check description */
        if ($this->description == '') {
            $message[] = MsgPool::required(_("Description"));
        }

        /* Check if we are allowed to create or move this object
         */
        if ($this->orig_dn == 'new' && !$this->acl_is_createable($this->base)) {
            $message[] = MsgPool::permCreate();
        } elseif ($this->orig_dn != 'new' && $this->base != $this->orig_base && !$this->acl_is_moveable($this->base)) {
            $message[] = MsgPool::permMove();
        }

        return ($message);
    }


    /* Return plugin informations for acl handling */
    static function plInfo()
    {
        return (array(
            "plShortName"   => _("Organization"),
            "plDescription" => _("Organization"),
            "plSelfModify"  => FALSE,
            "plPriority"    => 1,
            "plDepends"     => array(),
            "plSection"     => array("administration"),
            "plCategory"    => array("department"),

            "plProvidedAcls" => array(
                "o"                       => _("Organization name"),
                "description"             => _("Description"),
                "businessCategory"        => _("Category"),
                "base"                    => _("Base"),

                "manager"                 => _("Manager"),

                "st"                      => _("State"),
                "l"                       => _("Location"),
                "postalAddress"           => _("Postal address"),
                "telephoneNumber"         => _("Phone number"),
                "facsimileTelephoneNumber" => _("Fax"),

                "gosaUnitTag"             => _("Administrative settings")
            )
        ));
    }
}
