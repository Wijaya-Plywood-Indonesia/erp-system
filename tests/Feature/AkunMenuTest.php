<?php

namespace Tests\Feature;

use App\Models\IndukAkun;
use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use App\Models\User;
use Tests\TestCase;

class AkunMenuTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_induk_akun_model_and_attributes()
    {
        $user = User::factory()->create();
        
        $induk = IndukAkun::create([
            'kode_induk_akun' => 'TEST-1000',
            'nama_induk_akun' => 'Test Induk Akun',
            'keterangan' => 'Test Keterangan Induk',
            'saldo_normal' => 'debet',
            'status' => 'aktif',
            'created_by' => $user?->id,
        ]);

        $this->assertDatabaseHas('induk_akuns', [
            'kode_induk_akun' => 'TEST-1000',
            'nama_induk_akun' => 'Test Induk Akun',
            'saldo_normal' => 'debet',
            'status' => 'aktif',
        ]);

        $this->assertInstanceOf(IndukAkun::class, $induk);
        $this->assertEquals('debet', $induk->saldo_normal);
        $this->assertEquals('aktif', $induk->status);
    }

    public function test_anak_akun_model_and_attributes()
    {
        $user = User::factory()->create();
        
        $induk = IndukAkun::first();
        if (!$induk) {
            $induk = IndukAkun::create([
                'kode_induk_akun' => 'TEST-1000',
                'nama_induk_akun' => 'Test Induk Akun',
                'saldo_normal' => 'debet',
                'status' => 'aktif',
            ]);
        }

        // Parent-child recursive test
        $parentAnak = AnakAkun::create([
            'id_induk_akun' => $induk->id,
            'kode_anak_akun' => 'TEST-1100',
            'nama_anak_akun' => 'Test Parent Anak',
            'saldo_normal' => 'debet',
            'status' => 'aktif',
            'created_by' => $user?->id,
            'parent' => null,
        ]);

        $childAnak = AnakAkun::create([
            'id_induk_akun' => $induk->id,
            'kode_anak_akun' => 'TEST-1110',
            'nama_anak_akun' => 'Test Child Anak',
            'saldo_normal' => 'debet',
            'status' => 'aktif',
            'created_by' => $user?->id,
            'parent' => $parentAnak->id,
        ]);

        $this->assertDatabaseHas('anak_akuns', [
            'kode_anak_akun' => 'TEST-1100',
            'parent' => null,
        ]);

        $this->assertDatabaseHas('anak_akuns', [
            'kode_anak_akun' => 'TEST-1110',
            'parent' => $parentAnak->id,
        ]);

        $this->assertTrue($parentAnak->children->contains($childAnak));
        $this->assertEquals($parentAnak->id, $childAnak->parentAkun->id);
    }

    public function test_sub_anak_akun_model_and_attributes()
    {
        $user = User::factory()->create();
        
        $anak = AnakAkun::first();
        if (!$anak) {
            $induk = IndukAkun::first() ?? IndukAkun::create([
                'kode_induk_akun' => 'TEST-1000',
                'nama_induk_akun' => 'Test Induk Akun',
            ]);
            $anak = AnakAkun::create([
                'id_induk_akun' => $induk->id,
                'kode_anak_akun' => 'TEST-1100',
                'nama_anak_akun' => 'Test Parent Anak',
            ]);
        }

        $subAnak = SubAnakAkun::create([
            'id_anak_akun' => $anak->id,
            'kode_sub_anak_akun' => 'TEST-1100.001',
            'nama_sub_anak_akun' => 'Test Sub Anak',
            'saldo_normal' => 'debet',
            'status' => 'aktif',
            'created_by' => $user?->id,
        ]);

        $this->assertDatabaseHas('sub_anak_akuns', [
            'kode_sub_anak_akun' => 'TEST-1100.001',
            'saldo_normal' => 'debet',
            'status' => 'aktif',
        ]);

        $this->assertEquals($anak->id, $subAnak->anakAkun->id);
    }

    public function test_tree_akun_page_view_data()
    {
        $page = new \App\Filament\Pages\TreeAkunPage();
        $viewData = $page->getViewData();

        $this->assertArrayHasKey('indukAkuns', $viewData);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $viewData['indukAkuns']);
    }

    public function test_sub_anak_akun_validation_prefix()
    {
        $induk = IndukAkun::create([
            'kode_induk_akun' => 'TEST-1000',
            'nama_induk_akun' => 'Test Induk Akun',
        ]);

        $anak = AnakAkun::create([
            'id_induk_akun' => $induk->id,
            'kode_anak_akun' => 'TEST-1100',
            'nama_anak_akun' => 'Test Parent Anak',
        ]);

        $schema = new \Filament\Schemas\Schema();
        $schema = \App\Filament\Resources\SubAnakAkuns\Schemas\SubAnakAkunForm::configure($schema);
        
        $components = $schema->getComponents();
        $kodeComponent = null;
        foreach ($components as $component) {
            if ($component->getName() === 'kode_sub_anak_akun') {
                $kodeComponent = $component;
                break;
            }
        }

        $this->assertNotNull($kodeComponent);

        $refComponent = new \ReflectionClass($kodeComponent);
        $rulesProp = $refComponent->getProperty('rules');
        $rulesProp->setAccessible(true);
        $rules = $rulesProp->getValue($kodeComponent);
        
        $closureRule = null;
        foreach ($rules as $rule) {
            $r = is_array($rule) ? $rule[0] : $rule;
            if (is_callable($r)) {
                $ref = new \ReflectionFunction($r);
                $params = $ref->getParameters();
                if (count($params) === 1 && $params[0]->getName() === 'get') {
                    $closureRule = $r;
                    break;
                }
            }
        }
        
        $this->assertNotNull($closureRule);
        
        $mockGet = function ($field) use ($anak) {
            if ($field === 'id_anak_akun') {
                return $anak->id;
            }
            return null;
        };
        
        $innerRules = call_user_func($closureRule, $mockGet);
        $innerFunction = $innerRules[0];
        $this->assertIsCallable($innerFunction);
        
        $failed = false;
        $failCallback = function ($message) use (&$failed) {
            $failed = true;
        };
        
        call_user_func($innerFunction, 'kode_sub_anak_akun', '1', $failCallback);
        $this->assertTrue($failed, "Validation should fail for code '1' because it doesn't start with parent prefix 'TEST-1100.'");
        
        $failed = false;
        call_user_func($innerFunction, 'kode_sub_anak_akun', 'TEST-1100.001', $failCallback);
        $this->assertFalse($failed, "Validation should pass for code 'TEST-1100.001'");
    }

    public function test_anak_akun_validation_rules()
    {
        $induk = IndukAkun::create([
            'kode_induk_akun' => 'TEST-1000',
            'nama_induk_akun' => 'Test Induk Akun',
        ]);

        $schema = new \Filament\Schemas\Schema();
        $schema = \App\Filament\Resources\AnakAkuns\Schemas\AnakAkunForm::configure($schema);
        
        $components = $schema->getComponents();
        $kodeComponent = null;
        foreach ($components as $component) {
            if ($component->getName() === 'kode_anak_akun') {
                $kodeComponent = $component;
                break;
            }
        }

        $this->assertNotNull($kodeComponent);

        $refComponent = new \ReflectionClass($kodeComponent);
        $rulesProp = $refComponent->getProperty('rules');
        $rulesProp->setAccessible(true);
        $rules = $rulesProp->getValue($kodeComponent);
        
        $closureRule = null;
        foreach ($rules as $rule) {
            $r = is_array($rule) ? $rule[0] : $rule;
            if (is_callable($r)) {
                $ref = new \ReflectionFunction($r);
                $params = $ref->getParameters();
                if (count($params) === 1 && $params[0]->getName() === 'get') {
                    $closureRule = $r;
                    break;
                }
            }
        }
        
        $this->assertNotNull($closureRule);
        
        $mockGetInduk = function ($field) use ($induk) {
            if ($field === 'parent') {
                return null;
            }
            if ($field === 'id_induk_akun') {
                return $induk->id;
            }
            return null;
        };
        
        $innerRules1 = call_user_func($closureRule, $mockGetInduk);
        $innerFunction1 = $innerRules1[0];
        
        $failed = false;
        $failCallback = function ($message) use (&$failed) {
            $failed = true;
        };
        
        call_user_func($innerFunction1, 'kode_anak_akun', '1100', $failCallback);
        $this->assertTrue($failed);
        
        $failed = false;
        call_user_func($innerFunction1, 'kode_anak_akun', 'TEST-1000.01', $failCallback);
        $this->assertFalse($failed);
        
        $parentAnak = AnakAkun::create([
            'id_induk_akun' => $induk->id,
            'kode_anak_akun' => 'TEST-1100',
            'nama_anak_akun' => 'Test Parent Anak',
        ]);
        
        $mockGetParent = function ($field) use ($parentAnak) {
            if ($field === 'parent') {
                return $parentAnak->id;
            }
            return null;
        };
        
        $innerRules2 = call_user_func($closureRule, $mockGetParent);
        $innerFunction2 = $innerRules2[0];
        
        $failed = false;
        call_user_func($innerFunction2, 'kode_anak_akun', 'TEST-1200', $failCallback);
        $this->assertTrue($failed);
        
        $failed = false;
        call_user_func($innerFunction2, 'kode_anak_akun', 'TEST-1110', $failCallback);
        $this->assertFalse($failed);
    }

    public function test_sub_anak_akun_validation_padding()
    {
        $schema = new \Filament\Schemas\Schema();
        $schema = \App\Filament\Resources\SubAnakAkuns\Schemas\SubAnakAkunForm::configure($schema);
        
        $components = $schema->getComponents();
        $kodeComponent = null;
        foreach ($components as $component) {
            if ($component->getName() === 'kode_sub_anak_akun') {
                $kodeComponent = $component;
                break;
            }
        }

        $this->assertNotNull($kodeComponent);
        
        // Use Reflection to get mutateStateForValidationUsing closure
        $ref = new \ReflectionClass($kodeComponent);
        
        $propMutate = $ref->getProperty('mutateStateForValidationUsing');
        $propMutate->setAccessible(true);
        $mutateClosure = $propMutate->getValue($kodeComponent);
        $this->assertIsCallable($mutateClosure);
        
        $mutated = call_user_func($mutateClosure, '1900.2');
        $this->assertEquals('1900.02', $mutated);
        
        $mutatedNoChange = call_user_func($mutateClosure, '1900.12');
        $this->assertEquals('1900.12', $mutatedNoChange);
        
        // Use Reflection to get dehydrateStateUsing closure
        $propDehydrate = $ref->getProperty('dehydrateStateUsing');
        $propDehydrate->setAccessible(true);
        $dehydrateClosure = $propDehydrate->getValue($kodeComponent);
        $this->assertIsCallable($dehydrateClosure);
        
        $dehydrated = call_user_func($dehydrateClosure, '1900.2');
        $this->assertEquals('1900.02', $dehydrated);
        
        $dehydratedNoChange = call_user_func($dehydrateClosure, '1900.12');
        $this->assertEquals('1900.12', $dehydratedNoChange);
    }
}
