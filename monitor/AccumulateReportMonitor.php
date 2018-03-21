<?php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';
/**
 * Description of AccumulateReportMonitor
 *
 * @author pzhu
 */
class AccumulateReportMonitor
{
    private $scope;
    private $dt;
    private $shopId;

    public function __construct($shopId, $dt, $scope = 1)
    {
        $this->shopId = $shopId;
        $this->dt = $dt;
        $this->scope = $scope;
    }
    
    public function run()
    {
        $groupShopId = \DF\Component\Shop\ShopsComp::getGroupShopId($this->shopId);
        $firstDay = strtotime($this->dt.'01');
        $endDay = strtotime('+1month', $firstDay);
        $where[0] = 'dt>='.$firstDay;
        $where[1] = 'dt<'.$endDay;
        $where[3] = 'warehouseId>0';
        $where['groupShopId'] = $groupShopId;
        $where['shopId'] = $this->shopId;
        $where['scope'] = $this->scope;
        $fields = array(
            'id', 'groupMaterialId', 'warehouseId', 'expenseId', 'num', 'totalPrice',
        );
        $data = \DF\Data\Sheet\AccumulateReportData::getData($where, array('id' => array('expenseId', 'groupMaterialId', 'warehouseId'), 'overwrite' => true), $fields);
        $expenseIds = array_keys($data);
        $expenseInfo = \DF\Data\Shop\ExpenseData::getKv(['id' => $expenseIds], 'id', 'type');
        $emData = \DF\Data\Shop\ExpenseMaterialData::getData(['expenseId' => $expenseIds, 'deletedAt is null'], [], ['expenseId', 'materialId', 'warehouseId', 'excludeTaxTotal', 'materialRealCount']);
        $check = array();
        foreach($emData as $line){
            if($line['materialRealCount'] == 0 && in_array($expenseInfo[$line['expenseId']], [87, 96])){
                continue;
            }
            $gmInfo = \DF\Component\Shop\MeterialComp::getMaterial($line['materialId']);
            $check[$line['expenseId']][$gmInfo['groupMaterialId']][$line['warehouseId']] = 1;
            if(!isset($data[$line['expenseId']][$gmInfo['groupMaterialId']][$line['warehouseId']])){
                if(isset($data[$line['expenseId']][$gmInfo['groupMaterialId']])){
                    echo "expenseId={$line['expenseId']} AND materialId={$line['materialId']} AND warehouseId={$line['warehouseId']}, warehouseId in k_accumulate_report: ".key($data[$line['expenseId']][$gmInfo['groupMaterialId']]) . "\n";
                }else{
                    echo "expenseId={$line['expenseId']} AND materialId={$line['materialId']} AND warehouseId={$line['warehouseId']}, not exists in k_accumulate_report\n";
                }
                continue;
            }
            $accData = $data[$line['expenseId']][$gmInfo['groupMaterialId']][$line['warehouseId']];
            if($accData['num'] != $line['materialRealCount']){
                echo "expenseId={$line['expenseId']} AND materialId={$line['materialId']} AND warehouseId={$line['warehouseId']}, num: {$accData['num']}, materialRealCount: {$line['materialRealCount']}\n";
                continue;
            }
            if(abs($accData['totalPrice']) != $line['excludeTaxTotal']){
                echo "expenseId={$line['expenseId']} AND materialId={$line['materialId']} AND warehouseId={$line['warehouseId']}, totalPrice: {$accData['totalPrice']}, excludeTaxTotal: {$line['excludeTaxTotal']}\n";
                continue;
            }
            //echo "expenseId: {$line['expenseId']}, materialId: {$line['materialId']}, warehouseId: {$line['warehouseId']}, OK\n";
        }
        $toDelete = array();
        foreach($data as $expenseId => $exArr){
            foreach($exArr as $gId => $gArr){
                foreach($gArr as $warehouseId => $wArr){
                    if(!isset($check[$expenseId][$gId][$warehouseId])){
                        echo "expenseId: {$expenseId}, groupMateraiId: {$gId}, warehouseId: {$warehouseId}, not exists in expense_material\n";
                        $toDelete[] = $wArr['id'];
                        continue;
                    }
                }
            }
        }
        echo "id in k_accumulate_report to delete: ".json_encode($toDelete) . "\n";
    }
}
if($argc < 3){
    exit("Usage: php ".__FILE__ . ' <shopId> <year-month> <scope>');
}
$shopId = $argv[1];
$dt = $argv[2];
$scope = 1;
if(isset($argv[3])){
    $scope = $argv[3];
}
$app = new AccumulateReportMonitor($shopId, $dt, $scope);
$app->run();