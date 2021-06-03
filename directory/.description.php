<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arComponentDescription =[
	"NAME" => Loc::getMessage("DSCR_DIR_NAME"),
	"DESCRIPTION" => Loc::getMessage("DSCR_DIR_DESCRIPTION"),
    "COMPLEX" => "Y",
	'PATH' => [
        'ID' => 'qsoft',
        'NAME' => 'QSOFT',
	]
];