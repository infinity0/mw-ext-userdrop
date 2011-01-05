<?php
/** \file
* \brief Contains setup code for the User Drop Control Extension.
*/

# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
        echo "User Drop Control extension";
        exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
	'path'           => __FILE__,
	'name'           => 'User Drop Control',
	'url'            => 'http://github.com/infinity0/mw-ext-userdrop',
	'author'         => 'Ximin Luo',
	'descriptionmsg' => 'userdrop-desc',
	'version'        => '0.0.1'
);

$wgAvailableRights[] = 'userdrop';
# $wgGroupPermissions['bureaucrat']['userdrop'] = true;

$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['UserDrop'] = $dir . 'UserDrop_body.php';

$wgExtensionMessagesFiles['UserDrop'] = $dir . 'UserDrop.i18n.php';
$wgExtensionAliasesFiles['UserDrop'] = $dir . 'UserDrop.alias.php';
$wgSpecialPages['UserDrop'] = 'UserDrop';
$wgSpecialPageGroups['UserDrop'] = 'users';

