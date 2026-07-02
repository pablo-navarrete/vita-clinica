<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Logo;
use App\Models\LogoLocation;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;

class LogoController extends Controller
{
    public function index()
    {
        //cuenta los logos creados
        $customerId = app('customer')->id;
        $logosCount = Logo::where('customer_id', $customerId)->count();
        return view('customer.logo.index', compact('logosCount'));
    }

    //datatable
    public function getLogo()
    {
        $query = Logo::query()
            ->with('locations')
            ->where('customer_id', app('customer')->id);

        return DataTables::of($query)
            ->addIndexColumn()

            // Array real para edición
            ->addColumn('locations_array', function ($row) {
                return $row->locations->pluck('location')->toArray();
            })

            // HTML para mostrar en la tabla
            ->addColumn('locations', function ($row) {

                $locations = $row->locations;

                if (!$locations || $locations->isEmpty()) {
                    return '<span class="text-gray-400 text-xs">Sin ubicaciones</span>';
                }

                return $locations->map(function ($loc) {
                    return '<span class="inline-block px-2 py-1 mr-1 text-xs bg-blue-100 text-blue-700 rounded">'
                        . e($loc->location) .
                        '</span>';
                })->implode(' ');
            })

            ->addColumn('actions', function ($row) {
                return '';
            })

            ->rawColumns(['locations', 'actions'])

            ->make(true);
    }

    //crear logo
    public function store(Request $request)
    {
        $customerId = app('customer')->id;

        $request->validate([
            'name' => 'required',
            'image' => 'required|image',
            'status' => 'required',
            'locations' => 'required|array|min:1'
        ], [
            'locations.required' => 'Debe seleccionar al menos una ubicación.',
            'locations.array' => 'Las ubicaciones son inválidas.',
            'locations.min' => 'Debe seleccionar al menos una ubicación.'
        ]);



        // Validar conflicto SOLO si el nuevo logo será activo
        if ((int) $request->status === 1) {

            $activeConflict = LogoLocation::where('customer_id', $customerId)
                ->whereIn('location', $request->locations)
                ->whereHas('logo', function ($q) {
                    $q->where('status', 1);
                })
                ->exists();

            if ($activeConflict) {
                return response()->json([
                    'message' => 'Ya existe un logo activo en una ubicación seleccionada'
                ], 422);
            }
        }

        // Guardar imagen
        $path = $request->file('image')
            ->store("logos/customer_{$customerId}", 'public');

        // Crear logo
        $logo = Logo::create([
            'name' => $request->name,
            'url' => $path,
            'status' => $request->status,
            'customer_id' => $customerId,
        ]);

        // Guardar ubicaciones
        foreach ($request->locations as $location) {
            LogoLocation::create([
                'logo_id' => $logo->id,
                'customer_id' => $customerId,
                'location' => $location,
            ]);
        }

        return response()->json([
            'ok' => true
        ]);
    }
    //eliminar logo
    public function destroy(Request $request)
    {

        $id = $request->route('id');

        $logo = Logo::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        // Eliminar imagen física
        if ($logo->url && Storage::disk('public')->exists($logo->url)) {
            Storage::disk('public')->delete($logo->url);
        }

        $logo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logo eliminado permanentemente'
        ]);
    }

    //cambiar estado
    public function toggleStatus(Request $request)
    {
        $id = $request->route('id');

        $logo = Logo::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        // Cambiar estado automáticamente
        $newStatus = $logo->status == 1 ? 0 : 1;

        // Validar solo cuando se intenta activar
        if ($newStatus === 1) {

            $locations = $logo->locations()->pluck('location');

            $activeConflict = LogoLocation::where('customer_id', app('customer')->id)
                ->whereIn('location', $locations)
                ->whereHas('logo', function ($q) use ($logo) {
                    $q->where('status', 1)
                        ->where('id', '!=', $logo->id);
                })
                ->exists();

            if ($activeConflict) {
                return response()->json([
                    'message' => 'Ya existe otro logo activo en una de las ubicaciones seleccionadas.'
                ], 422);
            }
        }

        $logo->update([
            'status' => $newStatus
        ]);

        return response()->json([
            'success' => true
        ]);
    }


    public function update(Request $request)
    {
        $id = $request->route('id');

        $customerId = app('customer')->id;

        $logo = Logo::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $request->validate([
            'name' => 'required',
            'image' => 'nullable|image',
            'status' => 'required',
            'locations' => 'required|array|min:1'
        ], [
            'locations.required' => 'Debe seleccionar al menos una ubicación.',
            'locations.array' => 'Las ubicaciones son inválidas.',
            'locations.min' => 'Debe seleccionar al menos una ubicación.'
        ]);

        // Validar conflicto solamente si quedará activo
        if ((int) $request->status === 1) {

            $activeConflict = LogoLocation::where('customer_id', $customerId)
                ->whereIn('location', $request->locations)
                ->whereHas('logo', function ($q) use ($logo) {
                    $q->where('status', 1)
                        ->where('id', '!=', $logo->id);
                })
                ->exists();

            if ($activeConflict) {
                return response()->json([
                    'message' => 'Ya existe un logo activo en una ubicación seleccionada'
                ], 422);
            }
        }

        // Nueva imagen
        if ($request->hasFile('image')) {

            if ($logo->url && Storage::disk('public')->exists($logo->url)) {
                Storage::disk('public')->delete($logo->url);
            }

            $path = $request->file('image')
                ->store("logos/customer_{$customerId}", 'public');

            $logo->url = $path;
        }

        $logo->name = $request->name;
        $logo->status = $request->status;
        $logo->save();

        // Reemplazar ubicaciones
        LogoLocation::where('logo_id', $logo->id)->delete();

        foreach ($request->locations as $location) {
            LogoLocation::create([
                'logo_id' => $logo->id,
                'customer_id' => $customerId,
                'location' => $location,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logo actualizado correctamente'
        ]);
    }
}
