<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Highloadblock as HL;
use Bitrix\Main\{
	Application,
    Loader,
    ORM\Data\Result,
    Localization\Loc,
    Type\RandomSequence,
    Type\DateTime
};

use Bitrix\Sale\{
	Internals\BasketTable,
	Fuser
};

CBitrixComponent::includeComponentClass("ml:marketing_el");

class BasketShareList extends CBitrixComponent
{
    const HL_NAME = "ShareBasket";
    const CODE_LENGTH = 10;

    protected $arErrors = [];
    protected $allMarketing;
    protected $hlEntity;

    private function includeModules()
    {
        if (!Loader::includeModule("highloadblock")) {
            $this->setError(Loc::getMessage('NOT_INSTALL_HL'));
        }

        return !$this->hasError();
    }

    public function setError($message)
    {
        if ($message instanceof Result) {
            $errors = $message->getErrorMessages();
        } else {
            $errors = [$message];
        }

        foreach ($errors as $error) {
            if (!in_array($error, $this->arErrors, true)) {
                $this->arErrors[] = $error;
            }
        }

        return false;
    }

    protected function hasError()
    {
        return boolval($this->arErrors);
    }

    public function onPrepareComponentParams($params)
    {
	    $request = Application::getInstance()->getContext()->getRequest();
		
		$params["CODE"] = $request->getQuery("CODE");
		$params["CACHE_TIME"] = 86400;
		
        return $params;
    }

    public function executeComponent()
    {
        if (!(!empty($this->arParams["CODE"]) && $this->includeModules())) {
            return false;
        }

        global $USER;
        
        if ($this->startResultCache(false, [$USER->GetGroups(), $this->arParams])) {
            $this->getMarketing();
            $this->makeResult();
            $this->includeComponentTemplate();
        }
    }

    public function makeResult()
    {
        $this->getEntity();

        $db = $this->hlEntity::getList([
            'filter' => ['=UF_CODE' => $this->arParams["CODE"]],
            'limit' => 1,
            'select' => ['*'],
        ]);

        if ($result = $db->fetch()) {
            $basketItems = json_decode($result["UF_JSON_ITEMS"], true);
            $this->prepareItems($basketItems);
        } else {
            $this->AbortResultCache();
        }
    }

    protected function getMarketing()
    {
        global $APPLICATION;
        $this->allMarketing = $APPLICATION->IncludeComponent(
            "mirkrasok:marketing",
            ".default",
            [
                "IBLOCK_ID" => IBLOCK_MARKETING_ID,
                "CACHE_TYPE" => "N",
                "CACHE_TIME" => "3600000",
            ],
            false
        );
    }

    protected function prepareItems($basketItems)
    {
        $prepareArray = [];

        $iCurrentPriceID = CPriceExt::GetCurrentPriceID();

        foreach ($GLOBALS["PRICE_LINE"] as $sCode => $iValue) {
            if ($GLOBALS["AVAILABLE_PRICES"][$sCode]["ID"] == $iCurrentPriceID) {
                $arResult["PRICE_CODE"] = $sCode;
            }
        }

        $total = 0;
		
        foreach ($basketItems as $item) {
            $el = CIBlockExt::GetSKUItem($item["ID"]);
			
            $arPrices = CPriceExt::CheckEmptyPrice($item["ID"]);
			
            $skuM = infoProductsMarketing::skuMarketingExtract($item["ID"], $this->allMarketing);
            if ($arResultSku = infoProductsMarketing::skuMarketingApply($skuM)) {
                $el["MARKETING"] = $arResultSku;
            };

            $arCML2Link = GP(["IBLOCK_ID" => IB_SKU, "ID" => $item["ID"]]);
			
            if ($iElementID = IntVal($arCML2Link["CML2_LINK"])) {
                $el["ELEMENT"] = CIBlockExt::GetElement($iElementID);
            }

            $el["PRICE"] = $el["PRICE_MATRIX"][$arResult["PRICE_CODE"]];
            $el["PRICES"] = $arPrices;
            
            $el["SHARE_BASKET"] = $item;

            $prepareArray[] = $el;

            $total += (($el["MARKETING"]["price_active"] ?: $el["PRICE"]["DISCOUNT_VALUE"]) * $item["QUANTITY"]);
            $totalPrice += ($el["PRICE"]["DISCOUNT_VALUE"] * $item["QUANTITY"]);
            if (!empty($item["COLORATE"])) {
                $colorate = $item["COLORATE"];
                $total += ($colorate["PRICE"] * $colorate["VOLUME"]);
                $totalPrice += ($colorate["PRICE"] * $colorate["VOLUME"]);
            }
        }

		
        $this->arResult = [
            "ITEMS" => $prepareArray,
            "TOTAL_PRICE" => $totalPrice,
            "TOTAL" => $total,
            "DATA_JS" => $basketItems,
        ];
    }

    private function getEntity()
    {
        $hlblock = HL\HighloadBlockTable::getList([
            'filter' => ['=NAME' => self::HL_NAME],
        ])->fetch();

        $this->hlEntity = HL\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
    }
}