<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;

use App\Models\Assistant;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;


class AssistantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('customer.assistant.index');
    }

    // datatable asistente
    public function getAssistant()
    {
        $query = Assistant::query()
            ->where('customer_id', app('customer')->id);

        return DataTables::of($query)
            ->addIndexColumn() //contador automático
            ->addColumn('actions', function ($row) {
                return '';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    // crear assistant
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'status'        => 'required|in:0,1'
        ], [
            'name.required' => 'El nombre de asistente es obligatorio',
            'status.required' => 'Debe seleccionar un estado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }


        $assistant = Assistant::create([
            'name'           => $request->name,
            'status'         => $request->status,
            'customer_id' => app('customer')->id
        ]);

        return response()->json([
            'success' => true,
            'data' => $assistant
        ]);
    }

    // Actualizar asistente
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ], [
            'name.required' => 'El nombre de asistente es obligatorio',
            'status.required' => 'Debe seleccionar un estado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $id = $request->route('id');

        $assistant = Assistant::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $assistant->update([
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'data' => $assistant
        ]);
    }

    //cambiar estado
    public function toggleStatus(Request $request)
    {
        $id = $request->route('id');

        $assistant = Assistant::where('id', $id)->firstOrFail();

        $assistant->update([
            'status' => $request->status
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $id = $request->route('id');

        $assistant = Assistant::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $assistant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asistente eliminado permanentemente'
        ]);
    }
}
