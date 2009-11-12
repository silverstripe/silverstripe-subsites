<?php

/**
 * Turkish (Turkey) language pack
 * @package subsites
 * @subpackage i18n
 */

i18n::include_locale_file('subsites', 'en_US');

global $lang;

if(array_key_exists('tr_TR', $lang) && is_array($lang['tr_TR'])) {
	$lang['tr_TR'] = array_merge($lang['en_US'], $lang['tr_TR']);
} else {
	$lang['tr_TR'] = $lang['en_US'];
}

$lang['tr_TR']['GroupSubsites']['SECURITYACCESS'] = 'Alt sitelere İYS erişimini kısıtla';
$lang['tr_TR']['GroupSubsites']['SECURITYTABTITLE'] = 'Alt Siteler';
$lang['tr_TR']['Subsite']['PLURALNAME'] = 'Alt Siteler';
$lang['tr_TR']['Subsite']['SINGULARNAME'] = 'Alt Site';
$lang['tr_TR']['SubsiteAdmin']['MENUTITLE'] = 'Alt Siteler';
$lang['tr_TR']['SubsitesVirtualPage']['PLURALNAME'] = 'Alt Site Sanal Sayfalar';
$lang['tr_TR']['SubsitesVirtualPage']['SINGULARNAME'] = 'Alt Site Sanal Sayfa';
$lang['tr_TR']['VirtualPage']['CHOOSE'] = 'İzleyene bağlantı vermek için bir sayfa seçiniz: ';
$lang['tr_TR']['VirtualPage']['EDITCONTENT'] = 'İçeriği düzenlemek için tıklayınız';

?>