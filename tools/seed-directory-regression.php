<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\{Customer,User,Role,Patient};
$database=trim(file_get_contents(storage_path('app/directory-audit/regression/database.txt')));
if (!preg_match('/\Avita_directory_audit_[0-9_]+\z/',$database)) throw new RuntimeException('Only an isolated audit schema is allowed');
$connection=config('database.default');
config(["database.connections.$connection.database"=>$database]);DB::purge($connection);
if(DB::selectOne('SELECT DATABASE() AS db')->db!==$database)throw new RuntimeException('Isolation failed');
DB::transaction(function(){
    $global=User::firstOrCreate(['email'=>'audit-super@example.test','customer_id'=>null],['name'=>'Synthetic SuperAdmin','rut'=>'audit-super','password'=>'synthetic-password','phone'=>'123','status'=>1]);
    $role=Role::firstOrCreate(['customer_id'=>null,'name'=>'Super Administrador']);$global->roles()->syncWithoutDetaching([$role->id]);
    foreach([1,2] as $i) {
        $c=Customer::firstOrCreate(['domain'=>'audit-regression-'.$i],['name'=>'Synthetic fixture '.$i,'legal_name'=>'Synthetic','holder'=>'Synthetic','rut'=>'audit-reg-'.$i,'email'=>'audit'.$i.'@example.test','phone'=>'123','status'=>1]);
        $b=$c->branches()->firstOrCreate(['name'=>'Principal'],['status'=>1]);
        app(App\Http\Controllers\customer\RolePermissionController::class)->ensureClinicRoles($c->id);
        $doctor=User::firstOrCreate(['customer_id'=>$c->id,'email'=>'audit-doctor'.$i.'@example.test'],['name'=>'Synthetic doctor','rut'=>'audit-doctor-'.$i,'password'=>'synthetic-password','phone'=>'123','status'=>1]);
        $doctor->branches()->syncWithoutDetaching([$b->id=>['status'=>1]]);
        $role=Role::where('customer_id',$c->id)->where('name','Doctor')->firstOrFail();
        $role->permissions()->syncWithoutDetaching(App\Models\Permission::whereIn('slug',['appointments.create','patients.view','patients.create','appointments.cancel'])->pluck('id')->all());
        if(!$doctor->branchRoles()->whereKey($role->id)->exists())$doctor->branchRoles()->attach($role->id,['customer_id'=>$c->id,'clinic_branch_id'=>$b->id]);
        $doctor->syncClinicRoleMirror($c->id);
        app(App\Services\SpecialtyCatalog::class)->sync($doctor,$c->id,$b->id,[-1003]);
        Patient::firstOrCreate(['customer_id'=>$c->id,'rut'=>'audit-patient-'.$i],['clinic_branch_id'=>$b->id,'name'=>'Synthetic patient','birth_date'=>'1990-01-01','gender'=>'male']);
    }
});
echo "Synthetic fixtures prepared exclusively in $database\n";
