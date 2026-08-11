<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Allergie;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
  

    public function show($customer, $id)
    {
        
        $patient = Patient::with('insurance','encounters')->where('customer_id', app('customer')->id)->findOrFail($id);

        $medicalRecord = $patient->medicalRecord;

        $allergies = $medicalRecord->allergies;

        $chronicDiseases  = $medicalRecord->chronicDiseases ;

        $regularMedications  = $medicalRecord->regularMedications ;

        $previousSurgeries  = $medicalRecord->previousSurgeries ;

        $encounters = $patient->encounters;

        //status 1 abierta, 2 terminada , 3 cancelada
        $currentEncounter = $patient->encounters()->where('status', 1)->latest()->first();

        return view('customer.patient.medical-record', compact('currentEncounter','encounters','patient', 'medicalRecord', 'allergies','chronicDiseases','regularMedications', 'previousSurgeries'));
    }

  
}
