<?php

namespace Tests\Feature;

use App\Http\Middleware\NormalizePhoneNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PhoneValidationTest extends TestCase
{
    public function test_all_declared_phone_fields_use_country_validation_and_keep_required_nullable_semantics(): void
    {
        foreach (['phone','admin_phone','contact_phone','whatsapp_phone','reply_whatsapp'] as $field) {
            $this->assertTrue(Validator::make([$field=>'123'],[$field=>'nullable|string|max:40'])->fails(),$field);
            $this->assertTrue(Validator::make([$field=>null],[$field=>'nullable|string|max:40'])->passes(),$field);
            $this->assertTrue(Validator::make([],[$field=>'required|string|max:40'])->fails(),$field);
            $valid=Validator::make([$field=>'+1 (416) 555-0123'],[$field=>['required','string','max:40']]);
            $this->assertSame('+14165550123',$valid->validated()[$field]);
        }
        $nested=Validator::make(['contacts'=>[['phone'=>'123']]],['contacts.*.phone'=>'nullable|string']);
        $this->assertTrue($nested->fails());
        $this->assertArrayHasKey('contacts.0.phone',$nested->errors()->toArray());
    }

    public function test_request_is_normalized_for_controllers_reading_request_properties_and_invalid_values_are_retained(): void
    {
        $request=Request::create('/profile','PATCH',['phone'=>'+51 987 654 321','admin_phone'=>'123']);
        (new NormalizePhoneNumbers)->handle($request,function($request){
            $this->assertSame('+51987654321',$request->phone);
            $this->assertSame('123',$request->admin_phone);
            return response('ok');
        });
    }

    public function test_rule_cannot_be_bypassed_with_a_forged_country_field_and_allows_landlines(): void
    {
        $validator=Validator::make(['phone'=>'+999123456789','phone_country'=>'CL'],['phone'=>'required|string']);
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('país seleccionado',$validator->errors()->first('phone'));
        $this->assertTrue(Validator::make(['phone'=>'+56 2 2345 6789'],['phone'=>'required|string'])->passes());
    }

    public function test_branch_error_bag_and_excluded_fields_keep_their_existing_behavior(): void
    {
        try {
            Validator::make(['phone'=>'123'],['phone'=>'nullable|string'])->validateWithBag('editBranch');
            $this->fail('Invalid phone accepted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame('editBranch',$exception->errorBag);
            $this->assertArrayHasKey('phone',$exception->errors());
        }
        $this->assertSame([],Validator::make(['phone'=>'123'],['phone'=>'exclude'])->validated());
    }
}
