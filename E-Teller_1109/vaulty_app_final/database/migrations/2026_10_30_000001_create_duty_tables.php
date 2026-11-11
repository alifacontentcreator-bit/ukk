<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 10 30 000001 create duty tables; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        /**
         * Absensi & shift jaga petugas (teller) Bank Mini.
         * Jam shift tidak ditentukan sistem: Supervisor yang mengisinya sesuai kebijakan sekolah.
         */
        public function up(): void
        {
            Schema::create('duty_shifts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60);
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            Schema::create('duty_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('duty_shift_id')->constrained('duty_shifts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // teller yang jaga
                $table->date('duty_date');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['duty_shift_id', 'user_id', 'duty_date'], 'duty_schedule_unique');
                $table->index('duty_date');
            });

            Schema::create('duty_attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('duty_shift_id')->nullable()->constrained('duty_shifts')->nullOnDelete();
                $table->date('duty_date');
                $table->timestamp('check_in_at')->nullable();
                $table->timestamp('check_out_at')->nullable();
                $table->string('status', 15)->default('hadir');                          // hadir | izin | sakit | alpa
                $table->string('note', 255)->nullable();
                $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete(); // diisi bila ditandai Supervisor
                $table->timestamps();

                $table->unique(['user_id', 'duty_date', 'duty_shift_id'], 'duty_attendance_unique');
                $table->index('duty_date');
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('duty_attendances');
            Schema::dropIfExists('duty_schedules');
            Schema::dropIfExists('duty_shifts');
        }
    };