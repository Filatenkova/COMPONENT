<?php

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\UserTable;
use Bitrix\Main\Entity;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\FileTable;
use QSOFT\ORM\PositionsTable;
use QSOFT\ORM\CompanyTable;
use QSOFT\ORM\HrTable;
use QSOFT\Services\Structure\CacheManager;

/**
 * Класс - компонент для работы со структурой подчиненности должностей в компании
 */

class DirectoryComponent extends CBitrixComponent implements Controllerable
{
    const CMP_DIR_DEPARTMENT_TRUNCATE_TEXT = 50;
    const CMP_DIR_DEFAULT_PERSONAL_PHOTO = '/local/templates/.default/img/no-phote2.svg';
    const CMP_DIR_CACHE_TAG = 'user_picture_component';
    const CMP_DIR_WORK_MAIN = '1';

    private $errorCollection = null;
    private $headDepartment = '';
    private $counterNum = 0;
    private $autoCardId = '';
    protected $companyName = '';

    public function configureActions(): array
    {
        return [
            'getAllSubordinates' => [],
        ];
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->errorCollection = new ErrorCollection();

        try {
            if (!Loader::includeModule("iblock")) {
                $this->errorCollection->setError(new Error(Loc::getMessage("CMP_DIR_IBLOCK_MODULE_NOT_INSTALLED")));
            }

            if (!Loader::includeModule("highloadblock")) {
                $this->errorCollection->setError(new Error(Loc::getMessage("CMP_DIR_HIGHLOADBLOCK_MODULE_NOT_INSTALLED")));
            }

        } catch (Throwable $e) {
            $this->errorCollection->setError(new Error(Loc::getMessage("CMP_DIR_PARAMETERS_ERROR")));
        }

        return $arParams;
    }

    public function executeComponent()
    {
        $paramsId = Context::getCurrent()->getRequest()->get('ID');

        if ($this->StartResultCache(false)) {
            global $CACHE_MANAGER;
            $CACHE_MANAGER->RegisterTag(CacheManager::STRUCTURE_USERS_TAG);
            $CACHE_MANAGER->RegisterTag(self::CMP_DIR_CACHE_TAG);

            (($headId = $paramsId)) ?: $headId = Option::get("qsoft.boss", "general_hr");

            if (empty($this->errorCollection->toArray())) {
                $this->arResult["HEAD"] = $this->getHead($headId);
                $autoCardId = $this->arResult["HEAD"][$headId]["AUTO_CARD"] ?? $this->autoCardId;
                $this->arResult["BRANCH_USERS"] = $this->getSubordinates($headId, $autoCardId, $this->headDepartment, $this->counterNum);
            } else {
                $this->arResult["ERRORS"] = $this->errorCollection->toArray();
            }

            $this->IncludeComponentTemplate();

        }
    }

    /**
     *  Отбор подчиненных по ID должности вышестоящего сотрудника
     *
     * @param string $headHrId - ID должности сотрудника
     * @param string $autoCardId - Auto_Card сотрудника
     * @param string $headDepartment - название вышестоящего департамента
     * @param int $counterNum - уровень подчиненности для добавления класса в верстке
     *
     * @return AjaxJson
     */
    public function getAllSubordinatesAction(string $headHrId, string $autoCardId, string $headDepartment, int $counterNum): AjaxJson
    {
        $result = $this->getSubordinates($headHrId, $autoCardId, $headDepartment, $counterNum);

        if (!empty($this->errorCollection->toArray())) {
            return AjaxJson::createError($this->errorCollection);
        }

        return AjaxJson::createSuccess($result);
    }

    /**
     * Метод получает данные о Руководителе
     *
     * @param string $headId - id_st Руководителя
     *
     * @return array массив с данными о Руководителе
     */
    private function getHead(string $headId): array
    {
        $arFilter = [
            "!ACTIVE" => false,
            ">UF_DATE_OUT" => date('d.m.Y'),
            "UF_BOSS_ID_ST" => $headId
        ];

        $arUser = $this->getUserData($arFilter, $this->headDepartment, $this->counterNum) ?? [];

        if (empty($arUser)) {

            $hrFilter = [
                "UF_POS_EMPLOYEE_ID" => $headId,
                '<=UF_DATE_FROM' => date('d.m.Y'),
                '>UF_DATE_TO' => date('d.m.Y'),
            ];

            $arUser = $this->getHrData($hrFilter, $this->headDepartment, $this->counterNum);
        }

        return $arUser ?? [];
    }

    /**
     * Метод получает доп. данные о сотрудниках и вакантных должностях из штаного расписания по id должности руководителя из штатного расписания
     *
     * @param string $headHrId - ID должности руководителя из штатного расписания
     * @param string $autoCardId - Auto_Card руководителя из штатного расписания
     * @param string $headDepartment - Название вышестоящего департамента
     * @param int $counterNum - уровень подчиненности для добавления класса в верстке
     *
     * @return array массив с данными о сотрудниках и должностях
     */
    private function getSubordinates(string $headHrId, string $autoCardId, string $headDepartment, int $counterNum): array
    {
        $arSubordinates = [];

        if ($headHrId) {
            $autoCardId = $autoCardId ?? $this->autoCardId;
            $headDepartment = $headDepartment ?? $this->headDepartment;
            $counterNum = $counterNum ?? $this->counterNum;
            $allPositions = $this->getAllPositions($headHrId, $autoCardId);
            $arSubordinatesId = $this->getSubordinatesHr($allPositions);
            $arUsers = [];
            $arHr = [];
            if (!empty($arSubordinatesId["USER_FILTER"])) {

                $userFilter = [
                    "!ACTIVE" => false,
                    ">UF_DATE_OUT" => date('d.m.Y'),
                    "UF_BOSS_ID_ST" => $arSubordinatesId["USER_FILTER"]
                ];

                $cacheId = md5(serialize($userFilter));
                $cacheDir = "/directory";
                $obCache = new CPHPCache;

                if ($obCache->InitCache(86400, $cacheId, $cacheDir)) {

                    $arUsers = $obCache->GetVars();

                } elseif ($obCache->StartDataCache()) {

                    global $CACHE_MANAGER;
                    $CACHE_MANAGER->StartTagCache($cacheDir);

                    $arUsers = $this->getUserData($userFilter, $headDepartment, $counterNum);

                    $CACHE_MANAGER->RegisterTag(CacheManager::STRUCTURE_USERS_TAG);
                    $CACHE_MANAGER->EndTagCache();
                    $obCache->EndDataCache($arUsers);

                }
            }

            if (!empty($arSubordinatesId["HR_FILTER"])) {

                $hrFilter = [
                    "UF_POS_EMPLOYEE_ID" => $arSubordinatesId["HR_FILTER"]
                ];

                $arHr = $this->getHrData($hrFilter, $headDepartment, $counterNum);
            }

            $arSubordinates = array_replace($arUsers, $arHr);
        }

        return $arSubordinates ?? [];
    }

    /**
     * Метод получает данные о сотрудниках
     *
     * @param array $userFilter - фильтр сортировки для UserTable
     * @param string $headDepartment - Название вышестоящего департамента
     * @param int $counterNum - уровень подчиненности для добавления класса в верстке
     *
     * @return array $arUsers массив с данными о сотрудниках
     */
    private function getUserData(array $userFilter, string $headDepartment, int $counterNum): array
    {
        try {

            $arUsers = [];

            if (!empty($userFilter)) {

                $headDepartment = $headDepartment ?? $this->headDepartment;
                $newCounter = ++$counterNum;

                $result = UserTable::getList([
                    "select" => [
                        "ID",
                        "NAME",
                        "LAST_NAME",
                        "WORK_POSITION",
                        "COMPANY_NAME" => "COMPANY.UF_NAME",
                        "DEPARTMENT_NAME" => "DEPARTMENT.NAME",
                        "PHOTO_FILENAME" => "PHOTO.FILE_NAME",
                        "PHOTO_SUBDIR" => "PHOTO.SUBDIR",
                        "HEAD_WORK_MAIN" => "BYWORKER.UF_POS_MAIN_POSITION",
                        "HR_MANAGER_ID_ST" => "BYWORKER.UF_POS_MANAGER_ID",
                        "PERSONAL_PHOTO",
                        "UF_BOSS_ID_ST",
                        "UF_BOSS_AUTO_CARD"
                    ],
                    "runtime" => [
                        new  Entity\ReferenceField(
                            "DEPARTMENT",
                            SectionTable::class,
                            ["=this.UF_DEPARTMENT" => "ref.ID"]
                        ),
                        new  Entity\ReferenceField(
                            "PHOTO",
                            FileTable::class,
                            ["=this.PERSONAL_PHOTO" => "ref.ID"]
                        ),
                        new  Entity\ReferenceField(
                            "COMPANY",
                            CompanyTable::class,
                            ["=this.UF_COMPANY" => "ref.UF_GUID"]
                        ),
                        new  Entity\ReferenceField(
                            "BYWORKER",
                            HrTable::class,
                            ["=this.UF_BOSS_ID_ST" => "ref.UF_POS_EMPLOYEE_ID"],
                            ['join_type' => 'LEFT']
                        )
                    ],
                    "filter" => $userFilter,
                ]);

                while ($arUser = $result->fetch()) {
                    if (!empty($arUser["PHOTO_SUBDIR"]) && !empty($arUser["PHOTO_FILENAME"])) {
                        $srcFile = self::getFileSrc($arUser["PHOTO_SUBDIR"], $arUser["PHOTO_FILENAME"]);
                    } else {
                        $srcFile = self::CMP_DIR_DEFAULT_PERSONAL_PHOTO;
                    }

                    $companyName = $this->changeCompanyName($arUser["COMPANY_NAME"]);

                    $userId = $arUser["ID"];
                    $allPositions = $this->getAllPositions($arUser["UF_BOSS_ID_ST"], $arUser["UF_BOSS_AUTO_CARD"]);
                    $isManager = (($arUser["HEAD_WORK_MAIN"] == self::CMP_DIR_WORK_MAIN) && !empty($this->hasEmployee($allPositions))) ? true : false;
                    $headDepartmentName = $this->getHeadDepartment((string)$arUser["HR_MANAGER_ID_ST"]);

                    $arUsers[$userId]["ID"] = $userId;
                    $arUsers[$userId]["ID_ST"] = $arUser["UF_BOSS_ID_ST"];
                    $arUsers[$userId]["AUTO_CARD"] = $arUser["UF_BOSS_AUTO_CARD"];
                    $arUsers[$userId]["FULL_NAME"] = $this->getFullName($arUser["NAME"], $arUser["LAST_NAME"]);
                    $arUsers[$userId]["WORK_POSITION"] = $arUser["WORK_POSITION"];
                    $arUsers[$userId]["COMPANY_NAME"] = $companyName;
                    $arUsers[$userId]["HEAD_DEPARTMENT"] = ($headDepartment == '') ? $headDepartment : TruncateText($headDepartmentName, self::CMP_DIR_DEPARTMENT_TRUNCATE_TEXT);
                    $arUsers[$userId]["MANAGER_DEPARTMENT"] = $arUser["DEPARTMENT_NAME"] ? TruncateText($arUser["DEPARTMENT_NAME"], self::CMP_DIR_DEPARTMENT_TRUNCATE_TEXT) : "";
                    $arUsers[$userId]["IS_MANAGER"] = $isManager;
                    $arUsers[$userId]["PERSONAL_PHOTO"] = $srcFile;
                    $arUsers[$userId]["DETAIL_URL_PERSONAL"] = str_replace("#ID#", $userId, PERSONAL_LINK_TEMPLATE);
                    $arUsers[$userId]["COUNTER"] = $newCounter;
                }
            }

        } catch (Throwable $e) {
            $this->errorCollection->setError(new Error(Loc::getMessage("CMP_DIR_NOT_DATA")));
        }

        return $arUsers ?? [];
    }

    /**
     * Метод получает данные о должности из штатного расписания, если сотрудник на данную должность еще не принят
     *
     * @param array $hrFilter - фильтр сортировки для HrTable
     * @param string $headDepartment - Название вышестоящего департамента
     * @param int $counterNum - уровень подчиненности для добавления класса в верстке
     *
     * @return array $arHr массив с данными о вакантных должностях с выборкой по указанным параметрам
     */
    private function getHrData(array $hrFilter, string $headDepartment, int $counterNum): array
    {
        try {

            $arHr = [];

            if (!empty($hrFilter)) {

                $headDepartment = $headDepartment ?? $this->headDepartment;
                $newCounter = ++$counterNum;

                $obResult = HrTable::getList([
                    "select" => [
                        "ID",
                        "UF_POS_EMPLOYEE_ID",
                        "WORK_POSITION" => "POSITION.UF_NAME",
                        "DEPARTMENT_NAME" => "DEPARTMENT.NAME",
                        "UF_POS_MANAGER_ID",
                        "UF_POS_MAIN_POSITION"
                    ],
                    "runtime" => [
                        new  Entity\ReferenceField(
                            "POSITION",
                            PositionsTable::class,
                            ["=this.UF_POS_POSITIONS_ID" => "ref.UF_GUID"]
                        ),
                        new  Entity\ReferenceField(
                            "DEPARTMENT",
                            SectionTable::class,
                            ["=this.UF_POS_DEPARTMENT_ID" => "ref.CODE"]
                        )
                    ],
                    "filter" => $hrFilter,
                ]);

                while ($arResult = $obResult->fetch()) {
                    $headDepartmentName = $this->getHeadDepartment((string)$arResult["UF_POS_MANAGER_ID"]);
                    $hrId = $arResult["UF_POS_EMPLOYEE_ID"] . '/' . $arResult["UF_POS_MANAGER_ID"];
                    $isManager = (($arResult["UF_POS_MAIN_POSITION"] == self::CMP_DIR_WORK_MAIN) && !empty($this->hasEmployee([$arResult["UF_POS_EMPLOYEE_ID"]]))) ? true : false;

                    $arHr[$hrId]["ID"] = $arResult["UF_POS_EMPLOYEE_ID"];
                    $arHr[$hrId]["ID_ST"] = $arResult["UF_POS_EMPLOYEE_ID"];
                    $arHr[$hrId]["WORK_POSITION"] = $arResult["WORK_POSITION"];
                    $arHr[$hrId]["HEAD_DEPARTMENT"] = ($headDepartment == '') ? $headDepartment : TruncateText($headDepartmentName, self::CMP_DIR_DEPARTMENT_TRUNCATE_TEXT);
                    $arHr[$hrId]["MANAGER_DEPARTMENT"] = $arResult["DEPARTMENT_NAME"] ? TruncateText($arResult["DEPARTMENT_NAME"], self::CMP_DIR_DEPARTMENT_TRUNCATE_TEXT) : "";
                    $arHr[$hrId]["IS_MANAGER"] = $isManager;
                    $arHr[$hrId]["PERSONAL_PHOTO"] = self::CMP_DIR_DEFAULT_PERSONAL_PHOTO;
                    $arHr[$hrId]["COUNTER"] = $newCounter;
                    $arHr[$hrId]["DETAIL_URL_PERSONAL"] = '';
                }

            }

        } catch (Throwable $e) {
            $this->errorCollection->setError(new Error(Loc::getMessage("CMP_DIR_NOT_DATA")));
        }

        return $arHr ?? [];
    }

    /**
     * Метод возвращает название департамента руководителя
     * @param string $idSt
     * @return string
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getHeadDepartment(string $idSt): string
    {
        try {
            $obResult = HrTable::getlist([
                'filter' => ['UF_POS_EMPLOYEE_ID' => $idSt],
                'select' => [
                    'ID',
                    "DEPARTMENT_NAME" => "DEPARTMENT.NAME",
                ],
                'runtime' => [
                    new  Entity\ReferenceField(
                        'DEPARTMENT',
                        SectionTable::class,
                        ['=this.UF_POS_DEPARTMENT_ID' => 'ref.CODE']
                    )
                ],
            ]);

            if ($arResult = $obResult->fetch()) {
                $departmentName = $arResult['DEPARTMENT_NAME'];
            }
        } catch (Throwable $e) {
            $this->errorCollection->setError(new Error(Loc::getMessage("CMP_DIR_NOT_DEPARTMENT_NAME")));
        }

        return $departmentName ?? '';
    }

    /**
     * Метод проверяет, есть ли у данной должности хоть одна должность в подчинении - не совместитель (для отображения стрелочки).
     * @param array $arrPositions - id всех должностей пользователя
     * @return array
     */
    private function hasEmployee(array $arrPositions): array
    {
        $arrEmployee = [];

        $obResult = HrTable::getlist([
            'filter' => [
                'UF_POS_MANAGER_ID' => $arrPositions,
                'UF_POS_MAIN_POSITION' => '1',
                '<=UF_DATE_FROM' => date('d.m.Y'),
                '>UF_DATE_TO' => date('d.m.Y'),
            ],
            'order' => ['ID'],
            'select' => [
                'ID'
            ]
        ]);

        while ($arResult = $obResult->fetch()) {
            $arrEmployee[] = $arResult['ID'];
        }

        return $arrEmployee;
    }

    /**
     * Метод меняет стандартные кавычки на «» в названии организации
     *
     * @param string $companyName - название организации
     *
     * @return string $newCompanyName - название организации с кавычками «»
     */
    private function changeCompanyName(string $companyName): string
    {
        $newCompanyName = preg_replace_callback(
            '#(([\"]{2,})|(?![^\W])(\"))|([^\s][\"]+(?![\w]))#u',
            function ($matches) {
                if (count($matches) === 3) return "«»";
                else if ($matches[1]) return str_replace('"', "«", $matches[1]);
                else return str_replace('"', "»", $matches[4]);
            },
            $companyName
        );

        return $newCompanyName ?? '';
    }

    /**
     * Метод возвращает все id должностей пользователя
     *
     * @param string $headIdSt - id_st пользователя
     * @param string $autoCardId - Auto_Card пользователя
     *
     * @return array $arPositions id должностей пользователя
     */
    private function getAllPositions(string $headIdSt, string $autoCardId): array
    {
        $arPositions = [];

        if ($autoCardId != '') {
            $obPositions = HrTable::getlist([
                'filter' => [
                    'UF_POS_AUTO_CARD' => $autoCardId,
                    '<=UF_DATE_FROM' => date('d.m.Y'),
                    '>UF_DATE_TO' => date('d.m.Y'),
                ],
                'select' => ['ID', 'UF_POS_EMPLOYEE_ID']
            ]);

            while ($arResPositions = $obPositions->fetch()) {
                $arPositions[] = $arResPositions['UF_POS_EMPLOYEE_ID'];
            }
        }

        if (!in_array($headIdSt, $arPositions)) {
            $arPositions[] = $headIdSt;
        }

        return $arPositions ?? [];
    }

    /**
     * Метод возвращает массив c id сотрудников, которые приняты на должность из штатного расписания
     *
     * @param array $arSubordinatesHrId - массив с ID должностей сотрудников из штатного расписания
     *
     * @return array $arEmployeeId массив с сотрудниками, которые приняты на должность из штатного расписания
     */
    private function getEmployees(array $arSubordinatesHrId): array
    {
        $arEmployeeId = [];

        $obEmployee = UserTable::getlist([
            'filter' => [
                '!ACTIVE' => false,
                '>UF_DATE_OUT' => date('d.m.Y'),
                'UF_BOSS_ID_ST' => $arSubordinatesHrId
            ],
            'order' => ['ID'],
            'select' => ['ID', 'UF_BOSS_ID_ST']
        ]);

        foreach ($obEmployee as $employee) {
            $arEmployeeId[$employee['UF_BOSS_ID_ST']] = $employee['UF_BOSS_ID_ST'];
        }

        return $arEmployeeId ?? [];
    }

    /**
     * Метод возвращает массив с id должностей всех подчиненных c сортировкой на принятых и не принятых в штат сотрудников
     *
     * @param array $arHeadHrId - ID должностей руководителя из штатного расписания
     *
     * @return array $arSubordinatesId массив с id должностей всех подчиненных
     */
    private function getSubordinatesHr(array $arHeadHrId): array
    {
        $arSubordinatesHrId = [];
        $arSubordinatesId = [];

        $obHr = HrTable::getlist([
            'filter' => [
                'UF_POS_MANAGER_ID' => $arHeadHrId,
                'UF_POS_MAIN_POSITION' => '1',
                '<=UF_DATE_FROM' => date('d.m.Y'),
                '>UF_DATE_TO' => date('d.m.Y'),
            ],
            'order' => ['ID'],
            'select' => ['ID', 'UF_POS_EMPLOYEE_ID']
        ]);

        while ($arHr = $obHr->fetch()) {
            $arSubordinatesHrId[$arHr['UF_POS_EMPLOYEE_ID']] = $arHr['UF_POS_EMPLOYEE_ID'];
        }

        $arEmployeeId = $this->getEmployees($arSubordinatesHrId);
        $arHrId = array_diff($arSubordinatesHrId, $arEmployeeId);
        $arSubordinatesId["USER_FILTER"] = $arEmployeeId;
        $arSubordinatesId["HR_FILTER"] = $arHrId;

        return $arSubordinatesId ?? [];
    }

    /**
     * Метод возвращает путь к файлу
     *
     * @param string $subDir - Подраздел
     * @param string $fileName - Имя файла
     *
     * @return string путь к файлу
     */
    private function getFileSrc(string $subDir, string $fileName): string
    {
        $srcFile = CFile::GetFileSRC(["SUBDIR" => $subDir, "FILE_NAME" => $fileName]);

        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $srcFile)) {
            $srcFile = self::CMP_DIR_DEFAULT_PERSONAL_PHOTO;
        }

        return $srcFile ?? '';
    }

    /**
     * Метод возвращает полное имя сотрудника
     *
     * @param string $firstName - Имя
     * @param string $lastName - Фамилия
     *
     * @return string Полное имя
     */
    private function getFullName(string $firstName, string $lastName): string
    {
        return CUser::FormatName('#NAME# #LAST_NAME#', ['NAME' => $firstName, 'LAST_NAME' => $lastName], false, false);
    }
}
