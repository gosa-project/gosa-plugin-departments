<?php

namespace GosaDepartments\admin\departments;

$success = bindtextdomain('GosaDepartments', dirname(dirname(__FILE__)) . '/locale/compiled');

function __(string $GETTEXT): string
{
    return dgettext('GosaDepartments', $GETTEXT);
}
