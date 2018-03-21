<?php
namespace DF\Base;

class Key
{
    const REDIS_MAIN = 'main';
    //导入k_accumulate_report表的计数, string
    const IMPORT_TO_ACCUMULATE = 'import:accumulate5';
    const FIX_ACCUMULATE = 'fix:accumulate';
    //em表数据, hash,仅用于收发存
    const SHOP_EXPENSE_MATERIAL = 'shop:emdetail:{%s}';
    //单据, hash，只记type
    const SHOP_EXPENSE = 'shop:expense:{%s}';
    //流水表, hash
    const SHOP_CYCLE = 'shop:cycle:{%s}:%s';
    const VIP_SHOP_OPEN = 'vip_shop_open';
}