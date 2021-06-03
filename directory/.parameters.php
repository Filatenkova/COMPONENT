<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arComponentParameters = [
	"GROUPS" => [],
	"PARAMETERS" => [
        "SECTION_URL" => [
            "PARENT" => "ADDITIONAL_SETTINGS",
            "NAME" => Loc::getMessage("PRM_DIR_SECTION_URL_TITLE"),
            "TYPE" => "STRING",
            "DEFAULT" => "/directory/",
        ],
        "DETAIL_URL" => [
            "PARENT" => "ADDITIONAL_SETTINGS",
            "NAME" => Loc::getMessage("PRM_DIR_DETAIL_URL_TITLE"),
            "TYPE" => "STRING",
            "DEFAULT" => "#ID#",
        ],
	],
];