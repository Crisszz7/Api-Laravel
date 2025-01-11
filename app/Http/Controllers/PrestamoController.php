<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ambiente;
use App\Models\Herramienta;
use App\Models\Prestamo;
use App\Models\Usuario;
use App\Models\Historial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrestamoController extends Controller
{

    public function index():JsonResponse
    {
        return response()->json(Prestamo::with(['usuario', 'herramientas', 'ambiente'])->get());
    }

    public function store(Request $request):JsonResponse
    {
        $usuario = Usuario::where('identificacion', $request->identificacion)->first();

        if (!$usuario) {
            return response()->json(['prestamo realizado' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $validarExistencia = Prestamo::where('usuario_id', $usuario->id)->where('estado_prestamo', 'activo')->exists();

        if ($validarExistencia) {
            return response()->json(['prestamo realizado' => false, 'message' => 'El usuario tiene un prestamo activo'], 404);
        }

        $herramienta = null;
        $ambiente = null;

        if ($request->has('codigo_ambiente')) {
            $ambiente = Ambiente::where('codigo', $request->codigo_ambiente)->first();
            if (!$ambiente) {
                return response()->json(['prestamo realizado' => false, 'message' => 'Ambiente no encontrado'], 404);
            }
            if ($ambiente->disponible == false) {
                return response()->json(['prestamo realizado' => false, 'message' => 'Ambiente no disponible'], 400);
            }
            if ($usuario->rol_id != 2) {
                return response()->json(['prestamo realizado' => false, 'message' => 'Usuario No autorizado'], 400);
            }
            $ambiente->disponible = false;
            $ambiente->save();
        }

        $prestamo = Prestamo::create([
            'usuario_id' => $usuario->id,
            'identificacion' => $request->identificacion,
            'ambiente_id' => $ambiente ? $ambiente->id : null,
            'codigo_ambiente' => $ambiente ? $ambiente->codigo : null,
            'estado_prestamo'=> 'activo',
            'observaciones' => $request->observaciones
        ]);

        Historial::create([
            'usuario_id' => $usuario->id,
            'prestamo_id' => $prestamo->id,
            'estado' => 'activo'
        ]);


        if ($request->has('codigo_herramienta')) {
            foreach ($request->codigo_herramienta as $index => $codigoHerramienta) {
                $herramienta = Herramienta::where('codigo', $codigoHerramienta)->first();
            if (!$herramienta) {
                return response()->json(['prestamo realizado' => false, 'message' => 'Herramienta no encontrada'], 404);
            }
            if ($herramienta->stock < $request->cantidad[$index]) {
                return response()->json(['prestamo realizado' => false, 'message' => 'stock No disponible'], 400);
            }
            $herramienta->stock -= $request->cantidad[$index];
            $herramienta->save();

            $prestamo->herramientas()->attach($herramienta->id, ['cantidad' => $request->cantidad[$index]]);
        }
    }

        return response()->json([
            'success' => true,
            'message' => 'prestamo realizado con exito',
            'data' => $prestamo
        ], 201);

    }

    public function show($identificacion):JsonResponse
    {
        $prestamo = Prestamo::where('identificacion', $identificacion)->first();

        if (!$prestamo) {
            return response()->json(['message' => 'Prestamo no encontrado'], 404);
        }

        $prestamo->load('herramientas');

        return response()->json($prestamo);
    }

    public function update(Request $request, $identificacion):JsonResponse
    {
        $prestamo = Prestamo::where('identificacion', $identificacion)->first();

        if (!$prestamo) {
            return response()->json(['message' => 'Prestamo no encontrado'], 404);
        }

        $prestamo->codigo_ambiente = $request->input('codigo_ambiente', $prestamo->codigo_ambiente);
        $prestamo->observaciones = $request->input('observaciones', $prestamo->observaciones);
        $prestamo->save();

        if ($request->has('codigo_herramienta')) {
            foreach ($request->codigo_herramienta as $index => $codigoHerramienta) {
                $herramienta = Herramienta::where('codigo', $codigoHerramienta)->first();
            if (!$herramienta) {
                return response()->json(['prestamo realizado' => false, 'message' => 'Herramienta no encontrada'], 404);
            }
            if ($herramienta->stock == $request->cantidad[$index]) {
                return response()->json(['prestamo realizado' => false, 'message' => 'stock No disponible'], 400);
            }
            $herramienta->stock -= $request->cantidad[$index];
            $herramienta->save();

            $prestamo->herramientas()->syncWithoutDetaching([$herramienta->id => ['cantidad' => $request->cantidad[$index]]]);

        }
    }

    $prestamo->save();

        return response()->json([
            'success' => true,
            'message' => 'Prestamo actualizado exitosamente',
            'data' => $prestamo
        ], 200); 

    }

    public function destroy($identificacion)
{

    $usuario = Usuario::where('identificacion', $identificacion)->first();

    if (!$usuario) {
        return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
    }

    $prestamo = $usuario->prestamos()
        ->where('estado_prestamo', 'activo')
        ->first();

    if (!$prestamo) {
        return response()->json(['success' => false, 'message' => 'No hay préstamos existentes'], 404);
    }

    if ($prestamo->ambiente_id) {
        $ambiente = Ambiente::find($prestamo->ambiente_id);
        if ($ambiente) {
            $ambiente->disponible = true;
            $ambiente->save();
        }
    }

    $herramientas = $prestamo->herramientas;
    foreach ($herramientas as $herramienta) {
        $cantidad_prestada = $herramienta->pivot->cantidad ?? 0;
        $herramienta->stock += $cantidad_prestada;
        $herramienta->save();
    }

    $prestamo->estado_prestamo = 'devuelto';
    $prestamo->save();

    Historial::updateOrCreate(
        ['prestamo_id' => $prestamo->id],
        [
            'usuario_id' => $usuario->id,
            'estado' => 'devuelto'
        ]
    );

    return response()->json([
        'success' => true,
        'message' => 'Préstamo finalizado con éxito',
        'data' => $prestamo
    ], 200);
}

}