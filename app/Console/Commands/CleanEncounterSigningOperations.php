<?php
namespace App\Console\Commands;

use App\Models\EncounterSignature;
use App\Models\EncounterSigningOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanEncounterSigningOperations extends Command
{
    protected $signature = 'signatures:cleanup';
    protected $description = 'Retira archivos preparados de operaciones expiradas sin firma confirmada.';

    public function handle(): int
    {
        EncounterSigningOperation::where('status', 'prepared')->where('expires_at', '<', now())->select('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) DB::transaction(function () use ($row) {
                $op = EncounterSigningOperation::lockForUpdate()->findOrFail($row->id);
                // A live signing transaction holds this same lock. Recheck after waiting for it.
                if ($op->status !== 'prepared' || !$op->expires_at->isPast()
                    || EncounterSignature::where('operation_id', $op->id)->exists()) return;
                if (!Storage::disk('signed_encounters')->deleteDirectory($op->customer_id . '/' . $op->id)) {
                    throw new \RuntimeException('Signing cleanup failed; operation retained for retry.');
                }
                $op->update(['status' => 'abandoned', 'payload' => '']);
            });
        });
        return self::SUCCESS;
    }
}
