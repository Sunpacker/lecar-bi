<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $invalidRoles = DB::table('workspace_members')
            ->whereNotIn('role', ['owner', 'member', 'viewer'])
            ->pluck('role')
            ->unique()
            ->all();

        if (! empty($invalidRoles)) {
            throw new RuntimeException(
                'Cannot constrain workspace_members.role: unknown persisted roles found: '.implode(', ', $invalidRoles)
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE workspace_members ADD CONSTRAINT check_workspace_member_role CHECK (role IN ('owner', 'member', 'viewer'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE workspace_members DROP CONSTRAINT IF EXISTS check_workspace_member_role');
        }
    }
};
