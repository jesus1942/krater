<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendDeploySnapshotsAudit extends Migration
{
    /** Conserva las fotos existentes y agrega el limite de auditoria e identidades. */
    public function up()
    {
        Schema::table('deploy_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('audit_cursor')->nullable();
            $table->longText('memberships')->nullable();
            $table->longText('explained_losses')->nullable();
            $table->unsignedInteger('accepted_by')->nullable();
            $table->text('acceptance_reason')->nullable();
        });
    }

    public function down()
    {
        Schema::table('deploy_snapshots', function (Blueprint $table) {
            $table->dropColumn(['audit_cursor', 'memberships', 'explained_losses', 'accepted_by', 'acceptance_reason']);
        });
    }
}
