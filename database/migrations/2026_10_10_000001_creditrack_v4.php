<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CrediTrack v4: pagos con método/comprobante/recibo/anulación, mora automática,
 * promesas de pago, plantillas de mensajes, historial de WhatsApp y bitácora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('webhook_secret');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->nullable()->after('whatsapp_opt_in');
            $table->string('cosigner_name')->nullable()->after('credit_limit');
            $table->string('cosigner_document', 20)->nullable()->after('cosigner_name');
            $table->string('cosigner_phone', 20)->nullable()->after('cosigner_document');
            $table->json('documents')->nullable()->after('notes');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->string('statement_token', 40)->nullable()->unique()->after('notes');
            $table->json('documents')->nullable()->after('statement_token');
        });

        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->string('kind', 12)->default('installment')->after('loan_id');   // installment | fee
            $table->foreignId('parent_id')->nullable()->after('kind')->constrained('loan_schedules')->nullOnDelete();
            $table->string('note')->nullable()->after('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('receipt_number')->nullable()->after('user_id');
            $table->string('method', 20)->default('efectivo')->after('amount');
            $table->string('reference')->nullable()->after('method');
            $table->string('attachment')->nullable()->after('reference');
            $table->string('receipt_token', 40)->nullable()->unique()->after('attachment');
            $table->timestamp('voided_at')->nullable()->after('notes');
            $table->string('void_reason')->nullable()->after('voided_at');
        });

        Schema::create('payment_promises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('promised_date');
            $table->decimal('amount', 12, 2);
            $table->string('status', 12)->default('pending');   // pending | kept | broken
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'promised_date']);
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->text('body');
            $table->string('status', 10)->default('sent');      // sent | failed
            $table->string('error')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('event', 20);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        // Tokens y consecutivos para lo que ya existe
        foreach (DB::table('loans')->whereNull('statement_token')->pluck('id') as $id) {
            DB::table('loans')->where('id', $id)->update(['statement_token' => Str::random(40)]);
        }
        foreach (DB::table('payments')->orderBy('date')->orderBy('id')->get(['id', 'user_id']) as $p) {
            $next = (int) DB::table('payments')->where('user_id', $p->user_id)->max('receipt_number') + 1;
            DB::table('payments')->where('id', $p->id)->update(['receipt_number' => $next, 'receipt_token' => Str::random(40)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('payment_promises');
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['receipt_number', 'method', 'reference', 'attachment', 'receipt_token', 'voided_at', 'void_reason']));
        Schema::table('loan_schedules', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_id');
            $t->dropColumn(['kind', 'note']);
        });
        Schema::table('loans', fn (Blueprint $t) => $t->dropColumn(['statement_token', 'documents']));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn(['credit_limit', 'cosigner_name', 'cosigner_document', 'cosigner_phone', 'documents']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('settings'));
    }
};
