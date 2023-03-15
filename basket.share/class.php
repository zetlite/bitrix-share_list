<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Highloadblock as HL;
use Bitrix\Main\{
    Loader,
    ORM\Data\Result,
    Localization\Loc,
    Type\RandomSequence,
    Type\DateTime,
    Error,
    Errorable,
    ErrorCollection,
    Engine\ActionFilter,
    Engine\Contract\Controllerable
};


class ShareBasket extends \CBitrixComponent  implements Controllerable, Errorable
{
    const HL_NAME = "ShareBasket";
    const CODE_LENGTH = 10;

    protected $arErrors = [];
    protected $hlEntity;

    public function onPrepareComponentParams($params)
    {
        $this->errorCollection = new ErrorCollection();

        return $params;
    }

    private function includeModules()
    {
        if (!Loader::includeModule("highloadblock")) {
            $this->setError(Loc::getMessage('NOT_INSTALL_HL'));
        }

        return !$this->hasError();
    }

    public function executeComponent()
    {
        if ($this->includeModules()) {
            $this->getEntity();

            $this->makeResult();
            $this->includeComponentTemplate();
        }
    }

    private function getEntity()
    {
        $hlblock = HL\HighloadBlockTable::getList([
            'filter' => ['=NAME' => self::HL_NAME],
        ])->fetch();

        $this->hlEntity = HL\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
    }

    public function setError($message): bool
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

    protected function hasError(): bool
    {
        return boolval($this->arErrors);
    }

    public function makeResult()
    {
		
        $hash = md5(serialize($this->arParams["DATA"]));

        $shareBasket = $this->hlEntity::getList([
            "select" => ["*"],
            "order" => ["ID" => "ASC"],
            "filter" => ["=UF_HASH" => $hash],
        ])->fetch();

        if ($shareBasket) {
            $code = $shareBasket["UF_CODE"];
        } else {
            $code = $this->generateCode();

            $data = [
                "UF_HASH" => $hash,
                "UF_CODE" => $code,
                "UF_DATE_CREATE" => new DateTime(),
                "UF_JSON_ITEMS" => json_encode($this->arParams["DATA"]),
            ];

            if ($GLOBALS['USER'] && $GLOBALS['USER'] instanceof \CUser) {
                $data['UF_USER_ID'] = intval($GLOBALS['USER']->GetID());
            }

            $this->hlEntity::add($data);
        }

        $this->arResult["LINK"] = "https://{$_SERVER["SERVER_NAME"]}/personal/share_basket/?CODE={$code}";
    }

    protected function generateCode()
    {
        do {
            $code = '';

            while (strlen($code) < self::CODE_LENGTH) {
                $rs = new RandomSequence(phptime() . (random_int(1, 10000) + strlen($code) * random_int(1, 10000)) . __FILE__);
                $code .= preg_replace('/[^a-z0-9]/', '', $rs->randString(12));
            }

            $code = substr($code, 0, self::CODE_LENGTH);

            $result = $this->hlEntity::getList([
                'filter' => ['=UF_CODE' => $code],
                'limit' => 1,
                'select' => ['ID'],
            ]);
        } while ($result->fetch());

        return $code;
    }

    public function getErrorByCode($code)
    {
        return $this->errorCollection->getErrorByCode($code);
    }

    public function configureActions()
    {
        return [
            'send' => [
                'prefilters' => []
            ]
        ];
    }

    public function getErrors()
    {
        return $this->errorCollection->toArray();
    }

    public function sendAction(array $data): array
    {
        try {
            $basket = json_decode($data["basket"], true);
            switch ($data["action"]) {
                case "replace":
                    $this->changeBasket($basket, true);
                    break;
                case "add":
                    $this->changeBasket($basket);
                    break;
            }

            return [
                "result" => "Все ок",
            ];
        } catch (Exception $e) {
            $this->errorCollection[] = new Error($e->getMessage());
            return [
                "result" => "Произошла ошибка",
            ];
        }
    }

    public function changeBasket($data, $delete = false)
    {
        global $APPLICATION;

        if ($delete) {
            $this->deleteCurBasket();
        }

        foreach ($data as $newBasketItem) {

            $colorate = $newBasketItem["COLORATE"];

            $iQuantity = IntVal($newBasketItem["QUANTITY"]) ?: 1;
            $iColorID = IntVal($colorate["COLOR_ID"]);
            $iElementID = IntVal($newBasketItem["ID"]);
            if ($iElementID) {
                $propEl = GP(["IBLOCK_ID" => IB_SKU, "ID" => $iElementID], ["CML2_LINK", "KOD_7_7", "BARCODE"]);


                $arRewrite = [];
                if ($iParentID = IntVal($propEl["CML2_LINK"])) {
                    $arElement = $APPLICATION->IncludeComponent("mk:catalog.element", ".default", ["ID" => $iParentID, "ARRAY" => "Y"]);
                    $arRewrite["DETAIL_PAGE_URL"] = $arElement["DETAIL_PAGE_URL"];
                }
                $arProperties = [];
                if ($iColorID) {

                    $arBasketParams = [
                        "COLLECTION_NAME" => "Коллекция колеровки",
                        "COLOR_ID" => "ID цвета",
                        "COLOR_NAME" => "Название цвета",
                        "HEX" => "HEX-код цвета",
                    ];

                    foreach ($arBasketParams as $sParamCode => $sName) {
                        $arItem = [
                            "CODE" => $sParamCode,
                            "NAME" => $sName,
                            "VALUE" => htmlspecialchars($colorate[$sParamCode]),
                        ];
                        $arProperties[$sParamCode] = $arItem;
                    }
                }

                $basketRes = BasketTable::getList([
                    'filter' => [
                        'PRODUCT_ID' => $iElementID,
                        'FUSER_ID' => Fuser::getId(),
                        'ORDER_ID' => NULL,
                        'LID' => SITE_ID,
                        'CAN_BUY' => 'Y',
                    ],
                    'select' => ['ID'],
                ]);
                if ($item = $basketRes->fetch()) {
                    $basketPropRes = Bitrix\Sale\Internals\BasketPropertyTable::getList([
                        'filter' => [
                            "BASKET_ID" => $item['ID'],
                            'CODE' => 'BONUS_ADD',
                        ],
                    ]);
                    if ($prop = $basketPropRes->fetch()) {
                        unset($prop['ID'], $prop['BASKET_ID']);
                        $arProperties[] = $prop;
                    }
                }

                $iBasketID = Add2BasketByProductID($iElementID, $iQuantity, $arRewrite, $arProperties);

                if ($iBasketID && $iColorID && ($iColorElementID = IntVal($colorate["COLOR_SKU_ID"]))) {
                    $iVolume = FloatVal($_REQUEST["VOLUME"]);
                    if (!$iVolume) {
                        $iVolume = 1;
                    }

                    $arProp = GP(["ID" => $iColorElementID, "IBLOCK_ID" => IB_ROB], ["PRICE_SET", "SKU_ID"]);

                    $iPrice = FloatVal($arProp["PRICE_SET"]);

                    if ($arProp["PRICE_SET"] == 0) {
                        $iPrice = ceil($colorate["PRICE"] / 10) * 10 / $iVolume;
                    }

                    $iQuantity *= $iVolume;
                    $iSKUColorID = FloatVal($arProp["SKU_ID"]);

                    if ($iSKUColorID && $iPrice) {
                        $this->addColorate($iElementID, $iBasketID, $iColorElementID, $iPrice, $iQuantity, $arProperties);
                    }
                }
            }
        }

        CPriceExt::RecountPrices();
    }

    public static function addColorate($iProductID, $iBasketID, $iColorID, $iPrice, $iQuantity, $arProperties = [])
    {

        $obItem = CIBlockElement::GetList([], ["ID" => $iColorID], false, false, ["ID", "XML_ID", "NAME", "IBLOCK_ID", "IBLOCK.XML_ID"]);
        $iColorBasketID = 0;
        if ($arItem = $obItem->Fetch()) {
            $obProduct = CIBlockElement::GetList([], ["ID" => $iProductID], false, false, ["ID", "NAME"]);
            if ($arProduct = $obProduct->Fetch()) {
                self::clearBasketColorates($iBasketID);
                $arColorateProperties = [
                    "IS_COLOR_TO" => [
                        "CODE" => "IS_COLOR_TO",
                        "NAME" => "Колеровка для",
                        "VALUE" => $iBasketID,
                    ],
                ];
                $arColorateProperties = array_merge($arColorateProperties, $arProperties);

                $arFields = [
                    "FUSER_ID" => Fuser::getId(),
                    "PRODUCT_ID" => $iColorID,
                    "PRICE" => $iPrice,
                    "CURRENCY" => "RUB",
                    "QUANTITY" => $iQuantity,
                    "LID" => "s1",
                    "DELAY" => "N",
                    "CAN_BUY" => "Y",
                    "NAME" => $arItem["NAME"],
                    "MODULE" => "1c_exchange",
                    "PRODUCT_PROVIDER_CLASS" => false,
                    "CATALOG_XML_ID" => $arItem["IBLOCK_XML_ID"],
                    "PRODUCT_XML_ID" => $arItem["XML_ID"],
                    "CUSTOM_PRICE" => "Y",
                    "DISCOUNT_PRICE" => 0,
                    "PROPS" => $arColorateProperties,
                ];

                $iColorBasketID = CSaleBasket::Add($arFields);
            }
        }
        return $iColorBasketID;
    }

    public static function clearBasketColorates($iBasketID)
    {
        $obProperties = CSaleBasket::GetPropsList(
            ['BASKET_ID' => 'ASC', 'SORT' => 'ASC', 'ID' => 'ASC'],
            ["CODE" => "IS_COLOR_TO", "VALUE" => $iBasketID]
        );
        while ($arItem = $obProperties->Fetch()) {
            $obBasketItems = CSaleBasket::GetList(
                ["ID" => "ASC"],
                [
                    'FUSER_ID' => Fuser::getId(),
                    "LID" => SITE_ID,
                    "ORDER_ID" => "NULL",
                    "ID" => $arItem["BASKET_ID"],
                ],
                false,
                false,
                [
                    "ID",
                    "QUANTITY",
                    "PRODUCT_ID",
                ]
            );
            if ($arBasketItem = $obBasketItems->Fetch()) {
                CSaleBasket::Delete($arBasketItem["ID"]);
            }
        }
    }

    public function deleteCurBasket()
    {
        $basketRes = BasketTable::getList([
            'filter' => [
                'FUSER_ID' => Fuser::getId(),
                'ORDER_ID' => NULL,
                'LID' => SITE_ID,
            ],
            'select' => ['ID'],
        ]);

        while ($res = $basketRes->fetch()) {
            BasketTable::delete($res["ID"]);
        }
    }
}