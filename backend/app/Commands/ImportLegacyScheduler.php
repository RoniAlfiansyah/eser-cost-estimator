<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Psr\Log\LoggerInterface;
use PDO;

class ImportLegacyScheduler extends BaseCommand
{
    protected $group = 'Scheduler';
    protected $name = 'scheduler:import-legacy';
    protected $description = 'Import master data dan project EserScheduler SQLite ke database Cost Estimator.';
    protected $usage = 'scheduler:import-legacy [sqlite-path]';

    public function run(array $params)
    {
        $path = $params[0] ?? 'C:/xampp/htdocs/EserScheduler/data/eser_scheduler.sqlite';
        if (! is_file($path)) {
            CLI::error('File SQLite tidak ditemukan: ' . $path);
            return;
        }

        $legacy = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db = db_connect();
        $owner = $db->table('users')->orderBy('id')->get()->getRowArray();
        if (! $owner) {
            CLI::error('Minimal satu user Cost Estimator harus tersedia.');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $db->transStart();

        $wbsMap = [];
        foreach ($legacy->query('SELECT * FROM master_wbs ORDER BY id') as $row) {
            $existing = $db->table('scheduler_master_wbs')->where('code', $row['code'])->get()->getRowArray();
            $values = ['name'=>$row['name'], 'prefix'=>$row['prefix'] ?? null, 'active'=>1, 'updated_at'=>$now];
            if ($existing) {
                $id=(int)$existing['id'];
            } else {
                $db->table('scheduler_master_wbs')->insert(['code'=>$row['code'],'created_at'=>$now]+$values); $id=(int)$db->insertID();
            }
            $wbsMap[(int)$row['id']]=$id;
        }

        foreach ($legacy->query('SELECT * FROM master_activities ORDER BY id') as $row) {
            if (!isset($wbsMap[(int)$row['wbs_id']])) continue;
            $existing=$db->table('scheduler_master_activities')->where('activity_code',$row['activity_code'])->get()->getRowArray();
            $values=['wbs_id'=>$wbsMap[(int)$row['wbs_id']],'name'=>$row['name'],'default_duration'=>$row['default_duration'],'default_pic'=>$row['default_pic'],'active'=>1,'updated_at'=>$now];
            if(!$existing)$db->table('scheduler_master_activities')->insert(['activity_code'=>$row['activity_code'],'created_at'=>$now]+$values);
        }

        $resourceMaps=['personnel'=>[],'equipment'=>[]];
        foreach ($legacy->query('SELECT * FROM master_personnel ORDER BY id') as $row) {
            $existing=$db->table('resource_personnel')->where('code',$row['code'])->get()->getRowArray();
            $values=['role_name'=>$row['role_name'],'daily_rate'=>$row['daily_rate'],'standby_rate'=>$row['standby_rate'],'field_allowance'=>$row['field_allowance'],'accommodation_rate'=>$row['accommodation_rate'],'meal_rate'=>$row['meal_rate'],'transport_rate'=>$row['transport_rate'],'overtime_rate'=>$row['overtime_rate'],'active'=>$row['active'],'legacy_scheduler_id'=>$row['id'],'updated_at'=>$now];
            if($existing){$id=(int)$existing['id'];}
            else{$db->table('resource_personnel')->insert(['code'=>$row['code'],'created_at'=>$now]+$values);$id=(int)$db->insertID();}
            $resourceMaps['personnel'][$row['code']]=$id;
        }

        $equipmentCategoryMap = $this->equipmentCostCategoryMap($db);
        foreach ($legacy->query('SELECT * FROM master_equipment ORDER BY id') as $row) {
            $existing=$db->table('resource_equipment')->where('code',$row['code'])->get()->getRowArray();
            $values=['name'=>$row['name'],'category'=>$row['category'],'brand'=>$row['brand']??null,'model'=>$row['model']??null,'asset_tag'=>$row['asset_tag']??null,'specification'=>$row['specification']??null,'daily_rate'=>$row['daily_rate'],'standby_rate'=>$row['standby_rate'],'mobilization_cost'=>$row['mobilization_cost'],'demobilization_cost'=>$row['demobilization_cost'],'fuel_daily'=>$row['fuel_daily'],'operator_included'=>$row['operator_included'],'active'=>$row['active'],'legacy_scheduler_id'=>$row['id'],'updated_at'=>$now];
            if ($db->fieldExists('cost_category_id', 'resource_equipment')) {
                $values['cost_category_id'] = $equipmentCategoryMap[$row['category']] ?? null;
            }
            if($existing){$id=(int)$existing['id'];}
            else{$db->table('resource_equipment')->insert(['code'=>$row['code'],'created_at'=>$now]+$values);$id=(int)$db->insertID();}
            $resourceMaps['equipment'][$row['code']]=$id;
        }

        $projectMap=[];
        foreach ($legacy->query('SELECT * FROM projects ORDER BY id') as $row) {
            $payload=json_decode((string)$row['payload'],true)?:[];
            foreach(($payload['deployments']??[]) as &$deployment){
                $type=($deployment['type']??'personnel')==='equipment'?'equipment':'personnel'; $code=(string)($deployment['resourceCode']??'');
                if(isset($resourceMaps[$type][$code]))$deployment['resourceId']=$resourceMaps[$type][$code];
            }
            unset($deployment);
            $values=['name'=>$row['name'],'owner_id'=>(int)$owner['id'],'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'archived'=>$row['archived'],'legacy_scheduler_id'=>$row['id'],'updated_at'=>$row['updated_at']??$now];
            $existing=$db->table('scheduler_projects')->where('legacy_scheduler_id',$row['id'])->get()->getRowArray();
            if($existing){$id=(int)$existing['id'];}
            else{
                // A project with the same name may have been edited independently in MySQL.
                $sameName=$db->table('scheduler_projects')->where('name',$row['name'])->get()->getRowArray();
                if($sameName){$values['name']=$row['name'].' (arsip EserScheduler)';$values['archived']=1;}
                $db->table('scheduler_projects')->insert(['created_at'=>$row['created_at']??$now]+$values);
                $id=(int)$db->insertID();
            }
            $projectMap[(int)$row['id']]=$id;
        }

        $snapshotCount=0;
        foreach ($legacy->query('SELECT * FROM cost_snapshots ORDER BY id') as $row) {
            $projectId=$projectMap[(int)$row['project_id']]??null;
            if(!$projectId) continue;
            $existing=$db->table('scheduler_cost_snapshots')
                ->where('scheduler_project_id',$projectId)
                ->where('name',$row['name'])
                ->where('created_at',$row['created_at'])
                ->get()->getRowArray();
            if($existing) continue;
            $db->table('scheduler_cost_snapshots')->insert([
                'scheduler_project_id'=>$projectId,
                'name'=>$row['name'],
                'payload'=>$row['payload'],
                'created_by'=>(int)$owner['id'],
                'created_at'=>$row['created_at'],
                'updated_at'=>$row['created_at'],
            ]);
            $snapshotCount++;
        }

        $auditCount=0;
        foreach ($legacy->query('SELECT * FROM audit_log ORDER BY id') as $row) {
            if($db->table('legacy_scheduler_audit_logs')->where('legacy_id',$row['id'])->countAllResults()>0) continue;
            $db->table('legacy_scheduler_audit_logs')->insert([
                'legacy_id'=>$row['id'],
                'source_user_id'=>$row['user_id'],
                'action'=>$row['action'],
                'entity_type'=>$row['entity_type'],
                'entity_id'=>$row['entity_id'],
                'details'=>$row['details'],
                'created_at'=>$row['created_at'],
            ]);
            $auditCount++;
        }

        $db->transComplete();
        if(!$db->transStatus()){
            CLI::error('Import gagal dan transaksi dibatalkan.'); return;
        }
        CLI::write(sprintf('Import selesai: %d WBS, %d personel, %d equipment, %d proyek dipetakan; %d snapshot dan %d audit lama baru. Data MySQL yang sudah ada tidak ditimpa.',count($wbsMap),count($resourceMaps['personnel']),count($resourceMaps['equipment']),count($projectMap),$snapshotCount,$auditCount),'green');
    }

    private function equipmentCostCategoryMap($db): array
    {
        $ids = [];
        foreach ($db->table('categories')->select('id,name')->get()->getResultArray() as $category) {
            $ids[$category['name']] = (int) $category['id'];
        }

        $groups = [
            'Aerial Photogrammetry / LiDAR Survey' => ['Aerial Survey'],
            'Topography Survey' => ['Geodetic', 'GPS', 'Topography', 'Waterpass'],
            'Hydrography Survey' => ['Hydrography', 'Multibeam', 'Single Beam'],
            'Oceanography Survey' => ['AWLR', 'Oceanography'],
            'Geophysical Survey' => ['Magnetometer', 'SBP', 'Side Scan Sonar'],
            'Equipment' => ['Marine Logistics'],
        ];

        $result = [];
        foreach ($groups as $costCategory => $equipmentCategories) {
            foreach ($equipmentCategories as $equipmentCategory) {
                $result[$equipmentCategory] = $ids[$costCategory] ?? null;
            }
        }
        return $result;
    }
}
