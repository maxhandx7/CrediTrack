<?php

use App\Models\Loan;
use App\Services\LoanLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CrediTrack v3: libro contable de cuotas, notificaciones por WhatsApp,
 * acceso de clientes con código y webhooks. No borra ningún dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Infraestructura que Laravel 11+ espera (solo si no existe) ──
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
        }
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        // ── Prestamistas: notificaciones y webhook propio ──
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notifications_enabled')->default(true)->after('role');
            $table->string('webhook_url')->nullable()->after('notifications_enabled');
            $table->text('webhook_secret')->nullable()->after('webhook_url');
        });

        // ── Clientes: la cédula era única en TODO el sistema (solo por validación);
        //    ahora es única por prestamista, y además lo garantiza la base de datos ──
        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['user_id', 'document']);
            $table->boolean('whatsapp_opt_in')->default(true)->after('phone');
        });

        // ── Préstamos: total a pagar (capital + interés) guardado ──
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('total_amount', 12, 2)->nullable()->after('amount');
            $table->unsignedInteger('installments_count')->default(0)->after('total_amount');
        });

        // ── Cuotas: cuánto se ha abonado a cada una ──
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('amount_due');
            $table->date('paid_at')->nullable()->after('amount_paid');
            $table->index(['status', 'scheduled_date']);
        });

        // ── Códigos de acceso de clientes (enviados por WhatsApp) ──
        Schema::create('client_login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        // ── Registro de avisos enviados (evita repetir el mismo recordatorio) ──
        Schema::create('sent_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Recalcular los préstamos existentes con el libro contable nuevo ──
        // total_amount = suma de cuotas ya generadas (incluyen el interés).
        foreach (DB::table('loans')->pluck('id') as $id) {
            $sum = (float) DB::table('loan_schedules')->where('loan_id', $id)->sum('amount_due');
            $count = DB::table('loan_schedules')->where('loan_id', $id)->count();
            DB::table('loans')->where('id', $id)->update([
                'total_amount' => $sum > 0 ? $sum : DB::raw('amount'),
                'installments_count' => $count,
            ]);
        }

        Loan::query()->with(['schedules', 'payments'])->each(fn (Loan $loan) => app(LoanLedger::class)->recalculate($loan));
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_notifications');
        Schema::dropIfExists('client_login_codes');
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_date']);
            $table->dropColumn(['amount_paid', 'paid_at']);
        });
        Schema::table('loans', fn (Blueprint $table) => $table->dropColumn(['total_amount', 'installments_count']));
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'document']);
            $table->dropColumn('whatsapp_opt_in');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notifications_enabled', 'webhook_url', 'webhook_secret']));
    }
};
