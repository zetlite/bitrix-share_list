<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
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
?>
<script src="https://yastatic.net/share2/share.js" async></script>
<div class="" style="max-width: 505px; padding: 55px 40px; box-shadow: 0px 2px 14px rgba(0, 0, 0, 0.25);">
    <div class="bs-title">Поделиться своей корзиной</div>
    <div class="bs-share">
        <div class="sub">Скопируйте уникальную ссылку:</div>
        <div class="form__cols">
            <div class="form__col">
                <input class="input-gray" type="text" name="basket_share"
                       value="<?= $arResult["LINK"]?>">
            </div>
        </div>
        <div class="btn-yel" data-js="copy_site">Копировать ссылку</div>
        <div class="soc_new">
            <div class="ya-share2" data-curtain data-shape="round"
                 data-services="vkontakte,moimir,telegram,odnoklassniki,whatsapp,twitter,"></div>
        </div>
    </div>

</div>