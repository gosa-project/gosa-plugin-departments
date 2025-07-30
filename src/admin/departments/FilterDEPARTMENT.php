<?php

namespace GosaDepartments\admin\departments;

use \session as Session;
use \filterLDAP as FilterLDAP;

class FilterDEPARTMENT
{

  static function query($base, $scope, $filter, $attributes, $category, $objectStorage = array(""))
  {
    $config = Session::global_get('config');
    $ldap = $config->get_ldap_link(TRUE);

    $result = array();
    $flag = ($scope == "sub") ? GL_SUBSEARCH : 0;
    if (!($flag & GL_SUBSEARCH)) {
      $ldap->cat($base);
      $result[] = $ldap->fetch();
    }

    $result = array_merge($result, FilterLDAP::get_list($base, $filter, $attributes, $category, $objectStorage, $flag | GL_SIZELIMIT));
    return $result;
  }
}
