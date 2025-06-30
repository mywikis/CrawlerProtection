<?php

// use phan config shipped with mediawiki core
$cfg = require __DIR__ . '/../../../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// we suppress things for backwards compat reasons, so suppressions may not apply to all phan test runs
// as part of dropping support for old versions, old suppressions will be removed
$cfg['suppress_issue_types'][] = 'UnusedSuppression';
$cfg['suppress_issue_types'][] = 'UnusedPluginSuppression';

// we make use of class aliases for backwards compat, but phan doesn't honor version checks surrounding them
$cfg['suppress_issue_types'][] = 'PhanUndeclaredClassAliasOriginal';
$cfg['suppress_issue_types'][] = 'PhanRedefineClassAlias';

return $cfg;
