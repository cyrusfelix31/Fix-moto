<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MechanicDocument;
use App\Models\User;
use App\Services\ShopDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShopVerificationController extends Controller
{
    // ====================================
    // MECHANIC VERIFICATION STATUS
    // ====================================

    public function status(Request $request)
    {
        abort_unless(
            $request->user()->role === 'mechanic',
            403
        );

        abort_if(
            $request->user()->is_suspended,
            403
        );

        $profile = $request->user()
            ->mechanicProfile()
            ->with('documents')
            ->firstOrFail();

        if (
            $profile->is_verified
            && $profile->verification_status === 'approved'
        ) {

            return redirect()->route(
                'mechanic.dashboard'
            );
        }

        $latestVerification = $request->user()
            ->mechanicVerifications()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();

        return view(
            'auth.pending-verification',
            compact(
                'profile',
                'latestVerification'
            )
        );
    }


    // ====================================
    // ADMIN VERIFICATION LIST
    // ====================================

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $shops = User::query()
            ->where('role', 'mechanic')
            ->whereHas('mechanicProfile')
            ->with('mechanicProfile')
            ->latest()
            ->get();

        $counts = [

            'pending' => $shops->filter(
                fn ($shop) =>
                    $shop->mechanicProfile
                        ->verification_status === 'pending'
            )->count(),

            'approved' => $shops->filter(
                fn ($shop) =>
                    $shop->mechanicProfile
                        ->verification_status === 'approved'
            )->count(),

            'rejected' => $shops->filter(
                fn ($shop) =>
                    $shop->mechanicProfile
                        ->verification_status === 'rejected'
            )->count(),

        ];

        return view(
            'admin.verification',
            compact('shops', 'counts')
        );
    }


    // ====================================
    // VIEW SHOP AND DOCUMENTS
    // ====================================

    public function show(
        Request $request,
        User $user
    ) {

        $this->authorizeAdmin($request);

        abort_unless(
            $user->role === 'mechanic',
            404
        );

        $profile = $user
            ->mechanicProfile()
            ->with([
                'documents',
                'members'
            ])
            ->firstOrFail();

        return view(
            'admin.verification-details',
            compact('user', 'profile')
        );
    }


    // ====================================
    // SECURE DOCUMENT PREVIEW
    // ====================================

    public function viewDocument(
        Request $request,
        MechanicDocument $document
    ) {

        $this->authorizeAdmin($request);

        $disk = Storage::disk('local');

        abort_unless(
            $disk->exists(
                $document->document_path
            ),
            404
        );

        $mimeType = $disk->mimeType(
            $document->document_path
        );

        abort_unless(

            in_array($mimeType, [
                'application/pdf',
                'image/jpeg',
                'image/png',
            ], true),

            415

        );

        return response()->file(

            $disk->path(
                $document->document_path
            ),

            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }


    // ====================================
    // INDIVIDUAL DOCUMENT REVIEW
    // ====================================

    public function reviewDocument(
        Request $request,
        MechanicDocument $document
    ) {

        $this->authorizeAdmin($request);

        $data = $request->validate([

            'decision' => [
                'required',
                'in:approve,reject'
            ],

            'reason' => [
                'nullable',
                'required_if:decision,reject',
                'string',
                'max:1000'
            ],

        ]);

        DB::transaction(function () use (
            $request,
            $document,
            $data
        ) {

            $profile = $document
                ->mechanicProfile()
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $profile->verification_status === 'pending',
                409
            );

            $document->refresh();

            abort_unless(
                $document->mechanic_profile_id === $profile->id,
                404
            );

            if (
                $data['decision'] === 'approve'
                && $document->isExpired()
            ) {

                throw ValidationException::withMessages([
                    'document' =>
                        'An expired document cannot be approved.',
                ]);
            }

            $document->update([

                'status' =>
                    $data['decision'] === 'approve'
                        ? 'approved'
                        : 'rejected',

                'rejection_reason' =>
                    $data['decision'] === 'reject'
                        ? $data['reason']
                        : null,

                'reviewed_by' =>
                    $request->user()->id,

                'reviewed_at' => now(),

            ]);

            AuditLog::record(

                $request->user(),

                'mechanic.document_reviewed',

                $profile->mechanic,

                [
                    'document_type' =>
                        $document->document_type,

                    'decision' =>
                        $data['decision'],
                ]

            );

        });

        return back()->with(
            'success',
            'Document verification updated.'
        );
    }


    // ====================================
    // APPROVE OR REJECT APPLICATION
    // ====================================

    public function decide(
        Request $request,
        User $user
    ) {

        $this->authorizeAdmin($request);

        abort_unless(
            $user->role === 'mechanic',
            404
        );

        $data = $request->validate([

            'decision' => [
                'required',
                'in:approve,reject'
            ],

            'reason' => [
                'nullable',
                'required_if:decision,reject',
                'string',
                'max:1000'
            ],

        ]);

        DB::transaction(function () use (
            $request,
            $user,
            $data
        ) {

            $profile = $user
                ->mechanicProfile()
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $profile->verification_status === 'pending',
                409
            );

            $approved =
                $data['decision'] === 'approve';

            if ($approved) {

                if (!$profile->allDocumentsApproved()) {

                    throw ValidationException::withMessages([
                        'verification' =>
                            'All required documents must be approved and valid.',
                    ]);
                }

                foreach ($profile->documents as $document) {

                    if (!Storage::disk('local')->exists(
                        $document->document_path
                    )) {

                        throw ValidationException::withMessages([
                            'verification' =>
                                'A required document file is missing.',
                        ]);
                    }
                }
            }

            $profile->update([

                'verification_status' =>
                    $approved
                        ? 'approved'
                        : 'rejected',

                'is_verified' => $approved,

                'is_online' => false,

            ]);

            // Preserve the existing verification history.

            $user->mechanicVerifications()->create([

                'document_path' => '',

                'status' =>
                    $approved
                        ? 'approved'
                        : 'rejected',

                'rejection_reason' =>
                    $approved
                        ? null
                        : $data['reason'],

                'reviewed_by' =>
                    $request->user()->id,

                'reviewed_at' => now(),

            ]);

            AuditLog::record(

                $request->user(),

                'mechanic.verification_updated',

                $user,

                [
                    'decision' => $data['decision']
                ]

            );

        });

        return redirect()
            ->route('admin.verification')
            ->with(
                'success',
                'Mechanic shop application updated.'
            );
    }


    // ====================================
    // DOCUMENT RESUBMISSION
    // ====================================

    public function resubmit(
        Request $request,
        ShopDocumentService $documentService
    ) {

        $user = $request->user();

        abort_unless(
            $user->role === 'mechanic',
            403
        );

        abort_if(
            $user->is_suspended,
            403
        );

        $profile = $user
            ->mechanicProfile()
            ->firstOrFail();

        $eligible =

            $profile->verification_status === 'rejected'

            || (

                $profile->verification_status === 'pending'

                && !$profile->documents()->exists()

            );

        abort_unless($eligible, 409);

        $request->validate(
            $documentService->rules()
        );

        $documents = $documentService->store(
            $request
        );

        try {

            $oldPaths = DB::transaction(
                function () use (
                    $profile,
                    $documents,
                    $user
                ) {

                    $lockedProfile = $profile
                        ->newQuery()
                        ->whereKey($profile->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $eligible =

                        $lockedProfile->verification_status === 'rejected'

                        || (

                            $lockedProfile->verification_status === 'pending'

                            && !$lockedProfile->documents()->exists()

                        );

                    abort_unless($eligible, 409);

                    $oldPaths = $lockedProfile
                        ->documents()
                        ->pluck('document_path')
                        ->all();

                    $lockedProfile
                        ->documents()
                        ->delete();

                    $lockedProfile
                        ->documents()
                        ->createMany($documents);

                    $lockedProfile->update([

                        'verification_status' => 'pending',

                        'is_verified' => false,

                        'is_online' => false,

                    ]);

                    AuditLog::record(

                        $user,

                        'mechanic.documents_resubmitted'

                    );

                    return $oldPaths;
                }
            );

        } catch (Throwable $exception) {

            $documentService->delete(
                array_column(
                    $documents,
                    'document_path'
                )
            );

            throw $exception;
        }

        // Delete replaced files only after the database commits.
        $documentService->delete($oldPaths);

        return redirect()
            ->route('mechanic.verification.status')
            ->with(
                'success',
                'Your documents have been submitted for verification.'
            );
    }


    // ====================================
    // ADMIN AUTHORIZATION
    // ====================================

    private function authorizeAdmin(
        Request $request
    ): void
    {
        abort_unless(
            $request->user()?->role === 'admin'
            && !$request->user()->is_suspended,
            403
        );
    }
}
