<?php
/*
 * This code is part of GOsa (http://www.gosa-project.org)
 * Copyright (C) 2003-2008 GONICUS GmbH
 *
 * ID: $$Id$$
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

use \tabs as Tabs;
use \LDAP as LDAP;

class DepTabs extends Tabs
{
    var $base = "";
    var $moved = FALSE;
    var $base_name = "department";

    function __construct($config, $data, $dn, $category, $hide_refs = FALSE, $hide_acls = FALSE)
    {
        Tabs::__construct($config, $data, $dn, $category, $hide_refs, $hide_acls);

        // Detect the base class  (The classs which extends from department)
        foreach ($this->by_object as $name => $object) {
            if ($object instanceof Department) {
                $this->base_name = get_class($object);
                break;
            }
        }


        /* Add references/acls/snapshots */
        $this->addSpecialTabs();
        if (isset($this->by_object['acl'])) {
            $this->by_object['acl']->skipTagging = TRUE;
        }
    }

    function check($ignore_account = FALSE)
    {
        return (Tabs::check(TRUE));
    }

    function save($ignore_account = FALSE)
    {
        $baseobject = &$this->by_object[$this->base_name];
        $namingAttr       = $baseobject->namingAttr;
        $nAV      = preg_replace('/,/', '\,', $baseobject->$namingAttr);
        $nAV      = preg_replace('/"/', '\"', $nAV);
        $new_dn   = @LDAP::convert($namingAttr . '=' . $nAV . ',' . $baseobject->base);

        // Do not try to move the root DSE
        if ($baseobject->is_root_dse) {
            $new_dn = $baseobject->orig_dn;
        }

        // Move group?
        if ($this->dn != $new_dn && $this->dn != 'new') {
            $baseobject->move($this->dn, $new_dn);
        }

        // Update department cache.
        if ($this->dn != $new_dn) {
            global $config;
            $config->get_departments();
        }

        $this->dn = $new_dn;
        $baseobject->dn = $this->dn;
        if (!$ignore_account) {
            Tabs::save();
        }
    }
}
