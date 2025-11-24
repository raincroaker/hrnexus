<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveAccessRequestRequest;
use App\Http\Requests\RejectAccessRequestRequest;
use App\Http\Requests\UpdateAccessRequestRequest;
use App\Models\DocumentAccessRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DocumentAccessRequestsController extends Controller
{
    /**
     * Approve an access request.
     */
    public function approve(ApproveAccessRequestRequest $request, DocumentAccessRequest $accessRequest): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Update access request status
        $accessRequest->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_message' => $request->input('review_message') ?: "Access request approved by {$employee->full_name}",
        ]);

        // Delete all other requests for the same user and document (keep only the current one)
        DocumentAccessRequest::where('user_id', $accessRequest->user_id)
            ->where('document_id', $accessRequest->document_id)
            ->where('id', '!=', $accessRequest->id)
            ->delete();

        // Load relationships for response
        $accessRequest->load([
            'requester:id,name,email',
            'reviewer:id,name,email',
            'document' => function ($query) {
                $query->select('id', 'file_name', 'mime_type', 'accessibility', 'department_id', 'user_id', 'size')
                    ->with([
                        'department:id,name,code',
                        'user:id,name,email',
                        'tags:id,name',
                    ]);
            },
        ]);

        return response()->json([
            'message' => 'Access request approved successfully.',
            'accessRequest' => $accessRequest,
        ]);
    }

    /**
     * Reject an access request.
     */
    public function reject(RejectAccessRequestRequest $request, DocumentAccessRequest $accessRequest): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Update access request status
        $accessRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_message' => $request->input('review_message') ?: "Access request rejected by {$employee->full_name}",
        ]);

        // Delete all other requests for the same user and document (keep only the current one)
        DocumentAccessRequest::where('user_id', $accessRequest->user_id)
            ->where('document_id', $accessRequest->document_id)
            ->where('id', '!=', $accessRequest->id)
            ->delete();

        // Load relationships for response
        $accessRequest->load([
            'requester:id,name,email',
            'reviewer:id,name,email',
            'document' => function ($query) {
                $query->select('id', 'file_name', 'mime_type', 'accessibility', 'department_id', 'user_id', 'size')
                    ->with([
                        'department:id,name,code',
                        'user:id,name,email',
                        'tags:id,name',
                    ]);
            },
        ]);

        return response()->json([
            'message' => 'Access request rejected successfully.',
            'accessRequest' => $accessRequest,
        ]);
    }

    /**
     * Update an access request status (approve if rejected, reject if approved).
     */
    public function update(UpdateAccessRequestRequest $request, DocumentAccessRequest $accessRequest): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        $newStatus = $request->input('status');
        $statusText = $newStatus === 'approved' ? 'approved' : 'rejected';

        // Update access request status
        $accessRequest->update([
            'status' => $newStatus,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_message' => $request->input('review_message') ?: "Access request {$statusText} by {$employee->full_name}",
        ]);

        // Delete all other requests for the same user and document (keep only the current one)
        DocumentAccessRequest::where('user_id', $accessRequest->user_id)
            ->where('document_id', $accessRequest->document_id)
            ->where('id', '!=', $accessRequest->id)
            ->delete();

        // Load relationships for response
        $accessRequest->load([
            'requester:id,name,email',
            'reviewer:id,name,email',
            'document' => function ($query) {
                $query->select('id', 'file_name', 'mime_type', 'accessibility', 'department_id', 'user_id', 'size')
                    ->with([
                        'department:id,name,code',
                        'user:id,name,email',
                        'tags:id,name',
                    ]);
            },
        ]);

        return response()->json([
            'message' => "Access request {$statusText} successfully.",
            'accessRequest' => $accessRequest,
        ]);
    }
}
