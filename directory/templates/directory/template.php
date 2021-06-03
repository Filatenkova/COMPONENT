<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
/** @var array $arParams */
/** @var array $arResult */
/** @global \CMain $APPLICATION */
/** @global \CUser $USER */
/** @global \CDatabase $DB */
/** @var \CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var array $templateData */
/** @var \CBitrixComponent $component */
$this->setFrameMode(true);

use Bitrix\Main\UI\Extension;
Extension::load("ui.alerts");

?>
<div class="accordion-root mtop30">
    <div class="accordion">
        <div class="accordion-item accordion-item__main" data-users-container>
            <div data-errors-container>
                <?php
                if (!empty($arResult['ERRORS'])) {
                    foreach ($arResult['ERRORS'] as $errorMessage) {
                        ?>
                        <div class="ui-alert ui-alert-danger">
                            <span class="ui-alert-message">
                                <?= $errorMessage ?>
                            </span>
                        </div>
                        <?php
                    } return;
                } ?>
            </div>
            <div class="accordion-header accordion-header__main">
                <?php foreach($arResult["HEAD"] as $head) { ?>
                    <div class="directory-item">
                        <div class="directory-info">
                            <a class="directory-img" href="<?= $head["DETAIL_URL_PERSONAL"] ?>" title="<?= $head["FULL_NAME"] ?>">
                                <img src="<?= $head["PERSONAL_PHOTO"] ?>" title="<?= $head["FULL_NAME"] ?>" alt="<?= $head["FULL_NAME"] ?>" class="directory-img__item">
                            </a>
                            <div class="directory-text">
                                <a title="<?= $head["FULL_NAME"] ?>" href="<?= $head["DETAIL_URL_PERSONAL"] ?>" class="directory-text__name">
                                    <?= $head["FULL_NAME"] ?>
                                </a>
                                <a title="<?= $head["WORK_POSITION"] ?>" href="<?= $head["DETAIL_URL_PERSONAL"] ?>" class="directory-text__position">
                                    <?= $head["WORK_POSITION"] ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="border-none">
                <div class="accordion accordion-child">
                    <div class="accordion-item">
                        <?php foreach($arResult["BRANCH_USERS"] as $key => $child) { ?>
                            <div class="accordion-header" data-counter="<?= $child['COUNTER'] ?>" data-head-dep="<?= $child['MANAGER_DEPARTMENT'] ?>" data-id-element="<?= $child['ID_ST'] ?>" data-auto-card="<?= $child['AUTO_CARD'] ?>" data-action="viewSubordinate">
                                <div class="directory-item tree level-child" data-accordion="<?= $child['ID_ST'] ?>">
                                    <div class="directory-info">
                                        <a title="<?= $child["FULL_NAME"] ?>" href="#" class="directory-img level-child__img">
                                            <img src="<?= $child['PERSONAL_PHOTO'] ?>"
                                                 alt="<?= $child["FULL_NAME"] ?>"
                                                 title="<?= $child["FULL_NAME"] ?>"
                                                 class="directory-img__item"
                                                 data-href="<?= $child["DETAIL_URL_PERSONAL"] ?>"
                                            >
                                        </a>
                                        <div class="directory-text">
                                            <div class="directory-text--top">
                                                <?php
                                                if ($child["FULL_NAME"] != '') { ?>
                                                <a title="<?= $child["FULL_NAME"] ?>" data-href="<?= $child["DETAIL_URL_PERSONAL"] ?>" href="#" class="directory-text__name">
                                                    <?= $child["FULL_NAME"] ?>
                                                </a>
                                                <?php
                                                }
                                                if ($child['MANAGER_DEPARTMENT']) { ?>
                                                    <span class="directory-text__department">
                                                        <?= $child['MANAGER_DEPARTMENT'] ?>
                                                    </span>
                                                <?php } ?>
                                            </div>
                                            <div class="directory-text--bottom">
                                                <a title="<?= $child['WORK_POSITION'] ?>" data-href="<?= $child["DETAIL_URL_PERSONAL"] ?>" href="#" class="directory-text__position">
                                                    <?= $child['WORK_POSITION'] ?>
                                                </a>
                                                <?php if ($child['COMPANY_NAME']) { ?>
                                                    <span class="directory-text__company">
                                                        <?= $child['COMPANY_NAME'] ?>
                                                    </span>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($child['IS_MANAGER']) { ?>
                                        <div class="accordion-header__icon accordion-header__icon_down"></div>
                                        <div class="accordion-header__icon accordion-header__icon_up hidden"></div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="border-none hidden">
                                <div class="accordion accordion-child">
                                    <div class="accordion-item" data-visible-tree-container>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    new SubordinateTree({
        selectors: {
            viewSubordinate: '[data-action="viewSubordinate"]',
            usersContainer: '[data-users-container]',
            visibleTree: '[data-visible-tree-container]',
            errorsContainer: '[data-errors-container]',
        }
    });
</script>
