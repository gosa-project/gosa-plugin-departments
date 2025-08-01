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

use \management as Management;
use \session as Session;
use \listing as Listing;
use \filter as Filter;
use \LDAP as LDAP;
use \CopyPasteHandler as CopyPasteHandler;
use \SnapshotHandler as SnapshotHandler;

class DepartmentManagement extends Management
{
    var $plHeadline     = "Directory structure";
    var $plDescription  = "Manage organizations, organizational units, localities, countries and more";
    var $plIcon         = "plugins/departments/images/plugin.png";
    var $matIcon        = "source";

    // Tab definition
    protected $tabClass = "GosaDepartments\admin\departments\DepTabs";
    protected $tabType = "DEPTABS";
    protected $aclCategory = "department";
    protected $aclPlugin   = "generic";
    protected $objectName   = "department";

    function __construct($config, $ui)
    {
        $this->config = $config;
        $this->ui = $ui;

        // Build filter
        if (Session::global_is_set(get_class($this) . "_filter")) {
            $filter = Session::global_get(get_class($this) . "_filter");
        } else {
            $filter = new Filter(get_template_path("dep-filter.xml", true));
        }
        $filter->setObjectStorage(array(''));

        // Build headpage
        $headpage = new Listing(get_template_path("dep-list.xml", true));
        $headpage->registerElementFilter("depLabel", "GosaDepartments\admin\departments\DepartmentManagement::filterDepLabel");
        $headpage->setFilter($filter);
        $this->setFilter($filter);

        // Add copy&paste and snapshot handler.
        if ($this->config->boolValueIsTrue("core", "copyPaste")) {
            $this->cpHandler = new CopyPasteHandler($this->config);
        }
        if ($this->config->get_cfg_value("core", "enableSnapshots") == "true") {
            $this->snapHandler = new SnapshotHandler($this->config);
        }

        parent::__construct($config, $ui, "departments", $headpage);

        $this->registerAction("open", "openEntry");
        $this->registerAction("new_domain", "newEntry");
        $this->registerAction("new_country", "newEntry");
        $this->registerAction("new_locality", "newEntry");
        $this->registerAction("new_dcObject", "newEntry");
        $this->registerAction("new_organization", "newEntry");
        $this->registerAction("new_organizationalUnit", "newEntry");
        $this->registerAction("performRecMove", "performRecMove");
        $this->registerAction("tagDepartment", "tagDepartment");
    }

    // Inject additional actions here.
    function detectPostActions()
    {
        $actions = parent::detectPostActions();
        if (isset($_GET['PerformRecMove'])) $actions['action'] = "performRecMove";
        if (isset($_GET['TagDepartment'])) $actions['action'] = "tagDepartment";
        return ($actions);
    }

    // Action handler which allows department tagging - Creates the iframe contents.
    function tagDepartment()
    {
        $plugname = $this->last_tabObject->base_name;
        $this->last_tabObject->by_object[$plugname]->tag_objects();
        exit();
    }

    // Overridden new handler - We've different types of departments to create!
    function newEntry($action = "", $target = array(), $all = array(), $altTabClass = "", $altTabType = "", $altAclCategory = "")
    {
        $types = $this->get_support_departments();
        $type = preg_replace("/^new_/", "", $action);
        return (parent::newEntry($action, $target, $all, $this->tabClass, $types[$type]['TAB'], $this->aclCategory));
    }

    // Overridden edit handler - We've different types of departments to edit!
    function editEntry($action = "", $target = array(), $all = array(), $altTabClass = "", $altTabType = "", $altAclCategory = "")
    {
        $types = $this->get_support_departments();
        $headpage = $this->getHeadpage();
        $type = $headpage->getType($target[0]);
        return (parent::editEntry($action, $target, $all, $this->tabClass, $types[$type]['TAB'], $this->aclCategory));
    }


    // Overriden save handler - We've to take care about the department tagging here.
    protected function saveChanges()
    {
        $str = parent::saveChanges();
        if (!empty($str)) return ($str);

        $plugname = (isset($this->last_tabObject->base_name)) ? $this->last_tabObject->base_name : '';

        $this->refreshDeps();
        if (
            isset($this->last_tabObject->by_object[$plugname]) &&
            is_object($this->last_tabObject->by_object[$plugname])      &&
            $this->last_tabObject->by_object[$plugname]->must_be_tagged()
        ) {
            $smarty = get_smarty();
            $smarty->assign("src", "?plug=" . $_GET['plug'] . "&TagDepartment&no_output_compression");
            $smarty->assign("message", _("As soon as the tag operation has finished, you can scroll down to end of the page and    press the 'Continue' button to continue with the department management dialog."));
            return ($smarty->fetch(get_template_path("dep_iframe.tpl", TRUE)));
        }
    }

    function refreshDeps()
    {
        global $config;
        $config->get_departments();
        $config->make_idepartments();
        $this->config = $config;
        $headpage = $this->getHeadpage();
        $headpage->refreshBasesList();
    }

    // An action handler which enables to switch into deparmtment by clicking the names.
    function openEntry($action, $entry)
    {
        $headpage = $this->getHeadpage();
        $headpage->setBase(array_pop($entry));
    }


    // Overridden remove request method - Avoid removal of the ldap base.
    protected function removeEntryRequested($action = "", $target = array(), $all = array())
    {
        global $config;
        $target = array_remove_entries(array($config->current['BASE']), $target);
        return (parent::removeEntryRequested($action, $target, $all));
    }


    // A filter which allows to open a department by clicking on the departments name.
    static function filterDepLabel($row, $dn, $pid, $ou, $base)
    {
        $ou = $ou[0];
        if (LDAP::convert($dn) == $base) {
            $ou = ".";
        }
        $dn = LDAP::fix(func_get_arg(1));
        return ("<a href='?plug=" . $_GET['plug'] . "&amp;PID=$pid&amp;act=listing_open_$row' title='$dn'>$ou</a>");
    }


    // Finally remove departments and update departmnet browsers
    function removeEntryConfirmed(
        $action = "",
        $target = array(),
        $all = array(),
        $altTabClass = "",
        $altTabType = "",
        $altAclCategory = "",
        $aclPlugin = ""
    ) {
        parent::removeEntryConfirmed($action, $target, $all, $altTabClass, $altTabType, $altAclCategory);
        $this->refreshDeps();
    }

    /*! \brief  Returns information about all container types that GOsa con handle.
    @return Array   Informations about departments supported by GOsa.
   */
    public static function get_support_departments()
    {
        // Domain
        $types = array();
        $types['domain']['ACL']     = "domain";
        $types['domain']['CLASS']   = "domain";
        $types['domain']['ATTR']    = "dc";
        $types['domain']['TAB']     = "DOMAIN_TABS";
        $types['domain']['OC']      = "domain";
        $types['domain']['IMG']     = tempSwitch(array("domain", "plugins/departments/images/domain.png"));
        $types['domain']['IMG_FULL'] = tempSwitch(array("domain", "plugins/departments/images/domain.png"));
        $types['domain']['TITLE']   = _("Domain");
        $types['domain']['TPL']     = tempSwitch(array("dep_oUnit.tpl", "domain.tpl"));
        $types['domain']['TR']      = ["domain", "Domain"];

        // Domain component
        $types['dcObject']['ACL']     = "dcObject";
        $types['dcObject']['CLASS']   = "dcObject";
        $types['dcObject']['ATTR']    = "dc";
        $types['dcObject']['TAB']     = "DCOBJECT_TABS";
        $types['dcObject']['OC']      = "dcObject";
        $types['dcObject']['IMG']     = tempSwitch(array("router", "plugins/departments/images/dc.png"));
        $types['dcObject']['IMG_FULL'] = tempSwitch(array("router", "plugins/departments/images/dc.png"));
        $types['dcObject']['TITLE']   = _("Domain Component");
        $types['dcObject']['TPL']     = tempSwitch(array("dep_oUnit.tpl", "dcObject.tpl"));
        $types['dcObject']['TR']      = ["locality", "Locality"];

        // Country object
        $types['country']['ACL']     = "country";
        $types['country']['CLASS']   = "country";
        $types['country']['TAB']     = "COUNTRY_TABS";
        $types['country']['ATTR']    = "c";
        $types['country']['OC']      = "country";
        $types['country']['IMG']     = tempSwitch(array("outlined_flag", "plugins/departments/images/country.png"));
        $types['country']['IMG_FULL'] = tempSwitch(array("outlined_flag", "plugins/departments/images/country.png"));
        $types['country']['TITLE']   = _("Country");
        $types['country']['TPL']     = tempSwitch(array("dep_oUnit.tpl", "country.tpl"));
        $types['country']['TR']      = ["country", "Country"];

        // Locality object
        $types['locality']['ACL']     = "locality";
        $types['locality']['CLASS']   = "locality";
        $types['locality']['TAB']     = "LOCALITY_TABS";
        $types['locality']['ATTR']    = "l";
        $types['locality']['OC']      = "locality";
        $types['locality']['IMG']     = tempSwitch(array("screen_search_desktop", "plugins/departments/images/locality.png"));
        $types['locality']['IMG_FULL'] = tempSwitch(array("screen_search_desktop", "plugins/departments/images/locality.png"));
        $types['locality']['TITLE']   = _("Locality");
        $types['locality']['TPL']     = tempSwitch(array("dep_oUnit.tpl", "locality.tpl"));
        $types['locality']['TR']      = ["locality", "Locality"];

        // Organization
        $types['organization']['ACL']     = "organization";
        $types['organization']['CLASS']   = "organization";
        $types['organization']['TAB']     = "ORGANIZATION_TABS";
        $types['organization']['ATTR']    = "o";
        $types['organization']['OC']      = "organization";
        $types['organization']['IMG']     = tempSwitch(array("corporate_fare", "plugins/departments/images/organization.png"));
        $types['organization']['IMG_FULL'] = tempSwitch(array("corporate_fare", "plugins/departments/images/organization.png"));
        $types['organization']['TITLE']   = _("Organization");
        $types['organization']['TPL']     = tempSwitch(array("dep_oExtendedUnit.tpl", "organization.tpl"));
        $types['organization']['TR']      = ["organization", "Organization"];


        // Department
        $types['organizationalUnit']['ACL']     = "department";
        $types['organizationalUnit']['CLASS']   = "department";
        $types['organizationalUnit']['TAB']     = "DEPTABS";
        $types['organizationalUnit']['ATTR']    = "ou";
        $types['organizationalUnit']['OC']      = "organizationalUnit";
        $types['organizationalUnit']['IMG']     = tempSwitch(array("folder", "images/lists/folder.png")); //plugins/departments/images/department.png";
        $types['organizationalUnit']['IMG_FULL'] = tempSwitch(array("folder", "images/lists/folder.png")); //plugins/departments/images/department.png";
        $types['organizationalUnit']['TITLE']   = _("Department");
        $types['organizationalUnit']['TPL']     = tempSwitch(array("dep_oExtendedUnit.tpl", "generic.tpl"));
        $types['organizationalUnit']['TR']      = ["department", "Department"];

        return ($types);
    }
}
