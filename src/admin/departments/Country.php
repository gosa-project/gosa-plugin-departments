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

class Country extends Department
{
    /* attribute list for save action */
    var $attributes     = array("c", "ou", "description", "gosaUnitTag", "manager");
    var $objectclasses  = array("top", "gosaDepartment");
    var $structuralOC   = array("country");
    var $type   = "country";
    var $c      = '';
    var $orig_c = '';

    var $namingAttr = "c";
    var $manager_enabled = FALSE;
    var $manager_name = '';
    var $manager = '';


    function check()
    {
        global $config;
        $message = Plugin::check();

        /* Check for presence of this department */
        $ldap = $config->get_ldap_link();
        $ldap->ls("(&(c=" . $this->c . ")(objectClass=country))", $this->base, array('dn'));
        if ($this->orig_c == 'new' && $ldap->count()) {
            $message[] = MsgPool::duplicated(_('Name'));
        } elseif ($this->orig_dn != $this->dn && $ldap->count()) {
            $message[] = MsgPool::duplicated(_('Name'));
        }

        /* All required fields are set? */
        if ($this->c == '') {
            $message[] = MsgPool::required(_('Name'));
        } elseif (Tests::is_department_name_reserved($this->c, $this->base)) {
            $message[] = MsgPool::reserved(_('Name'));
        } elseif (preg_match('/[#+:=>\\\\\/]/', $this->c)) {
            $message[] = MsgPool::invalid(_('Name'), $this->c, "/[^#+:=>\\\\\/]/");
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
            "plShortName"   => _("Country"),
            "plDescription" => _("Country"),
            "plSelfModify"  => FALSE,
            "plPriority"    => 2,
            "plDepends"     => array(),
            "plSection"     => array("administration"),
            "plCategory"    => array("department"),

            "plProvidedAcls" => array(
                "c"                 => _("Country name"),
                "description"       => _("Description"),
                "manager"                 => _("Manager"),
                "base"              => _("Base"),
                "gosaUnitTag"       => _("Administrative settings")
            )
        ));
    }
}
