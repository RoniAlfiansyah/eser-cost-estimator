<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use Throwable;

class SchedulerController extends BaseController
{
    private $db;

    public function index()
    {
        helper(['auth', 'audit']);
        $this->db = db_connect();
        $action = (string) ($this->request->getGet('action') ?? 'session');

        try {
            return match ($action) {
                'session' => $this->sessionInfo(),
                'projects' => $this->projects(),
                'project' => $this->project(),
                'master' => $this->master(),
                'master_resources' => $this->masterResources(),
                'master_wbs' => $this->masterWbs(),
                'master_activity' => $this->masterActivity(),
                'master_personnel' => $this->masterPersonnel(),
                'master_equipment' => $this->masterEquipment(),
                'sync_resources' => $this->syncLegacyResources(),
                'cost_snapshots' => $this->costSnapshots(),
                'basic_cost_create' => $this->createBasicCost(),
                'users' => $this->users(),
                'audit' => $this->audit(),
                'logout' => $this->logout(),
                default => $this->fail('Aksi Scheduler tidak ditemukan.', 404),
            };
        } catch (Throwable $error) {
            log_message('error', 'Scheduler API: {message}', ['message' => $error->getMessage()]);
            return $this->fail(ENVIRONMENT === 'development' ? $error->getMessage() : 'Gagal memproses data Scheduler.', 500);
        }
    }

    private function sessionInfo()
    {
        return $this->ok([
            'user' => [
                'id' => (int) current_user('id'),
                'username' => (string) current_user('email'),
                'display_name' => (string) current_user('name'),
                'role' => (string) current_user('role'),
                'active' => true,
                'must_change_password' => 0,
            ],
            'csrf' => '',
        ]);
    }

    private function projects()
    {
        $builder = $this->db->table('scheduler_projects p')
            ->select('p.*, u.name AS owner_name')
            ->join('users u', 'u.id = p.owner_id', 'left')
            ->orderBy('p.archived', 'ASC')->orderBy('p.updated_at', 'DESC');
        if (! $this->canSeeAllProjects()) {
            $builder->where('p.owner_id', (int) current_user('id'));
        }
        $projects = array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['owner_id'] = $row['owner_id'] !== null ? (int) $row['owner_id'] : null;
            $row['archived'] = (bool) $row['archived'];
            $row['data'] = json_decode((string) $row['payload'], true) ?: [];
            unset($row['payload']);
            return $row;
        }, $builder->get()->getResultArray());
        return $this->ok(['projects' => $projects]);
    }

    private function project()
    {
        $method = strtoupper($this->request->getMethod());
        if ($method === 'DELETE') {
            $id = (int) $this->request->getGet('id');
            $project = $this->findAccessibleProject($id);
            if (! $project) return $this->fail('Proyek tidak ditemukan.', 404);
            $this->db->table('scheduler_projects')->where('id', $id)->delete();
            $this->writeAudit('delete', 'scheduler_project', $id, 'Menghapus proyek schedule.');
            return $this->ok();
        }

        if ($method !== 'POST') return $this->fail('Metode tidak didukung.', 405);
        $input = $this->json();
        $name = trim((string) ($input['name'] ?? ''));
        $data = $input['data'] ?? null;
        if ($name === '' || mb_strlen($name) > 180 || ! is_array($data)) {
            return $this->fail('Nama atau data proyek tidak valid.', 422);
        }
        $id = (int) ($input['id'] ?? 0);
        $values = [
            'name' => $name,
            'payload' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'archived' => ! empty($input['archived']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            if (! $this->findAccessibleProject($id)) return $this->fail('Proyek tidak ditemukan.', 404);
            $this->db->table('scheduler_projects')->where('id', $id)->update($values);
            $action = 'update';
        } else {
            $values['owner_id'] = (int) current_user('id');
            $values['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('scheduler_projects')->insert($values);
            $id = (int) $this->db->insertID();
            $action = 'create';
        }
        $this->writeAudit($action, 'scheduler_project', $id, 'Menyimpan proyek schedule.');
        return $this->ok(['id' => $id]);
    }

    private function master()
    {
        $wbs = $this->db->table('scheduler_master_wbs')->select('id,code,name,prefix')->orderBy('code')->get()->getResultArray();
        $activities = $this->db->table('scheduler_master_activities a')
            ->select('a.id,a.activity_code,a.wbs_id,a.name,a.default_duration,a.default_pic,w.code AS wbs_code,w.name AS wbs_name')
            ->join('scheduler_master_wbs w', 'w.id=a.wbs_id')->orderBy('w.code')->orderBy('a.activity_code')->get()->getResultArray();
        foreach ($wbs as &$row) $row['id'] = (int) $row['id'];
        foreach ($activities as &$row) {
            $row['id'] = (int) $row['id'];
            $row['wbs_id'] = (int) $row['wbs_id'];
            $row['default_duration'] = (float) $row['default_duration'];
        }
        return $this->ok(['wbs' => $wbs, 'activities' => $activities]);
    }

    private function masterResources()
    {
        $mappedPersonnel = array_column($this->db->table('resource_cost_item_mappings')->select('resource_code')->where('resource_type', 'personnel')->groupBy('resource_code')->get()->getResultArray(), 'resource_code');
        $mappedEquipment = array_column($this->db->table('resource_cost_item_mappings')->select('resource_code')->where('resource_type', 'equipment')->groupBy('resource_code')->get()->getResultArray(), 'resource_code');
        $personnelBuilder = $this->db->table('resource_personnel')->orderBy('role_name');
        if ($mappedPersonnel) $personnelBuilder->whereNotIn('code', $mappedPersonnel);
        $personnel = $personnelBuilder->get()->getResultArray();
        $equipmentBuilder = $this->db->table('resource_equipment e')
            ->select('e.*, c.name AS cost_category_name')
            ->join('categories c', 'c.id=e.cost_category_id', 'left')
            ->orderBy('e.category')->orderBy('e.brand')->orderBy('e.model');
        if ($mappedEquipment) $equipmentBuilder->whereNotIn('e.code', $mappedEquipment);
        $equipment = $equipmentBuilder->get()->getResultArray();
        $costCategories = $this->db->table('categories')->select('id,name')->where('is_active', 1)->orderBy('name')->get()->getResultArray();
        $canViewRates = in_array(current_user('role'), ['admin', 'manager'], true);
        foreach ($personnel as &$row) {
            $row['id'] = (int) $row['id']; $row['active'] = (bool) $row['active'];
            if (! $canViewRates) foreach (['daily_rate','standby_rate','field_allowance','accommodation_rate','meal_rate','transport_rate','overtime_rate'] as $field) unset($row[$field]);
        }
        foreach ($equipment as &$row) {
            $row['id'] = (int) $row['id']; $row['cost_category_id'] = $row['cost_category_id'] === null ? null : (int) $row['cost_category_id'];
            $row['active'] = (bool) $row['active']; $row['operator_included'] = (bool) $row['operator_included'];
            if (! $canViewRates) foreach (['daily_rate','standby_rate','mobilization_cost','demobilization_cost','fuel_daily'] as $field) unset($row[$field]);
        }
        foreach ($costCategories as &$row) $row['id'] = (int) $row['id'];
        $costProfiles = $this->costEstimatorResourceProfiles($canViewRates);
        return $this->ok(['personnel' => $personnel, 'equipment' => $equipment, 'costProfiles' => $costProfiles, 'costCategories' => $costCategories, 'canViewRates' => $canViewRates]);
    }

    private function costEstimatorResourceProfiles(bool $canViewRates): array
    {
        $rows = $this->db->table('cost_items i')
            ->select('i.id,i.item_name,i.default_price,i.subcategory_id,c.name AS category_name,s.name AS subcategory_name,m.resource_code,m.resource_type')
            ->join('categories c', 'c.id=i.category_id')
            ->join('subcategories s', 's.id=i.subcategory_id')
            ->join('resource_cost_item_mappings m', 'm.cost_item_id=i.id', 'left')
            ->where('i.is_active', 1)->where('c.is_active', 1)->where('s.is_active', 1)
            ->orderBy('c.name')->orderBy('s.name')->orderBy('i.item_name')->get()->getResultArray();

        $rateKeys = [
            'working' => 'daily_rate', 'standby' => 'standby_rate', 'travel' => 'travel_rate', 'field allowance' => 'field_allowance',
            'akomodasi' => 'accommodation_rate', 'konsumsi' => 'meal_rate', 'transport lokal' => 'transport_rate',
            'overtime' => 'overtime_rate', 'mobilisasi' => 'mobilization_cost',
            'demobilisasi' => 'demobilization_cost', 'bbm' => 'fuel_daily',
        ];
        $componentKeys = [
            'working' => 'working', 'standby' => 'standby', 'travel' => 'travel',
            'field allowance' => 'field_allowance', 'akomodasi' => 'accommodation', 'konsumsi' => 'meal',
            'transport lokal' => 'local_transport', 'overtime' => 'overtime', 'mobilisasi' => 'mobilization',
            'demobilisasi' => 'demobilization', 'bbm' => 'fuel',
        ];
        $profiles = [];
        foreach ($rows as $row) {
            $subcategoryId = (int) $row['subcategory_id'];
            if (! isset($profiles[$subcategoryId])) {
                $type = ($row['resource_type'] ?? '') === 'personnel' || $row['category_name'] === 'Personnel' ? 'personnel' : 'equipment';
                $profiles[$subcategoryId] = [
                    'id' => -100000 - $subcategoryId,
                    'source' => 'cost_estimator',
                    'type' => $type,
                    'code' => $row['resource_code'] ?: 'CE-' . $subcategoryId,
                    'name' => $row['subcategory_name'],
                    'role_name' => $row['subcategory_name'],
                    'category_name' => $row['category_name'],
                    'active' => true,
                    'cost_item_ids' => [],
                ];
                if ($canViewRates) {
                    foreach (['daily_rate','standby_rate','travel_rate','field_allowance','accommodation_rate','meal_rate','transport_rate','overtime_rate','mobilization_cost','demobilization_cost','fuel_daily'] as $key) {
                        $profiles[$subcategoryId][$key] = 0.0;
                    }
                }
            }
            $profile =& $profiles[$subcategoryId];
            $itemName = trim((string) $row['item_name']);
            $suffix = strtolower(trim(str_contains($itemName, ' - ') ? substr($itemName, strrpos($itemName, ' - ') + 3) : 'working'));
            $component = $componentKeys[$suffix] ?? 'working';
            $profile['cost_item_ids'][$component] = (int) $row['id'];
            if ($component === 'working' && ! isset($profile['cost_item_ids']['travel'])) {
                $profile['cost_item_ids']['travel'] = (int) $row['id'];
                if ($canViewRates) $profile['travel_rate'] = (float) $row['default_price'];
            }
            if ($canViewRates) $profile[$rateKeys[$suffix] ?? 'daily_rate'] = (float) $row['default_price'];
            unset($profile);
        }
        return array_values($profiles);
    }

    private function masterWbs()
    {
        if (! $this->isAdmin()) return $this->fail('Hanya admin yang dapat mengelola master WBS.', 403);
        $method = strtoupper($this->request->getMethod());
        if ($method === 'DELETE') {
            $id = (int) $this->request->getGet('id');
            if ($this->db->table('scheduler_master_activities')->where('wbs_id', $id)->countAllResults() > 0) return $this->fail('WBS masih digunakan oleh master aktivitas.', 409);
            $this->db->table('scheduler_master_wbs')->where('id', $id)->delete();
            return $this->ok();
        }
        $input = $this->json(); $id = (int) ($input['id'] ?? 0);
        $values = ['code' => strtoupper(trim((string) ($input['code'] ?? ''))), 'name' => trim((string) ($input['name'] ?? '')), 'prefix' => strtoupper(trim((string) ($input['prefix'] ?? ''))), 'active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
        if ($values['code'] === '' || $values['name'] === '') return $this->fail('Data WBS tidak valid.', 422);
        if ($id) $this->db->table('scheduler_master_wbs')->where('id', $id)->update($values);
        else { $values['created_at'] = date('Y-m-d H:i:s'); $this->db->table('scheduler_master_wbs')->insert($values); $id = (int) $this->db->insertID(); }
        return $this->ok(['id' => $id]);
    }

    private function masterActivity()
    {
        if (! $this->isAdmin()) return $this->fail('Hanya admin yang dapat mengelola master aktivitas.', 403);
        if (strtoupper($this->request->getMethod()) === 'DELETE') {
            $this->db->table('scheduler_master_activities')->where('id', (int) $this->request->getGet('id'))->delete(); return $this->ok();
        }
        $input = $this->json(); $id = (int) ($input['id'] ?? 0);
        $values = ['activity_code' => strtoupper(trim((string) ($input['activityCode'] ?? ''))), 'wbs_id' => (int) ($input['wbsId'] ?? 0), 'name' => trim((string) ($input['name'] ?? '')), 'default_duration' => max(1, (float) ($input['defaultDuration'] ?? 1)), 'default_pic' => trim((string) ($input['defaultPic'] ?? '')), 'active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
        if ($values['activity_code'] === '' || $values['wbs_id'] < 1 || $values['name'] === '') return $this->fail('Data master aktivitas tidak valid.', 422);
        if ($id) $this->db->table('scheduler_master_activities')->where('id', $id)->update($values);
        else { $values['created_at'] = date('Y-m-d H:i:s'); $this->db->table('scheduler_master_activities')->insert($values); $id = (int) $this->db->insertID(); }
        return $this->ok(['id' => $id]);
    }

    private function masterPersonnel()
    {
        if (! $this->isAdmin()) return $this->fail('Hanya admin yang dapat mengelola tarif personel.', 403);
        if (strtoupper($this->request->getMethod()) === 'DELETE') {
            $this->db->table('resource_personnel')->where('id', (int) $this->request->getGet('id'))->delete(); return $this->ok();
        }
        $input = $this->json(); $id = (int) ($input['id'] ?? 0);
        $values = [
            'code' => strtoupper(trim((string) ($input['code'] ?? ''))), 'role_name' => trim((string) ($input['roleName'] ?? '')),
            'daily_rate' => $this->amount($input, 'dailyRate'), 'standby_rate' => $this->amount($input, 'standbyRate'),
            'field_allowance' => $this->amount($input, 'fieldAllowance'), 'accommodation_rate' => $this->amount($input, 'accommodationRate'),
            'meal_rate' => $this->amount($input, 'mealRate'), 'transport_rate' => $this->amount($input, 'transportRate'),
            'overtime_rate' => $this->amount($input, 'overtimeRate'), 'active' => ! empty($input['active']) ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($values['code'] === '' || $values['role_name'] === '') return $this->fail('Data personel tidak valid.', 422);
        if ($id) $this->db->table('resource_personnel')->where('id', $id)->update($values);
        else { $values['created_at'] = date('Y-m-d H:i:s'); $this->db->table('resource_personnel')->insert($values); $id = (int) $this->db->insertID(); }
        $resource = $this->db->table('resource_personnel')->where('id', $id)->get()->getRowArray();
        $this->syncResourceToCostEstimator('personnel', $resource, true);
        return $this->ok(['id' => $id, 'syncedToCostEstimator' => true]);
    }

    private function masterEquipment()
    {
        if (! $this->isAdmin()) return $this->fail('Hanya admin yang dapat mengelola tarif peralatan.', 403);
        if (strtoupper($this->request->getMethod()) === 'DELETE') {
            $existing = $this->db->table('resource_equipment')->where('id', (int) $this->request->getGet('id'))->get()->getRowArray();
            if ($existing) $this->db->table('resource_cost_item_mappings')->where(['resource_type' => 'equipment', 'resource_code' => $existing['code']])->delete();
            $this->db->table('resource_equipment')->where('id', (int) $this->request->getGet('id'))->delete(); return $this->ok();
        }
        $input = $this->json(); $id = (int) ($input['id'] ?? 0);
        $costCategoryId = (int) ($input['costCategoryId'] ?? 0);
        $costCategory = $this->db->table('categories')->select('id')->where(['id' => $costCategoryId, 'is_active' => 1])->get()->getRowArray();
        if (! $costCategory) return $this->fail('Pilih kategori Basic Cost yang aktif.', 422);
        $existing = $id ? $this->db->table('resource_equipment')->where('id', $id)->get()->getRowArray() : null;
        $values = [
            'code' => strtoupper(trim((string) ($input['code'] ?? ''))), 'name' => trim((string) ($input['name'] ?? '')),
            'category' => trim((string) ($input['category'] ?? 'Survey Equipment')), 'brand' => trim((string) ($input['brand'] ?? '')),
            'cost_category_id' => $costCategoryId,
            'model' => trim((string) ($input['model'] ?? '')), 'asset_tag' => trim((string) ($input['assetTag'] ?? '')),
            'specification' => trim((string) ($input['specification'] ?? '')), 'daily_rate' => $this->amount($input, 'dailyRate'),
            'standby_rate' => $this->amount($input, 'standbyRate'), 'mobilization_cost' => $this->amount($input, 'mobilizationCost'),
            'demobilization_cost' => $this->amount($input, 'demobilizationCost'), 'fuel_daily' => $this->amount($input, 'fuelDaily'),
            'operator_included' => ! empty($input['operatorIncluded']) ? 1 : 0, 'active' => ! empty($input['active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($values['code'] === '' || $values['name'] === '') return $this->fail('Data peralatan tidak valid.', 422);
        if ($id) $this->db->table('resource_equipment')->where('id', $id)->update($values);
        else { $values['created_at'] = date('Y-m-d H:i:s'); $this->db->table('resource_equipment')->insert($values); $id = (int) $this->db->insertID(); }
        $mappingCodes = array_values(array_unique(array_filter([(string) ($existing['code'] ?? ''), $values['code']])));
        if ($mappingCodes && $existing && $existing['code'] !== $values['code']) $this->db->table('resource_cost_item_mappings')->where('resource_type', 'equipment')->where('resource_code', $existing['code'])->update(['resource_code'=>$values['code'],'updated_at'=>date('Y-m-d H:i:s')]);
        $resource = $this->db->table('resource_equipment')->where('id', $id)->get()->getRowArray();
        $this->syncResourceToCostEstimator('equipment', $resource, true);
        return $this->ok(['id' => $id, 'syncedToCostEstimator' => true]);
    }

    private function syncLegacyResources()
    {
        if (! $this->isAdmin()) return $this->fail('Hanya admin yang dapat menyatukan master resource.', 403);
        if (strtoupper($this->request->getMethod()) !== 'POST') return $this->fail('Metode tidak diizinkan.', 405);
        $personnel = $this->db->table('resource_personnel')->get()->getResultArray();
        $equipment = $this->db->table('resource_equipment')->get()->getResultArray();
        $this->db->transStart();
        foreach ($personnel as $resource) $this->syncResourceToCostEstimator('personnel', $resource, false);
        foreach ($equipment as $resource) $this->syncResourceToCostEstimator('equipment', $resource, false);
        $this->db->transComplete();
        if (! $this->db->transStatus()) return $this->fail('Migrasi master resource gagal.', 500);
        $this->writeAudit('unify_resource_master', 'cost_items', 0, 'Menyatukan master resource Scheduler ke Cost Estimator.');
        return $this->ok(['personnel'=>count($personnel),'equipment'=>count($equipment)]);
    }

    private function syncResourceToCostEstimator(string $type, array $resource, bool $overwriteExisting): void
    {
        $now = date('Y-m-d H:i:s');
        $category = null;
        if ($type === 'equipment' && ! empty($resource['cost_category_id'])) $category = $this->db->table('categories')->where('id', (int)$resource['cost_category_id'])->get()->getRowArray();
        $categoryName = $type === 'personnel' ? 'Personnel' : 'Equipment';
        if (! $category) $category = $this->db->table('categories')->where('name', $categoryName)->get()->getRowArray();
        if (! $category) {
            $this->db->table('categories')->insert(['name'=>$categoryName,'is_active'=>1,'created_at'=>$now,'updated_at'=>$now]);
            $category = ['id'=>(int)$this->db->insertID(),'name'=>$categoryName];
        }
        $name = trim((string)($type === 'personnel' ? $resource['role_name'] : $resource['name']));
        $code = trim((string)$resource['code']);
        $dayUnit = $this->db->table('units')->where('symbol', 'day')->get()->getRowArray();
        $hourUnit = $this->db->table('units')->where('symbol', 'hour')->get()->getRowArray();
        $unitUnit = $this->db->table('units')->where('symbol', 'unit')->get()->getRowArray();
        $subcategory = $this->db->table('subcategories')->where(['category_id'=>(int)$category['id'],'name'=>$name])->get()->getRowArray();
        if (! $subcategory) {
            $this->db->table('subcategories')->insert(['category_id'=>(int)$category['id'],'unit_id'=>(int)$dayUnit['id'],'name'=>$name,'default_duration'=>1,'default_price'=>(float)($resource['daily_rate']??0),'description'=>'Resource terintegrasi dari Scheduler: '.$code,'is_active'=>!empty($resource['active'])?1:0,'created_at'=>$now,'updated_at'=>$now]);
            $subcategory = ['id'=>(int)$this->db->insertID()];
        } elseif ($overwriteExisting) {
            $this->db->table('subcategories')->where('id', (int)$subcategory['id'])->update(['default_price'=>(float)($resource['daily_rate']??0),'is_active'=>!empty($resource['active'])?1:0,'updated_at'=>$now]);
        }
        $components = $type === 'personnel'
            ? [['working','Working','daily_rate',$dayUnit],['standby','Standby','standby_rate',$dayUnit],['field_allowance','Field allowance','field_allowance',$dayUnit],['accommodation','Akomodasi','accommodation_rate',$dayUnit],['meal','Konsumsi','meal_rate',$dayUnit],['local_transport','Transport lokal','transport_rate',$dayUnit],['overtime','Overtime','overtime_rate',$hourUnit]]
            : [['working','Working','daily_rate',$dayUnit],['standby','Standby','standby_rate',$dayUnit],['mobilization','Mobilisasi','mobilization_cost',$unitUnit],['demobilization','Demobilisasi','demobilization_cost',$unitUnit],['fuel','BBM','fuel_daily',$dayUnit]];
        foreach ($components as [$component,$label,$field,$unit]) {
            $rate = (float)($resource[$field]??0);
            $mapping = $this->db->table('resource_cost_item_mappings')->where(['resource_type'=>$type,'resource_code'=>$code,'cost_component'=>$component])->get()->getRowArray();
            $item = $mapping ? $this->db->table('cost_items')->where('id', (int)$mapping['cost_item_id'])->get()->getRowArray() : null;
            if (! $item) {
                $itemBuilder = $this->db->table('cost_items')->where(['subcategory_id'=>(int)$subcategory['id']]);
                if ($component === 'working') $itemBuilder->groupStart()->where('item_name', $name)->orWhere('item_name', $name.' - Working')->groupEnd();
                else $itemBuilder->where('item_name', $name.' - '.$label);
                $item = $itemBuilder->get()->getRowArray();
            }
            if (! $item && $component !== 'working' && $rate <= 0) continue;
            if (! $item) {
                $this->db->table('cost_items')->insert(['category_id'=>(int)$category['id'],'subcategory_id'=>(int)$subcategory['id'],'unit_id'=>(int)$unit['id'],'item_name'=>$name.' - '.$label,'default_duration'=>1,'default_price'=>$rate,'description'=>'Resource terintegrasi dari Scheduler: '.$code,'is_active'=>!empty($resource['active'])?1:0,'created_at'=>$now,'updated_at'=>$now]);
                $item = ['id'=>(int)$this->db->insertID()];
            } elseif ($overwriteExisting) {
                $this->db->table('cost_items')->where('id', (int)$item['id'])->update(['unit_id'=>(int)$unit['id'],'default_price'=>$rate,'is_active'=>!empty($resource['active'])?1:0,'updated_at'=>$now]);
            }
            if ($mapping) $this->db->table('resource_cost_item_mappings')->where('id', (int)$mapping['id'])->update(['cost_item_id'=>(int)$item['id'],'updated_at'=>$now]);
            else $this->db->table('resource_cost_item_mappings')->insert(['resource_type'=>$type,'resource_code'=>$code,'cost_component'=>$component,'cost_item_id'=>(int)$item['id'],'created_at'=>$now,'updated_at'=>$now]);
        }
    }

    private function costSnapshots()
    {
        if (! in_array(current_user('role'), ['admin', 'manager'], true)) return $this->fail('Anda tidak dapat melihat basic cost.', 403);
        $projectId = (int) $this->request->getGet('projectId');
        if (! $this->findAccessibleProject($projectId)) return $this->fail('Proyek tidak ditemukan.', 404);
        $method = strtoupper($this->request->getMethod());
        if ($method === 'GET') {
            $rows = $this->db->table('scheduler_cost_snapshots s')->select('s.id,s.name,s.payload,s.created_at,u.name AS created_by_name')->join('users u','u.id=s.created_by','left')->where('s.scheduler_project_id',$projectId)->orderBy('s.id','DESC')->get()->getResultArray();
            foreach ($rows as &$row) { $row['id']=(int)$row['id']; $row['data']=json_decode((string)$row['payload'],true)?:[]; unset($row['payload']); }
            return $this->ok(['snapshots'=>$rows]);
        }
        if ($method === 'DELETE') { $this->db->table('scheduler_cost_snapshots')->where(['id'=>(int)$this->request->getGet('id'),'scheduler_project_id'=>$projectId])->delete(); return $this->ok(); }
        $input=$this->json(); $data=$input['data']??null; if(!is_array($data)) return $this->fail('Snapshot tidak valid.',422);
        $this->db->table('scheduler_cost_snapshots')->insert(['scheduler_project_id'=>$projectId,'name'=>trim((string)($input['name']??'Basic Cost')),'payload'=>json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'created_by'=>(int)current_user('id'),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        return $this->ok(['id'=>(int)$this->db->insertID()]);
    }

    private function createBasicCost()
    {
        if (! in_array(current_user('role'), ['admin', 'manager', 'staff'], true)) return $this->fail('Anda tidak dapat membuat Basic Cost.',403);
        $input=$this->json(); $projectId=(int)($input['projectId']??0); $project=$this->findAccessibleProject($projectId);
        if(!$project) return $this->fail('Proyek tidak ditemukan.',404);
        $payload=json_decode((string)$project['payload'],true)?:[]; $deployments=$payload['deployments']??[];
        if(!is_array($deployments)||$deployments===[]) return $this->fail('Belum ada deployment untuk dikirim ke Basic Cost.',422);
        $this->db->transStart();
        $items=[]; $categories=[]; $sort=1; $grand=0.0;
        foreach($deployments as $deployment){
            foreach($this->deploymentComponents($deployment) as $component){
                $catalogItemId = (int) (($deployment['costItemIds'][$component['key']] ?? 0));
                $master = $catalogItemId > 0 ? $this->costItemDetails($catalogItemId) : null;
                if (! $master) $master=$this->resolveCostItem((string)($deployment['type']??'personnel'),(string)($deployment['resourceCode']??''),(string)($deployment['resourceName']??'Resource'),$component);
                $total=round($component['quantity']*$component['duration']*$component['rate'],2); $grand+=$total; $categories[$master['category_id']]=$master['category_name'];
                $items[]=['category_id'=>$master['category_id'],'category_name'=>$master['category_name'],'subcategory_id'=>$master['subcategory_id'],'subcategory_name'=>$master['subcategory_name'],'cost_item_id'=>$master['cost_item_id'],'item_name'=>$master['item_name'],'unit_id'=>$master['unit_id'],'unit_name'=>$master['unit_name'],'unit_symbol'=>$master['unit_symbol'],'quantity'=>$component['quantity'],'duration'=>$component['duration'],'unit_price'=>$component['rate'],'total_price'=>$total,'sort_order'=>$sort++,'source_type'=>'schedule','source_key'=>(string)($deployment['id']??'').':'.$component['key'],'source_payload'=>json_encode(['deployment'=>$deployment,'component'=>$component],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
            }
        }
        $this->db->table('basic_costs')->insert(['scheduler_project_id'=>$projectId,'client_name'=>trim((string)($project['client_name']??''))?:'TBA','project_name'=>$project['name'],'location'=>(string)($project['location']??''),'basic_cost_date'=>date('Y-m-d'),'notes'=>'Dibuat dari Resource Deployment Schedule. Item tetap dapat diedit dan ditambah secara manual.','grand_total'=>$grand,'status'=>'draft','created_by'=>(int)current_user('id'),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        $basicCostId=(int)$this->db->insertID();
        foreach($categories as $categoryId=>$name) $this->db->table('basic_cost_work_types')->insert(['basic_cost_id'=>$basicCostId,'category_id'=>$categoryId,'category_name'=>$name,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        foreach($items as &$item){$item['basic_cost_id']=$basicCostId;} unset($item); $this->db->table('basic_cost_items')->insertBatch($items);
        $this->db->table('basic_cost_approval_histories')->insert(['basic_cost_id'=>$basicCostId,'action'=>'created','status_from'=>null,'status_to'=>'draft','note'=>'Draft dibuat dari EserScheduler.','actor_id'=>(int)current_user('id'),'actor_name'=>(string)current_user('name'),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        $this->db->transComplete(); if(!$this->db->transStatus()) return $this->fail('Gagal membuat Basic Cost.',500);
        $this->writeAudit('create_from_schedule','basic_cost',$basicCostId,'Membuat draft Basic Cost dari resource schedule.');
        return $this->ok(['basicCostId'=>$basicCostId,'itemCount'=>count($items),'grandTotal'=>$grand]);
    }

    private function deploymentComponents(array $deployment): array
    {
        $type=($deployment['type']??'personnel')==='equipment'?'equipment':'personnel'; $rates=is_array($deployment['rateOverride']??null)?$deployment['rateOverride']:[];
        $resource=$this->db->table($type==='equipment'?'resource_equipment':'resource_personnel')->where('id',(int)($deployment['resourceId']??0))->get()->getRowArray()?:[]; $rates=array_merge($resource,$rates);
        if (($deployment['resourceSource'] ?? '') === 'cost_estimator' && is_array($deployment['costItemIds'] ?? null)) {
            $componentRates = ['working'=>'daily_rate','standby'=>'standby_rate','travel'=>'travel_rate','field_allowance'=>'field_allowance','accommodation'=>'accommodation_rate','meal'=>'meal_rate','local_transport'=>'transport_rate','overtime'=>'overtime_rate','mobilization'=>'mobilization_cost','demobilization'=>'demobilization_cost','fuel'=>'fuel_daily'];
            foreach ($deployment['costItemIds'] as $component => $costItemId) {
                if (! isset($componentRates[$component])) continue;
                $item = $this->db->table('cost_items')->select('default_price')->where(['id'=>(int)$costItemId,'is_active'=>1])->get()->getRowArray();
                if ($item && ! array_key_exists($componentRates[$component], $rates)) $rates[$componentRates[$component]] = (float) $item['default_price'];
            }
        }
        $qty=max(0.01,(float)($deployment['quantity']??1)); $onsite=max(1,(float)($deployment['endDay']??1)-(float)($deployment['startDay']??1)+1); $standby=min($onsite,max(0,(float)($deployment['standbyDays']??0))); $travel=min($onsite-$standby,max(0,(float)($deployment['travelDays']??0))); $working=max(0,$onsite-$standby-$travel);
        $components=[]; $add=static function(string $key,string $label,float $quantity,float $duration,float $rate,string $unit='day',bool $always=false) use (&$components):void { if($duration<=0||(!$always&&$rate<=0))return; $components[]=['key'=>$key,'label'=>$label,'quantity'=>$quantity,'duration'=>$duration,'rate'=>max(0,$rate),'unit'=>$unit]; };
        $add('working','Working',$qty,$working,(float)($rates['daily_rate']??0),'day',true); $add('standby','Standby',$qty,$standby,(float)($rates['standby_rate']??0)); $add('travel','Travel',$qty,$travel,(float)($rates['travel_rate']??$rates['daily_rate']??0));
        if($type==='personnel'){
            $add('field_allowance','Field allowance',$qty,$onsite,(float)($rates['field_allowance']??0)); $add('accommodation','Akomodasi',$qty,$onsite,(float)($rates['accommodation_rate']??0)); $add('meal','Konsumsi',$qty,$onsite,(float)($rates['meal_rate']??0)); $add('local_transport','Transport lokal',$qty,$onsite,(float)($rates['transport_rate']??0)); $add('overtime','Overtime',$qty,max(0,(float)($deployment['overtimeHours']??0)),(float)($rates['overtime_rate']??0),'hour');
        } else {
            $add('mobilization','Mobilisasi',$qty,1,(float)($rates['mobilization_cost']??0),'unit'); $add('demobilization','Demobilisasi',$qty,1,(float)($rates['demobilization_cost']??0),'unit'); $add('fuel','BBM',$qty,$working,(float)($rates['fuel_daily']??0));
        }
        return $components;
    }

    private function resolveCostItem(string $type,string $code,string $name,array $component): array
    {
        $category = null;
        if ($type === 'equipment') {
            $resource = $this->db->table('resource_equipment')->select('cost_category_id')->where('code', $code)->get()->getRowArray();
            if (! empty($resource['cost_category_id'])) {
                $category = $this->db->table('categories')->where('id', (int) $resource['cost_category_id'])->get()->getRowArray();
            }
        }
        $categoryName = $type === 'personnel' ? 'Personnel' : 'Equipment';
        if (! $category) $category = $this->db->table('categories')->where('name', $categoryName)->get()->getRowArray();
        if(!$category){$this->db->table('categories')->insert(['name'=>$categoryName,'is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);$category=['id'=>(int)$this->db->insertID(),'name'=>$categoryName];}

        $mapping=$this->db->table('resource_cost_item_mappings')->where(['resource_type'=>$type,'resource_code'=>$code,'cost_component'=>$component['key']])->get()->getRowArray();
        if($mapping){
            $found=$this->costItemDetails((int)$mapping['cost_item_id']);
            if($found && (int)$found['category_id'] === (int)$category['id']) return $found;
            $this->db->table('resource_cost_item_mappings')->where('id', (int)$mapping['id'])->delete();
        }
        $unitSymbol=$component['unit']==='hour'?'hour':($component['unit']==='unit'?'unit':'day'); $unit=$this->db->table('units')->where('symbol',$unitSymbol)->get()->getRowArray();
        $subcategoryName=$name; $subcategory=$this->db->table('subcategories')->where(['category_id'=>$category['id'],'name'=>$subcategoryName])->get()->getRowArray();
        if(!$subcategory){$this->db->table('subcategories')->insert(['category_id'=>$category['id'],'unit_id'=>$unit['id'],'name'=>$subcategoryName,'default_duration'=>1,'default_price'=>0,'description'=>'Resource dari Schedule','is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);$subcategory=['id'=>(int)$this->db->insertID(),'name'=>$subcategoryName];}
        $itemName=$name.' - '.$component['label']; $costItem=$this->db->table('cost_items')->where(['category_id'=>$category['id'],'subcategory_id'=>$subcategory['id'],'item_name'=>$itemName])->get()->getRowArray();
        if(!$costItem){$this->db->table('cost_items')->insert(['category_id'=>$category['id'],'subcategory_id'=>$subcategory['id'],'unit_id'=>$unit['id'],'item_name'=>$itemName,'default_duration'=>1,'default_price'=>$component['rate'],'description'=>'Dibuat otomatis dari resource '.$code,'is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);$costItemId=(int)$this->db->insertID();}else{$costItemId=(int)$costItem['id'];}
        $this->db->table('resource_cost_item_mappings')->ignore(true)->insert(['resource_type'=>$type,'resource_code'=>$code,'cost_component'=>$component['key'],'cost_item_id'=>$costItemId,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        return $this->costItemDetails($costItemId);
    }

    private function costItemDetails(int $id): ?array
    {
        return $this->db->table('cost_items i')->select('i.id AS cost_item_id,i.item_name,i.unit_id,c.id AS category_id,c.name AS category_name,s.id AS subcategory_id,s.name AS subcategory_name,u.name AS unit_name,u.symbol AS unit_symbol')->join('categories c','c.id=i.category_id')->join('subcategories s','s.id=i.subcategory_id')->join('units u','u.id=i.unit_id')->where('i.id',$id)->get()->getRowArray() ?: null;
    }

    private function users(){ if(!$this->isAdmin())return $this->fail('Hanya admin.',403); $rows=$this->db->table('users')->select('id,email AS username,name AS display_name,role,is_active AS active,created_at')->orderBy('name')->get()->getResultArray(); return $this->ok(['users'=>$rows]); }
    private function audit(){ if(!$this->isAdmin())return $this->fail('Hanya admin.',403); $rows=$this->db->table('audit_logs a')->select('a.created_at,a.action,a.target_type AS entity_type,a.target_id AS entity_id,u.name AS display_name')->join('users u','u.id=a.user_id','left')->orderBy('a.id','DESC')->limit(100)->get()->getResultArray(); return $this->ok(['entries'=>$rows]); }
    private function logout(){ $this->session->destroy(); return $this->ok(); }
    private function json():array { return $this->request->getJSON(true) ?? $this->request->getPost() ?? []; }
    private function amount(array $input,string $key):float { return max(0,(float)($input[$key]??0)); }
    private function isAdmin():bool { return current_user('role')==='admin'; }
    private function canSeeAllProjects():bool { return in_array(current_user('role'),['admin','manager'],true); }
    private function findAccessibleProject(int $id):?array { $row=$this->db->table('scheduler_projects')->where('id',$id)->get()->getRowArray(); if(!$row)return null; return $this->canSeeAllProjects()||(int)$row['owner_id']===(int)current_user('id')?$row:null; }
    private function writeAudit(string $action,string $type,int $id,string $description):void { write_audit_log(['module'=>'scheduler','action'=>$action,'description'=>$description,'target_type'=>$type,'target_id'=>$id,'properties'=>[]]); }
    private function ok(array $data=[]){ return $this->response->setJSON(['ok'=>true]+$data); }
    private function fail(string $message,int $status){ return $this->response->setStatusCode($status)->setJSON(['ok'=>false,'error'=>$message]); }
}
