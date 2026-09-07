<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reemplaza la columna `users.tipo` (enum Staff/Cliente) por los roles de
     * spatie/laravel-permission. Se resuelve todo con la capa de query builder,
     * sin tocar los modelos Eloquent (User, Role), para no depender de código
     * de aplicación que puede cambiar después de esta migración.
     */
    public function up(): void
    {
        $now = now();

        $staffRoleId = DB::table('roles')->insertGetId([
            'name' => 'staff',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $clienteRoleId = DB::table('roles')->insertGetId([
            'name' => 'cliente',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleIdPorTipo = [
            'staff' => $staffRoleId,
            'cliente' => $clienteRoleId,
        ];

        DB::table('users')->select('id', 'tipo')->orderBy('id')->get()->each(function (object $user) use ($roleIdPorTipo): void {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleIdPorTipo[$user->tipo] ?? $roleIdPorTipo['staff'],
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('tipo')->default('staff')->after('password');
        });

        $staffRoleId = DB::table('roles')->where('name', 'staff')->where('guard_name', 'web')->value('id');
        $clienteRoleId = DB::table('roles')->where('name', 'cliente')->where('guard_name', 'web')->value('id');

        if ($clienteRoleId !== null) {
            DB::table('model_has_roles')
                ->where('role_id', $clienteRoleId)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id')
                ->each(fn (int $userId) => DB::table('users')->where('id', $userId)->update(['tipo' => 'cliente']));
        }

        DB::table('model_has_roles')->whereIn('role_id', array_filter([$staffRoleId, $clienteRoleId]))->delete();
        DB::table('roles')->whereIn('id', array_filter([$staffRoleId, $clienteRoleId]))->delete();
    }
};
