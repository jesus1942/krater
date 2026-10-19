<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila C: el correo se configura desde administracion total y vive en la base,
 * no en el .env del contenedor (que se pierde en cada deploy). Los secretos se
 * guardan cifrados con APP_KEY; nunca se devuelven en claro.
 */
class CreateMailSettings extends Migration
{
    public function up()
    {
        if (Schema::hasTable('mail_settings')) {
            return;
        }
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('driver', 20);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->string('encryption', 10)->nullable();
            $table->string('from_address');
            $table->string('from_name');
            $table->string('mailgun_domain')->nullable();
            $table->string('mailgun_endpoint')->nullable();
            $table->text('mailgun_secret_encrypted')->nullable();
            $table->text('ses_key_encrypted')->nullable();
            $table->text('ses_secret_encrypted')->nullable();
            $table->string('ses_region', 40)->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mail_settings');
    }
}
