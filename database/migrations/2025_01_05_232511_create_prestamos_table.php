<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->bigInteger('identificacion');
            $table->unsignedBigInteger('herramienta_id')->nullable();
            $table->unsignedBigInteger('ambiente_id')->nullable();
            $table->string('codigo_herramienta')->nullable();
            $table->unsignedInteger('cantidad')->nullable();
            $table->string('codigo_ambiente')->nullable();
            $table->enum('estado_prestamo' ,['activo','devuelto','mora'])->default('activo');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('herramienta_id')->references('id')->on('herramientas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('ambiente_id')->references('id')->on('ambientes')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
