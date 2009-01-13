<?php

/**
 * Norwegian Bokmal (Norway) language pack
 * @package modules: subsites
 * @subpackage i18n
 */

i18n::include_locale_file('modules: subsites', 'en_US');

global $lang;

if(array_key_exists('nb_NO', $lang) && is_array($lang['nb_NO'])) {
	$lang['nb_NO'] = array_merge($lang['en_US'], $lang['nb_NO']);
} else {
	$lang['nb_NO'] = $lang['en_US'];
}

$lang['nb_NO']['GroupSubsites']['SECURITYACCESS'] = 'Begrens CMS tilgang til Subdomener';
$lang['nb_NO']['GroupSubsites']['SECURITYTABTITLE'] = 'subdomener';
$lang['nb_NO']['Subsite']['PLURALNAME'] = 'Subdomener';
$lang['nb_NO']['Subsite']['SINGULARNAME'] = 'Subdomene';
$lang['nb_NO']['SubsiteAdmin']['MENUTITLE'] = 'Underdomener';
$lang['nb_NO']['SubsitesVirtualPage']['PLURALNAME'] = 'Subdomeners Virtuelle Sider';
$lang['nb_NO']['SubsitesVirtualPage']['SINGULARNAME'] = 'Subdomeners Virtuelle Side';
$lang['nb_NO']['VirtualPage']['CHOOSE'] = 'Velg en side å lenke til';
$lang['nb_NO']['VirtualPage']['EDITCONTENT'] = 'klikk her for å endre dette innholdet';

?>