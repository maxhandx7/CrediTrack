<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CrediTrack v5 (SaaS): empresas, planes, suscripciones y equipos.
 * Cada prestamista existente se convierte en DUEÑO de su propia empresa con todos sus datos;
 * los usuarios "admin" pasan a ser administradores de la plataforma. No se borra nada.
 */
return new class extends Migration
{
    private array $tenantTables = ['clients', 'loans', 'payments', 'payment_promises', 'message_logs', 'activity_logs'];

    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->unsignedInteger('max_active_loans')->nullable();   // null = ilimitado
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_daily_messages')->nullable();
            $table->json('features')->nullable();                     // own_whatsapp, branding, webhook
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('logo')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('trial');           // trial | active | past_due | suspended
            $table->date('trial_ends_at')->nullable();
            $table->date('paid_until')->nullable();                    // null + active = sin vencimiento
            $table->json('settings')->nullable();
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12)->default('lender');            // owner | lender | collector
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('months')->default(1);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('method', 30)->default('manual');           // manual | wompi | mercadopago (futuro)
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('role');
        });

        foreach ($this->tenantTables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // ── Planes iniciales ──
        $now = now();
        DB::table('plans')->insert([
            ['name' => 'Básico', 'slug' => 'basico', 'price_monthly' => 39900, 'max_active_loans' => 50, 'max_users' => 2, 'max_daily_messages' => 100,
                'features' => json_encode(['branding' => false, 'webhook' => false, 'own_whatsapp' => false]), 'sort' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Profesional', 'slug' => 'profesional', 'price_monthly' => 89900, 'max_active_loans' => 300, 'max_users' => 6, 'max_daily_messages' => 400,
                'features' => json_encode(['branding' => true, 'webhook' => true, 'own_whatsapp' => false]), 'sort' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Empresarial', 'slug' => 'empresarial', 'price_monthly' => 179900, 'max_active_loans' => null, 'max_users' => null, 'max_daily_messages' => 1500,
                'features' => json_encode(['branding' => true, 'webhook' => true, 'own_whatsapp' => true]), 'sort' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $topPlan = DB::table('plans')->where('slug', 'empresarial')->value('id');

        // ── Cada usuario existente → su empresa (dueño), con su configuración y su webhook ──
        foreach (DB::table('users')->orderBy('id')->get() as $u) {
            $settings = json_decode($u->settings ?? '[]', true) ?: [];
            $name = $settings['business_name'] ?? $u->name;
            $base = Str::slug($name) ?: 'empresa';
            $slug = $base;
            for ($i = 2; DB::table('companies')->where('slug', $slug)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }

            $companyId = DB::table('companies')->insertGetId([
                'name' => $name, 'slug' => $slug, 'phone' => $u->phone,
                'plan_id' => $topPlan, 'status' => 'active', 'paid_until' => null,   // clientes fundadores: sin vencimiento
                'settings' => json_encode(array_filter([
                    'late_fee' => $settings['late_fee'] ?? null,
                    'templates' => $settings['templates'] ?? null,
                    'notify' => ['enabled' => (bool) ($u->notifications_enabled ?? true)],
                ])),
                'webhook_url' => $u->webhook_url, 'webhook_secret' => $u->webhook_secret,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('company_user')->insert(['company_id' => $companyId, 'user_id' => $u->id, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now]);

            foreach (['clients', 'loans', 'payments', 'payment_promises', 'message_logs', 'activity_logs'] as $t) {
                DB::table($t)->where('user_id', $u->id)->update(['company_id' => $companyId]);
            }
        }

        DB::table('users')->where('role', 'admin')->update(['is_platform_admin' => true]);

        // Cédula única por EMPRESA (antes: por usuario).
        // MySQL usa el índice único (user_id, document) para la llave foránea de user_id:
        // primero se le da un índice propio, si no, no deja borrar el único.
        Schema::table('clients', function (Blueprint $table) {
            $table->index('user_id', 'clients_user_id_index');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'document']);
            $table->unique(['company_id', 'document']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'document']);
            $table->unique(['user_id', 'document']);
        });
        Schema::table('clients', fn (Blueprint $table) => $table->dropIndex('clients_user_id_index'));
        foreach ($this->tenantTables as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropConstrainedForeignId('company_id'));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('plans');
    }
};