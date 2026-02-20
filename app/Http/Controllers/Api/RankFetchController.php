<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RankFetchJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RankFetchController extends Controller
{
    public function finish(Request $request)
    {
        $jobId = $request->input('job_id');
        $status = $request->input('status'); // 'success' or 'failed'
        $errorMessage = $request->input('error_message');

        if (!$jobId || !$status) {
            return response()->json([
                'success' => false,
                'message' => 'job_idとstatusが必要です。',
            ], 400);
        }

        $job = RankFetchJob::findOrFail($jobId);

        $job->update([
            'status' => $status === 'success' ? 'completed' : 'failed',
            'error_message' => $errorMessage,
        ]);

        Log::info('RANK_FETCH_JOB_FINISHED', [
            'job_id' => $jobId,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ジョブを完了しました。',
        ]);
    }
}
