<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */

/** @var CBitrixComponent $component */

use Bitrix\Main\Localization\Loc;

$this->setFrameMode(false);
$arGroups = $USER->GetUserGroup($USER->GetID());
$arBases = CIBlockExt::GetBases();

$countBasket = count($arResult["BASKET_ITEMS_NEW"]);
$text = Loc::getMessage(
	'IW_ITEMS',
	[
		"#COUNT#" => $countBasket,
		"#TEXT#" => PluralForm(count($arResult["BASKET_ITEMS_NEW"]), ["товар", "товара", "товаров"]),
	]
);
$APPLICATION->SetPageProperty("basket_items", $text);

if ($arResult["ITEMS"]) { ?>
    <div class="basketn__cols basket_share">
        <div class="basketn__col">
            <div class="basketn-table">
                <input type="hidden" value='<?= json_encode($arResult["DATA_JS"]) ?>' name="share_basket">
				<? foreach ($arResult["ITEMS"] as $arItem) {
					$basketShare = $arItem["SHARE_BASKET"];
					$colorate = $basketShare["COLORATE"] ?: false;
					$arElement = $arItem['ELEMENT'];
					$arSKUElement = $arElement["SKU_ITEMS"][$arItem['ID']];
					
					/*todo не забыть посмотреть как это в элементе реализовано и поправить $arItem["PRICE"]["VALUE_VAT"]*/
					?>
                    <div class="basketn-table__item" data-id="<?= $arItem['ID'] ?>">
                        <div data-block-discount>
							<? // Информация по акциям примененным к корзине
							if (!empty($arItem["MARKETING"])) { ?>
                                <div class="how-sale">
									<?= Loc::getMessage(
										'DISCOUNT_ALL',
										[
											"#PERCENT#" => getPercentPrice($arItem["MARKETING"]["price_old"] - $arItem["MARKETING"]["price_active"], $arItem["PRICES"]["RRC"]),
										]
									);
									?>
                                    <div class="how-sale__d_new">
										<?
										if (!empty($arItem["MARKETING"])) {
											foreach ($arItem["MARKETING"]['actions'] as $d_key => $d_val) {
												if (!empty($d_val['URL'])) { ?>
                                                    <div class="sale_description 1">
                                                        <a href="<?= $d_val['URL'] ?>">
															<?= $d_val['DISCOUNT_TITLE'] ?>
                                                        </a>
                                                    </div>
												
												<? } else { ?>
                                                    <div class="sale_description">
                                                        <span>
															<?= $d_val['DISCOUNT_TITLE'] ?>
                                                        </span>
                                                    </div>
												<? }
											}
										} ?>
                                    </div>
                                </div>
							<? } ?>
                        </div>
                        <div class="basketn-table__cols first">
                            <div class="basketn-table__left first">
                                <div class="pic">
                                    <a href="javascript:$.fancybox({href:'<?= GR($arElement["PICTURE"], 1000) ?>'})">
                                        <img src="<?= GR($arElement["PICTURE"], 120, 0) ?>"
                                             alt="<?= $arElement["NAME"] ?>">
                                    </a>
                                </div>
                            </div>
                            <div class="basketn-table__right border">
                                <div class="basketn-table__col">
                                    <div class="basketn-table__title">
                                        <a href="<?= $arElement["DETAIL_PAGE_URL"] ?>" target="_blank">
											<?= $arItem["NAME"] ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="basketn-table__col">
                                    <div class="price" data-price>
										<? if ($arItem["MARKETING"]) { ?>
											<?= F($arItem["MARKETING"]["price_active"], 1, 1) ?>
                                            <span class="old-price">
												<?= F($arItem["MARKETING"]["price_old"], 1, 1) ?>
                                            </span>
										<? } else { ?>
											<?= F($arItem["PRICES"]["RRC"], 1, 1) ?>
										<? } ?>
                                    </div>
                                    <div>
                                        <div data-discount-price>
											<? if ($arItem["MARKETING"]) { ?>
                                                <div class="price-sale">
													<?= Loc::getMessage(
														'PRICE_DISCOUNT',
														["#DISCOUNT_PRICE#" => F(($arItem["MARKETING"]["price_old"] - $arItem["MARKETING"]["price_active"]), 1, 1)]
													) ?>
                                                </div>
											<? } ?>
                                        </div>
                                        <div data-bonus-price>
											<? if ($arItem["MAYBE_BONUS"]) { ?>
                                                <div class="price-bonus">
                                                    <span><?= F($arItem["MAYBE_BONUS"], true) ?></span>
													<?= Loc::getMessage('MK_BONUS') ?>
                                                </div>
											<? } ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="basketn-table__col">
                                    <div class="quantity">
                                        <input type="text" name="" value="<?= intval($basketShare["QUANTITY"]) ?>"
                                               disabled>
                                    </div>
                                </div>
                            </div>
                        </div>
						<? if (!empty($colorate)) { ?>
                            <div class="basketn-table__cols last">
                                <div class="basketn-table__left">
                                    <div class="pic cl" <?= ($colorate["HEX"] ? "style='background-color:{$colorate["HEX"]}'" : "") ?>></div>
                                </div>
                                <div class="basketn-table__right">
                                    <div class="basketn-table__col">
                                        <div class="basketn-table__title sm">
											<?= Loc::getMessage('COLORATE') ?>
											<?= $colorate["COLLECTION_NAME"] ?>
											<?= $colorate["COLOR_NAME"] ?>
											<?= Loc::getMessage(
												"COLORATE_MEASURE",
												["#QUANTITY#" => FloatVal($colorate["VOLUME"])]
											) ?>
                                        </div>
                                    </div>
                                    <div class="basketn-table__col">
                                        <div class="price">
											<?= F($colorate["PRICE"] * $colorate["VOLUME"], 1, 1) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
						<? } ?>
                    </div>
				<? } ?>
            </div>
        </div>
        <div class="basketn__col">
            <div class="basketn__chisel" id="sticker">
                <div class="basketn__total">
                    <div class="basketn__total__item">
                        <span class="first">
                            Стоимость:
                        </span>
                        <div class="last" data-total-summary>
							<?= F($arResult["TOTAL_PRICE"]) ?>
                        </div>
                    </div>
                    <div data-total-discounts>
						<? if ($arResult["TOTAL_PRICE"] - $arResult["TOTAL"] > 0) { ?>
                            <div class="basketn__total__item">
                                <span class="first">
									<?= Loc::getMessage('DISCOUNT_NAME') ?>
                                </span>
                                <div class="last">
									<?= F($arResult["TOTAL_PRICE"] - $arResult["TOTAL"]) ?>
                                    <div class="more-n">
                                        <div class="more-n__t"><?= Loc::getMessage('DETAIL_DISCOUNTS') ?></div>
                                        <div class="more-n__d">
                                            <div>
												<?= Loc::getMessage(
													'DISCOUNT_SALE',
													["#PRICE#" => F($arResult["TOTAL_PRICE"] - $arResult["TOTAL"])]
												) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
						<? } ?>
                    </div>
                    <div class="basketn__total__item">
                        <span class="first bl">ИТОГО:</span>
                        <div class="last bl"><?= F($arResult["TOTAL"]) ?></div>
                    </div>
                </div>
                <button class="btn-bl" style="width: 100%; margin-bottom: 8px;"
                        data-js="add_to_basket">
					<?= Loc::getMessage('ADD_TO_BASKET') ?>
                </button>
                <button class="btn-yel" style="width: 100%; margin-bottom: 8px;"
                        data-js="replace_basket">
					<?= Loc::getMessage('REPLACE_CUR_BASKET') ?>
                </button>
            </div>
        </div>
    </div>
<? }